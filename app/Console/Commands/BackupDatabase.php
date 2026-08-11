<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--connection= : Database connection to back up}
        {--path= : Absolute path or project-relative directory for backup archives}
        {--filename= : Backup filename without extension}
        {--keep= : Number of backup archives to keep; defaults to DB_BACKUP_KEEP or 14, use 0 to disable pruning}';

    protected $description = 'Create a database backup archive for SQLite, MariaDB/MySQL, or PostgreSQL.';

    public function handle(): int
    {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            $this->error("Database connection [{$connection}] is not configured.");

            return self::FAILURE;
        }

        $driver = (string) ($config['driver'] ?? '');
        $createdAt = CarbonImmutable::now();
        $backupDirectory = $this->resolveBackupDirectory($config, $driver);
        $baseFilename = $this->resolveBaseFilename($connection, $driver, $createdAt);

        File::ensureDirectoryExists($backupDirectory, 0750, true);

        if (! is_writable($backupDirectory)) {
            $this->error("Backup directory is not writable: {$backupDirectory}");

            return self::FAILURE;
        }

        $payloadPath = null;

        try {
            [$payloadPath, $payloadName] = match ($driver) {
                'sqlite' => $this->backupSqlite($connection, $config, $backupDirectory, $baseFilename),
                'mariadb', 'mysql' => $this->backupMysql($config, $backupDirectory, $baseFilename),
                'pgsql' => $this->backupPostgres($config, $backupDirectory, $baseFilename),
                default => throw new RuntimeException(
                    "Unsupported database driver [{$driver}]. Use sqlite, mariadb/mysql, or pgsql."
                ),
            };

            $archivePath = $this->zipBackup(
                payloadPath: $payloadPath,
                payloadName: $payloadName,
                backupDirectory: $backupDirectory,
                baseFilename: $baseFilename,
                connection: $connection,
                driver: $driver,
                config: $config,
                createdAt: $createdAt,
            );

            @unlink($payloadPath);

            $this->pruneBackups($backupDirectory, $this->backupPrefix($connection, $driver));

            $this->info('Database backup created.');
            $this->line($archivePath);
            $this->line('Size: '.$this->humanSize((int) filesize($archivePath)));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_string($payloadPath) && is_file($payloadPath)) {
                @unlink($payloadPath);
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function backupSqlite(string $connection, array $config, string $backupDirectory, string $baseFilename): array
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('SQLite backups require a database file, not an in-memory database.');
        }

        $databasePath = $this->absolutePath($database);

        if (! is_file($databasePath)) {
            throw new RuntimeException("SQLite database file does not exist: {$databasePath}");
        }

        $payloadPath = $backupDirectory.DIRECTORY_SEPARATOR.$baseFilename.'.sqlite';

        if (file_exists($payloadPath)) {
            throw new RuntimeException("Backup payload already exists: {$payloadPath}");
        }

        $pdo = DB::connection($connection)->getPdo();
        $quotedPath = $pdo->quote($payloadPath);

        if ($quotedPath === false) {
            throw new RuntimeException('Could not quote SQLite backup path.');
        }

        $pdo->exec('VACUUM INTO '.$quotedPath);

        if (! is_file($payloadPath) || filesize($payloadPath) === 0) {
            throw new RuntimeException('SQLite backup did not create a readable database file.');
        }

        return [$payloadPath, basename($payloadPath)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function backupMysql(array $config, string $backupDirectory, string $baseFilename): array
    {
        $settings = $this->connectionSettings($config);
        $database = (string) ($settings['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('MariaDB/MySQL backups require a database name.');
        }

        $binary = $this->firstExecutable(['mariadb-dump', 'mysqldump']);
        $payloadPath = $backupDirectory.DIRECTORY_SEPARATOR.$baseFilename.'.sql';
        $arguments = [
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set='.($config['charset'] ?? 'utf8mb4'),
            '--host='.(string) ($settings['host'] ?? '127.0.0.1'),
            '--port='.(string) ($settings['port'] ?? '3306'),
            '--user='.(string) ($settings['username'] ?? ''),
            $database,
        ];

        if (! empty($config['unix_socket'])) {
            $arguments[] = '--socket='.(string) $config['unix_socket'];
        }

        $this->runDumpProcess(
            arguments: $arguments,
            payloadPath: $payloadPath,
            environment: ['MYSQL_PWD' => (string) ($settings['password'] ?? '')],
            label: 'MariaDB/MySQL dump',
        );

        return [$payloadPath, basename($payloadPath)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function backupPostgres(array $config, string $backupDirectory, string $baseFilename): array
    {
        $settings = $this->connectionSettings($config);
        $database = (string) ($settings['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('PostgreSQL backups require a database name.');
        }

        $binary = $this->firstExecutable(['pg_dump']);
        $payloadPath = $backupDirectory.DIRECTORY_SEPARATOR.$baseFilename.'.sql';
        $arguments = [
            $binary,
            '--format=plain',
            '--no-owner',
            '--no-acl',
            '--host='.(string) ($settings['host'] ?? '127.0.0.1'),
            '--port='.(string) ($settings['port'] ?? '5432'),
            '--username='.(string) ($settings['username'] ?? ''),
            '--dbname='.$database,
        ];

        $environment = [
            'PGPASSWORD' => (string) ($settings['password'] ?? ''),
        ];

        if (! empty($settings['sslmode'])) {
            $environment['PGSSLMODE'] = (string) $settings['sslmode'];
        }

        $this->runDumpProcess(
            arguments: $arguments,
            payloadPath: $payloadPath,
            environment: $environment,
            label: 'PostgreSQL dump',
        );

        return [$payloadPath, basename($payloadPath)];
    }

    /**
     * @param  array<int, string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function runDumpProcess(array $arguments, string $payloadPath, array $environment, string $label): void
    {
        $handle = fopen($payloadPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Could not write backup payload: {$payloadPath}");
        }

        $errorOutput = '';
        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout(600);

        try {
            $exitCode = $process->run(function (string $type, string $buffer) use ($handle, &$errorOutput): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);

                    return;
                }

                $errorOutput = substr($errorOutput.$buffer, -4000);
            });
        } finally {
            fclose($handle);
        }

        if ($exitCode !== 0) {
            @unlink($payloadPath);

            throw new RuntimeException(trim("{$label} failed. {$errorOutput}"));
        }

        if (! is_file($payloadPath) || filesize($payloadPath) === 0) {
            throw new RuntimeException("{$label} created an empty backup file.");
        }
    }

    private function zipBackup(
        string $payloadPath,
        string $payloadName,
        string $backupDirectory,
        string $baseFilename,
        string $connection,
        string $driver,
        array $config,
        CarbonImmutable $createdAt,
    ): string {
        $archivePath = $backupDirectory.DIRECTORY_SEPARATOR.$baseFilename.'.zip';
        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL);

        if ($opened !== true) {
            throw new RuntimeException("Could not create backup archive: {$archivePath}");
        }

        $manifest = json_encode([
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'created_at' => $createdAt->toIso8601String(),
            'connection' => $connection,
            'driver' => $driver,
            'database' => $this->manifestDatabaseValue($config),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if (! $zip->addFile($payloadPath, $payloadName) || ! $zip->addFromString('manifest.json', $manifest)) {
            $zip->close();
            @unlink($archivePath);

            throw new RuntimeException("Could not write backup archive contents: {$archivePath}");
        }

        if (! $zip->close()) {
            @unlink($archivePath);

            throw new RuntimeException("Could not finalize backup archive: {$archivePath}");
        }

        return $archivePath;
    }

    private function resolveBackupDirectory(array $config, string $driver): string
    {
        $configuredPath = $this->option('path') ?: env('DB_BACKUP_PATH');

        if (is_string($configuredPath) && $configuredPath !== '') {
            return $this->absolutePath($configuredPath);
        }

        $railwayVolume = env('RAILWAY_VOLUME_MOUNT_PATH');

        if (is_string($railwayVolume) && $railwayVolume !== '') {
            return rtrim($railwayVolume, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'backups';
        }

        if ($driver === 'sqlite') {
            $database = (string) ($config['database'] ?? '');
            $databasePath = $database !== '' && $database !== ':memory:' ? $this->absolutePath($database) : '';

            if ($databasePath !== '' && ! str_starts_with($databasePath, base_path())) {
                return dirname($databasePath).DIRECTORY_SEPARATOR.'backups';
            }
        }

        return storage_path('app/private/database-backups');
    }

    private function resolveBaseFilename(string $connection, string $driver, CarbonImmutable $createdAt): string
    {
        $filename = $this->option('filename');

        if (is_string($filename) && $filename !== '') {
            return $this->sanitizeFilename($filename);
        }

        return $this->backupPrefix($connection, $driver).$createdAt->format('Ymd-His');
    }

    private function backupPrefix(string $connection, string $driver): string
    {
        return $this->sanitizeFilename(config('app.name', 'app')).'-'.$this->sanitizeFilename($connection).'-'.$driver.'-';
    }

    private function pruneBackups(string $backupDirectory, string $prefix): void
    {
        $keep = $this->resolveKeepCount();

        if ($keep < 1) {
            return;
        }

        $files = glob($backupDirectory.DIRECTORY_SEPARATOR.$prefix.'*.zip') ?: [];

        usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
        }
    }

    private function resolveKeepCount(): int
    {
        $keep = $this->option('keep');

        if ($keep === null || $keep === '') {
            $keep = env('DB_BACKUP_KEEP', 14);
        }

        return max(0, (int) $keep);
    }

    /**
     * @return array<string, string|null>
     */
    private function connectionSettings(array $config): array
    {
        $settings = [
            'host' => isset($config['host']) ? (string) $config['host'] : null,
            'port' => isset($config['port']) ? (string) $config['port'] : null,
            'database' => isset($config['database']) ? (string) $config['database'] : null,
            'username' => isset($config['username']) ? (string) $config['username'] : null,
            'password' => isset($config['password']) ? (string) $config['password'] : null,
            'sslmode' => isset($config['sslmode']) ? (string) $config['sslmode'] : null,
        ];

        if (empty($config['url']) || ! is_string($config['url'])) {
            return $settings;
        }

        $url = parse_url($config['url']);

        if ($url === false) {
            return $settings;
        }

        if (isset($url['host'])) {
            $settings['host'] = $url['host'];
        }

        if (isset($url['port'])) {
            $settings['port'] = (string) $url['port'];
        }

        if (isset($url['path'])) {
            $settings['database'] = ltrim($url['path'], '/');
        }

        if (isset($url['user'])) {
            $settings['username'] = urldecode($url['user']);
        }

        if (isset($url['pass'])) {
            $settings['password'] = urldecode($url['pass']);
        }

        if (isset($url['query'])) {
            parse_str($url['query'], $query);

            if (isset($query['sslmode']) && is_string($query['sslmode'])) {
                $settings['sslmode'] = $query['sslmode'];
            }
        }

        return $settings;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function firstExecutable(array $names): string
    {
        $finder = new ExecutableFinder;

        foreach ($names as $name) {
            $binary = $finder->find($name);

            if (is_string($binary)) {
                return $binary;
            }
        }

        throw new RuntimeException('Missing database dump binary. Install one of: '.implode(', ', $names));
    }

    private function absolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private function sanitizeFilename(string $value): string
    {
        $filename = strtolower(trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '', '-'));

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException('Invalid backup filename.');
        }

        return $filename;
    }

    private function manifestDatabaseValue(array $config): ?string
    {
        $settings = $this->connectionSettings($config);
        $database = $settings['database'] ?? null;

        return is_string($database) && $database !== '' ? $database : null;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
