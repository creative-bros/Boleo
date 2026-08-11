<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BackupDatabaseToPostgres extends Command
{
    protected $signature = 'db:backup-postgres
        {--source= : Source database connection; defaults to DB_CONNECTION}
        {--target=backup_pgsql : Target PostgreSQL backup connection}
        {--chunk=500 : Rows to copy per insert batch}
        {--force : Required when running in production}';

    protected $description = 'Synchronize the primary database into a PostgreSQL backup database.';

    public function handle(): int
    {
        $sourceName = (string) ($this->option('source') ?: config('database.default'));
        $targetName = (string) $this->option('target');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($sourceName === $targetName) {
            $this->error('Source and target database connections must be different.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This command rewrites the backup database. Re-run with --force in production.');

            return self::FAILURE;
        }

        try {
            $source = DB::connection($sourceName);
            $target = DB::connection($targetName);
            $this->validateConnections($sourceName, $targetName, $source, $target);

            $this->line("Migrating backup schema on [{$targetName}]...");
            $migrationExitCode = Artisan::call('migrate', [
                '--database' => $targetName,
                '--force' => true,
            ]);

            if ($migrationExitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'Backup database migrations failed.');
            }

            $sourceTables = $this->sourceTables($source);
            $targetTables = array_map(
                fn (string $table): string => $this->unqualifiedTableName($table),
                $target->getSchemaBuilder()->getTableListing()
            );
            $tables = array_values(array_intersect($sourceTables, $targetTables));

            if ($tables === []) {
                throw new RuntimeException('No matching tables were found between source and backup databases.');
            }

            $orderedTables = $this->sortTablesByDependencies($target, $tables);

            $this->truncateTargetTables($target, $tables);

            $copied = [];

            foreach ($orderedTables as $table) {
                $copied[$table] = $this->copyTable($source, $target, $table, $chunkSize);
                $this->line("Copied {$copied[$table]} rows from [{$table}].");
            }

            $this->resetPostgresSequences($target, $orderedTables);

            $this->info('PostgreSQL backup synchronized.');
            $this->line('Tables: '.count($orderedTables));
            $this->line('Rows: '.array_sum($copied));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function validateConnections(string $sourceName, string $targetName, Connection $source, Connection $target): void
    {
        $source->getPdo();
        $target->getPdo();

        if ($target->getDriverName() !== 'pgsql') {
            throw new RuntimeException("Backup connection [{$targetName}] must use the pgsql driver.");
        }

        if ($source->getDriverName() === 'pgsql' && $sourceName === $targetName) {
            throw new RuntimeException('Refusing to back up PostgreSQL into itself.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function sourceTables(Connection $source): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $table): string => $this->unqualifiedTableName($table),
                $source->getSchemaBuilder()->getTableListing()
            ),
            fn (string $table): bool => ! str_starts_with($table, 'sqlite_')
        ));
    }

    private function truncateTargetTables(Connection $target, array $tables): void
    {
        $quotedTables = array_map(fn (string $table): string => $this->quotePgIdentifier($table), $tables);

        $target->statement('TRUNCATE TABLE '.implode(', ', $quotedTables).' RESTART IDENTITY CASCADE');
    }

    private function copyTable(Connection $source, Connection $target, string $table, int $chunkSize): int
    {
        $columns = $target->getSchemaBuilder()->getColumns($table);
        $columnTypes = [];

        foreach ($columns as $column) {
            if (! isset($column['name'])) {
                continue;
            }

            $columnTypes[$column['name']] = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
        }

        $copied = 0;
        $batch = [];

        foreach ($source->table($table)->cursor() as $row) {
            $batch[] = $this->normalizeRowForPostgres((array) $row, $columnTypes);

            if (count($batch) >= $chunkSize) {
                $target->table($table)->insert($batch);
                $copied += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $target->table($table)->insert($batch);
            $copied += count($batch);
        }

        return $copied;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columnTypes
     * @return array<string, mixed>
     */
    private function normalizeRowForPostgres(array $row, array $columnTypes): array
    {
        foreach ($row as $column => $value) {
            $type = $columnTypes[$column] ?? '';

            if ($value === null) {
                continue;
            }

            if (str_contains($type, 'bool')) {
                $row[$column] = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (($type === 'json' || $type === 'jsonb') && $value === '') {
                $row[$column] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function sortTablesByDependencies(Connection $target, array $tables): array
    {
        $tableSet = array_fill_keys($tables, true);
        $dependencies = array_fill_keys($tables, []);

        $foreignKeys = $target->select(<<<'SQL'
            select
                tc.table_name as table_name,
                ccu.table_name as foreign_table_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu
                on tc.constraint_name = kcu.constraint_name
                and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu
                on ccu.constraint_name = tc.constraint_name
                and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY'
                and tc.table_schema = 'public'
        SQL);

        foreach ($foreignKeys as $foreignKey) {
            $table = (string) $foreignKey->table_name;
            $foreignTable = (string) $foreignKey->foreign_table_name;

            if (isset($tableSet[$table], $tableSet[$foreignTable]) && $table !== $foreignTable) {
                $dependencies[$table][] = $foreignTable;
            }
        }

        $ordered = [];
        $visiting = [];
        $visited = [];

        foreach ($tables as $table) {
            $this->visitTable($table, $dependencies, $visiting, $visited, $ordered);
        }

        return $ordered;
    }

    /**
     * @param  array<string, array<int, string>>  $dependencies
     * @param  array<string, bool>  $visiting
     * @param  array<string, bool>  $visited
     * @param  array<int, string>  $ordered
     */
    private function visitTable(string $table, array $dependencies, array &$visiting, array &$visited, array &$ordered): void
    {
        if (isset($visited[$table])) {
            return;
        }

        if (isset($visiting[$table])) {
            throw new RuntimeException("Circular foreign-key dependency detected at table [{$table}].");
        }

        $visiting[$table] = true;

        foreach ($dependencies[$table] ?? [] as $dependency) {
            $this->visitTable($dependency, $dependencies, $visiting, $visited, $ordered);
        }

        unset($visiting[$table]);
        $visited[$table] = true;
        $ordered[] = $table;
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function resetPostgresSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            $columns = $target->getSchemaBuilder()->getColumns($table);

            foreach ($columns as $column) {
                $columnName = (string) ($column['name'] ?? '');

                if ($columnName === '') {
                    continue;
                }

                $sequence = $target->selectOne(
                    'select pg_get_serial_sequence(?, ?) as sequence',
                    ['public.'.$table, $columnName]
                )->sequence ?? null;

                if (! is_string($sequence) || $sequence === '') {
                    continue;
                }

                $quotedTable = $this->quotePgIdentifier($table);
                $quotedColumn = $this->quotePgIdentifier($columnName);

                $target->statement(
                    "select setval(?, coalesce((select max({$quotedColumn}) from {$quotedTable}), 1), exists(select 1 from {$quotedTable}))",
                    [$sequence]
                );
            }
        }
    }

    private function quotePgIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function unqualifiedTableName(string $table): string
    {
        if (! str_contains($table, '.')) {
            return $table;
        }

        return substr($table, strrpos($table, '.') + 1);
    }
}
