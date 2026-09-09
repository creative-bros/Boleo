<?php

namespace App\Console\Commands;

use App\Models\CondominiumProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RepairCondominiumProfiles extends Command
{
    protected $signature = 'condominiums:repair
        {--renumber : Renumber the three canonical profiles to IDs 1, 2, and 3}
        {--force : Required when running in production}';

    protected $description = 'Delete unnamed condominium profiles and optionally renumber the canonical Boleo profiles.';

    /**
     * @var array<int, array{name: string, aliases: array<int, string>}>
     */
    private array $canonicalProfiles = [
        1 => ['name' => '6 DE OCTUBRE', 'aliases' => ['6 DE OCTUBRE']],
        2 => ['name' => 'LA VIRGEN', 'aliases' => ['LA VIRGEN']],
        3 => ['name' => 'REAL DE BOLEO II', 'aliases' => ['REAL DE BOLEO II', 'REAL BOLEO II']],
    ];

    /**
     * @var array<int, string>
     */
    private array $profileReferenceTables = [
        'assembly_minutes',
        'billing_base_imports',
        'imported_resident_accounts',
        'quote_requests',
        'resident_receipts',
        'units',
        'users',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This command changes production condominium data. Re-run with --force.');

            return self::FAILURE;
        }

        try {
            $deletedEmptyProfiles = $this->deleteEmptyProfiles();
            $this->info("Deleted unnamed condominium profiles: {$deletedEmptyProfiles}");

            if ($this->option('renumber')) {
                $this->renumberCanonicalProfiles();
            } else {
                $this->resetSqliteSequence();
            }

            $this->line('Profiles:');

            foreach (CondominiumProfile::query()->orderBy('id')->get(['id', 'commercial_name']) as $profile) {
                $this->line("{$profile->id}: {$profile->commercial_name}");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function deleteEmptyProfiles(): int
    {
        $emptyProfileIds = CondominiumProfile::query()
            ->whereRaw("trim(coalesce(commercial_name, '')) = ''")
            ->pluck('id');

        if ($emptyProfileIds->isEmpty()) {
            return 0;
        }

        return CondominiumProfile::query()
            ->whereKey($emptyProfileIds->all())
            ->delete();
    }

    private function renumberCanonicalProfiles(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Renumbering condominium profile IDs is currently supported only for SQLite.');
        }

        $mapping = $this->canonicalProfileMapping();
        $this->assertNoUnexpectedNamedProfiles();
        $this->assertTargetIdsAreAvailable(array_keys($mapping));

        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::beginTransaction();

            foreach ($mapping as $oldId => $target) {
                $temporaryId = $this->temporaryProfileId($target['id']);

                DB::table('condominium_profiles')
                    ->where('id', $oldId)
                    ->update(['id' => $temporaryId]);
            }

            foreach ($mapping as $oldId => $target) {
                foreach ($this->profileReferenceTables as $table) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'condominium_profile_id')) {
                        continue;
                    }

                    DB::table($table)
                        ->where('condominium_profile_id', $oldId)
                        ->update(['condominium_profile_id' => $target['id']]);
                }

                DB::table('condominium_profiles')
                    ->where('id', $this->temporaryProfileId($target['id']))
                    ->update([
                        'id' => $target['id'],
                        'commercial_name' => $target['name'],
                    ]);
            }

            $this->resetSqliteSequence(3);
            $this->assertNoForeignKeyViolations();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->info('Canonical condominium profiles renumbered to IDs 1, 2, and 3.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function canonicalProfileMapping(): array
    {
        $mapping = [];

        foreach ($this->canonicalProfiles as $targetId => $profileDefinition) {
            $profile = CondominiumProfile::query()
                ->whereIn(DB::raw('upper(trim(commercial_name))'), $profileDefinition['aliases'])
                ->first(['id']);

            if (! $profile) {
                throw new RuntimeException("Missing canonical condominium profile: {$profileDefinition['name']}");
            }

            $mapping[(int) $profile->id] = [
                'id' => $targetId,
                'name' => $profileDefinition['name'],
            ];
        }

        if (count($mapping) !== count($this->canonicalProfiles)) {
            throw new RuntimeException('Canonical condominium profile aliases point to duplicate records.');
        }

        return $mapping;
    }

    private function assertNoUnexpectedNamedProfiles(): void
    {
        $aliases = collect($this->canonicalProfiles)
            ->flatMap(fn (array $profileDefinition): array => $profileDefinition['aliases'])
            ->all();
        $unexpectedProfiles = CondominiumProfile::query()
            ->whereRaw("trim(coalesce(commercial_name, '')) <> ''")
            ->whereNotIn(DB::raw('upper(trim(commercial_name))'), $aliases)
            ->orderBy('id')
            ->get(['id', 'commercial_name']);

        if ($unexpectedProfiles->isEmpty()) {
            return;
        }

        $summary = $unexpectedProfiles
            ->map(fn (CondominiumProfile $profile): string => "{$profile->id}: {$profile->commercial_name}")
            ->implode(', ');

        throw new RuntimeException("Unexpected named condominium profiles found: {$summary}");
    }

    /**
     * @param  array<int, int>  $sourceIds
     */
    private function assertTargetIdsAreAvailable(array $sourceIds): void
    {
        $targetIds = array_keys($this->canonicalProfiles);
        $blockingProfiles = CondominiumProfile::query()
            ->whereIn('id', $targetIds)
            ->whereNotIn('id', $sourceIds)
            ->orderBy('id')
            ->get(['id', 'commercial_name']);

        if ($blockingProfiles->isEmpty()) {
            return;
        }

        $summary = $blockingProfiles
            ->map(fn (CondominiumProfile $profile): string => "{$profile->id}: {$profile->commercial_name}")
            ->implode(', ');

        throw new RuntimeException("Cannot renumber because target IDs are occupied: {$summary}");
    }

    private function resetSqliteSequence(?int $sequence = null): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || ! Schema::hasTable('sqlite_sequence')) {
            return;
        }

        $sequence ??= (int) (CondominiumProfile::query()->max('id') ?? 0);

        DB::table('sqlite_sequence')->updateOrInsert(
            ['name' => 'condominium_profiles'],
            ['seq' => $sequence]
        );
    }

    private function assertNoForeignKeyViolations(): void
    {
        $violations = DB::select('PRAGMA foreign_key_check');

        if ($violations !== []) {
            throw new RuntimeException('Foreign key check failed after renumbering condominium profiles.');
        }
    }

    private function temporaryProfileId(int $targetId): int
    {
        return -1000 - $targetId;
    }
}
