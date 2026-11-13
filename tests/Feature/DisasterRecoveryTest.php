<?php

use App\Models\User;
use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'poh-dr-test-'.uniqid();
    $this->backupDir = $this->tempDir.DIRECTORY_SEPARATOR.'backups';
    $this->sourceDbPath = $this->tempDir.DIRECTORY_SEPARATOR.'disaster_recovery.sqlite';

    File::makeDirectory($this->backupDir, 0755, true);

    $db = new SQLite3($this->sourceDbPath);
    $db->exec('CREATE TABLE operational_items (id INTEGER PRIMARY KEY, title TEXT, status TEXT);');
    $db->exec("INSERT INTO operational_items (title, status) VALUES ('Server Migration', 'pending');");
    $db->close();

    config([
        'operations.backups.directory' => $this->backupDir,
        'operations.backups.source_database' => $this->sourceDbPath,
        'operations.backups.default_retention_days' => 7,
    ]);
});

afterEach(function (): void {
    if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

it('creates a verified database backup via ops:backup-database with valid metadata and hash', function (): void {
    User::factory()->create();

    $exitCode = Artisan::call('ops:backup-database', ['--trigger' => 'manual']);
    expect($exitCode)->toBe(0);

    $service = app(DatabaseBackupService::class);
    $backups = $service->listBackups();

    expect($backups)->toHaveCount(1)
        ->and($backups[0]['integrity_status'])->toBe('ok')
        ->and($backups[0]['size_bytes'])->toBeGreaterThan(0)
        ->and($backups[0]['trigger'])->toBe('manual');

    $backupPath = $this->backupDir.DIRECTORY_SEPARATOR.$backups[0]['filename'];
    $metaPath = $backupPath.'.json';

    expect(File::exists($backupPath))->toBeTrue()
        ->and(File::exists($metaPath))->toBeTrue();

    $meta = json_decode((string) File::get($metaPath), true);
    expect($meta['sha256'])->toBe(hash_file('sha256', $backupPath));
});

it('restores database state from a verified backup archive via ops:restore-database --latest --force', function (): void {
    User::factory()->create();
    $service = app(DatabaseBackupService::class);

    // 1. Take initial backup of baseline state
    $service->createBackup('baseline');

    // 2. Mutate active database
    $db = new SQLite3($this->sourceDbPath);
    $db->exec("UPDATE operational_items SET status = 'corrupted_or_deleted' WHERE id = 1;");
    $db->exec("INSERT INTO operational_items (title, status) VALUES ('Unwanted Record', 'active');");
    $db->close();

    // Verify mutated state
    $checkDb = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $statusBefore = $checkDb->querySingle('SELECT status FROM operational_items WHERE id = 1;');
    $countBefore = (int) $checkDb->querySingle('SELECT COUNT(*) FROM operational_items;');
    $checkDb->close();
    expect($statusBefore)->toBe('corrupted_or_deleted')
        ->and($countBefore)->toBe(2);

    // 3. Restore using ops:restore-database --latest --force
    $exitCode = Artisan::call('ops:restore-database', [
        '--latest' => true,
        '--force' => true,
        '--no-migrate' => true,
    ]);

    expect($exitCode)->toBe(0);

    // 4. Verify data restored to baseline
    $restoredDb = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $statusAfter = $restoredDb->querySingle('SELECT status FROM operational_items WHERE id = 1;');
    $countAfter = (int) $restoredDb->querySingle('SELECT COUNT(*) FROM operational_items;');
    $restoredDb->close();

    expect($statusAfter)->toBe('pending')
        ->and($countAfter)->toBe(1);

    // 5. Verify a pre-restore safety snapshot was generated
    $allBackups = $service->listBackups();
    $preRestoreSnapshots = array_filter(
        $allBackups,
        fn (array $b): bool => str_contains((string) $b['filename'], 'pre_restore')
    );
    expect(count($preRestoreSnapshots))->toBeGreaterThanOrEqual(1);
});

it('restores from a specific filename via ops:restore-database {filename} --force', function (): void {
    $service = app(DatabaseBackupService::class);
    $backupMeta = $service->createBackup('snapshot_one');

    // Mutate database
    $db = new SQLite3($this->sourceDbPath);
    $db->exec("UPDATE operational_items SET title = 'Altered Title';");
    $db->close();

    $exitCode = Artisan::call('ops:restore-database', [
        'filename' => $backupMeta['filename'],
        '--force' => true,
        '--no-migrate' => true,
    ]);

    expect($exitCode)->toBe(0);

    $db = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $title = $db->querySingle('SELECT title FROM operational_items WHERE id = 1;');
    $db->close();

    expect($title)->toBe('Server Migration');
});

it('aborts and safely preserves active database when attempting to restore a corrupt backup', function (): void {
    $corruptPath = $this->backupDir.DIRECTORY_SEPARATOR.'corrupt-backup.sqlite';
    File::put($corruptPath, 'THIS_IS_NOT_A_VALID_SQLITE_DATABASE_HEADER');

    $exitCode = Artisan::call('ops:restore-database', [
        'filename' => 'corrupt-backup.sqlite',
        '--force' => true,
        '--no-migrate' => true,
    ]);

    expect($exitCode)->toBe(1);

    // Active database must remain completely intact
    $db = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $integrity = $db->querySingle('PRAGMA integrity_check;');
    $title = $db->querySingle('SELECT title FROM operational_items WHERE id = 1;');
    $db->close();

    expect($integrity)->toBe('ok')
        ->and($title)->toBe('Server Migration');
});

it('encrypts backup and restores via ops:restore-database with passphrase', function (): void {
    $service = app(DatabaseBackupService::class);

    // 1. Create plain snapshot
    $plainSnapshot = $service->createBackup('pre_encryption');
    $plainPath = $this->backupDir.DIRECTORY_SEPARATOR.$plainSnapshot['filename'];

    // 2. Encrypt snapshot
    $encFilename = 'ops-hub-backup-encrypted.sqlite.enc';
    $encPath = $this->backupDir.DIRECTORY_SEPARATOR.$encFilename;
    $passphrase = 'StrongVaultPass#2026';
    $service->encryptBackupFile($plainPath, $encPath, $passphrase);

    expect(File::exists($encPath))->toBeTrue();

    // 3. Mutate active database
    $db = new SQLite3($this->sourceDbPath);
    $db->exec("UPDATE operational_items SET status = 'damaged_before_enc_restore';");
    $db->close();

    // 4. Attempt restore with wrong passphrase
    $failExit = Artisan::call('ops:restore-database', [
        'filename' => $encFilename,
        '--passphrase' => 'WrongPassword!',
        '--force' => true,
        '--no-migrate' => true,
    ]);
    expect($failExit)->toBe(1);

    // 5. Restore with correct passphrase
    $successExit = Artisan::call('ops:restore-database', [
        'filename' => $encFilename,
        '--passphrase' => $passphrase,
        '--force' => true,
        '--no-migrate' => true,
    ]);
    expect($successExit)->toBe(0);

    // 6. Verify restored data
    $db = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $status = $db->querySingle('SELECT status FROM operational_items WHERE id = 1;');
    $db->close();

    expect($status)->toBe('pending');
});

it('preserves manual and pre_restore snapshots when pruning expired backups', function (): void {
    $service = app(DatabaseBackupService::class);

    // Create three backups
    $b1 = $service->createBackup('scheduled');
    $b2 = $service->createBackup('manual');
    $b3 = $service->createBackup('pre_restore');

    // Artificially age the metadata files to 20 days ago
    $twentyDaysAgo = Carbon::now()->subDays(20)->toIso8601String();
    foreach ([$b1['filename'], $b2['filename'], $b3['filename']] as $fn) {
        $metaPath = $this->backupDir.DIRECTORY_SEPARATOR.$fn.'.json';
        $meta = json_decode((string) File::get($metaPath), true);
        $meta['created_at'] = $twentyDaysAgo;
        File::put($metaPath, json_encode($meta));
        touch($this->backupDir.DIRECTORY_SEPARATOR.$fn, time() - (20 * 86400));
    }

    // Prune with 7-day retention and preserveManualAndPreRestore = true
    $prunedCount = $service->pruneOldBackups(7, preserveManualAndPreRestore: true);
    expect($prunedCount)->toBe(1);

    // Verify scheduled was deleted, but manual and pre_restore were retained
    expect(File::exists($this->backupDir.DIRECTORY_SEPARATOR.$b1['filename']))->toBeFalse()
        ->and(File::exists($this->backupDir.DIRECTORY_SEPARATOR.$b2['filename']))->toBeTrue()
        ->and(File::exists($this->backupDir.DIRECTORY_SEPARATOR.$b3['filename']))->toBeTrue();
});

it('automatically rolls back active database to pre-restore snapshot if post-restore verification fails', function (): void {
    $customService = new class extends DatabaseBackupService
    {
        public int $integrityCallCount = 0;

        public function verifySqliteIntegrity(string $sqlitePath): string
        {
            $this->integrityCallCount++;
            // Allow check 1 (source backup pre-check), but simulate corruption on check 2 (destination post-restore check)
            if ($this->integrityCallCount === 2) {
                return 'simulated_destination_corruption';
            }

            return parent::verifySqliteIntegrity($sqlitePath);
        }
    };

    // 1. Take initial backup
    $backupMeta = $customService->createBackup('before_simulated_failure');

    // 2. Mutate active database to a specific state right before restore attempt
    $db = new SQLite3($this->sourceDbPath);
    $db->exec("UPDATE operational_items SET status = 'state_to_preserve_via_safety_snapshot';");
    $db->close();

    // 3. Attempt restore which must fail and trigger automatic rollback to safety snapshot
    expect(fn () => $customService->restoreFromBackup($backupMeta['filename']))
        ->toThrow(RuntimeException::class);

    // 4. Verify active database was safely recovered to the pre-restore snapshot state
    $db = new SQLite3($this->sourceDbPath, SQLITE3_OPEN_READONLY);
    $status = $db->querySingle('SELECT status FROM operational_items WHERE id = 1;');
    $integrity = $db->querySingle('PRAGMA integrity_check;');
    $db->close();

    expect($status)->toBe('state_to_preserve_via_safety_snapshot')
        ->and($integrity)->toBe('ok');
});
