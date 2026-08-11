<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;
use ZipArchive;

class DatabaseBackupCommandTest extends TestCase
{
    public function test_it_creates_a_sqlite_backup_archive(): void
    {
        $databasePath = storage_path('framework/testing/backup-source.sqlite');
        $backupDirectory = storage_path('framework/testing/database-backups');
        $extractDirectory = storage_path('framework/testing/database-backup-extract');

        File::delete($databasePath);
        File::deleteDirectory($backupDirectory);
        File::deleteDirectory($extractDirectory);
        File::ensureDirectoryExists(dirname($databasePath));
        touch($databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);

        DB::purge('sqlite');
        DB::connection('sqlite')->statement('CREATE TABLE sample_residents (id integer primary key, name varchar not null)');
        DB::connection('sqlite')->table('sample_residents')->insert(['name' => 'Boleo']);

        $this->artisan('db:backup', [
            '--connection' => 'sqlite',
            '--path' => $backupDirectory,
            '--filename' => 'test-backup',
            '--keep' => '0',
        ])->assertExitCode(0);

        $archivePath = $backupDirectory.DIRECTORY_SEPARATOR.'test-backup.zip';
        $this->assertFileExists($archivePath);

        File::ensureDirectoryExists($extractDirectory);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivePath));

        $sqliteName = null;
        $hasManifest = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false) {
                continue;
            }

            if (str_ends_with($name, '.sqlite')) {
                $sqliteName = $name;
            }

            if ($name === 'manifest.json') {
                $hasManifest = true;
            }
        }

        $this->assertNotNull($sqliteName);
        $this->assertTrue($hasManifest);
        $this->assertTrue($zip->extractTo($extractDirectory));
        $zip->close();

        $pdo = new PDO('sqlite:'.$extractDirectory.DIRECTORY_SEPARATOR.$sqliteName);
        $this->assertSame('Boleo', $pdo->query('select name from sample_residents limit 1')->fetchColumn());
    }
}
