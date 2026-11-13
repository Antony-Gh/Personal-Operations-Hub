<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:restore-database
                            {filename? : Specific backup filename or path}
                            {--latest : Automatically select the most recent verified backup}
                            {--migrate : Run database migrations after restore}
                            {--no-migrate : Do not run migrations after restore}
                            {--passphrase= : Passphrase for encrypted (.enc) backup files}
                            {--force : Bypass interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely restore the active SQLite database from a verified backup archive';

    public function handle(DatabaseBackupService $backupService): int
    {
        $filename = (string) ($this->argument('filename') ?? '');
        $isLatest = (bool) $this->option('latest');
        $force = (bool) $this->option('force');

        $backups = $backupService->listBackups();

        if (empty($backups)) {
            $this->warn('No database backup archives found in backup storage directory.');

            return self::SUCCESS;
        }

        $selectedItem = null;

        if ($isLatest) {
            $selectedItem = $backups[0];
            $filename = (string) $selectedItem['filename'];
        } elseif ($filename !== '') {
            $baseName = basename($filename);
            foreach ($backups as $item) {
                if ($item['filename'] === $baseName || $item['filename'] === $filename) {
                    $selectedItem = $item;
                    break;
                }
            }

            if ($selectedItem === null) {
                $selectedItem = [
                    'filename' => $baseName,
                    'is_encrypted' => str_ends_with($baseName, '.enc'),
                    'size_human' => 'unknown',
                    'created_at' => 'unknown',
                    'trigger' => 'external',
                ];
            }
        } else {
            $this->info('Available Database Backups:');
            $rows = [];
            foreach ($backups as $index => $item) {
                $rows[] = [
                    $index + 1,
                    $item['filename'],
                    $item['size_human'],
                    $item['created_at'],
                    $item['trigger'],
                    ! empty($item['is_encrypted']) ? 'Yes (AES-256)' : 'No (Raw SQLite)',
                ];
            }

            $this->table(['#', 'Filename', 'Size', 'Created At', 'Trigger', 'Encrypted'], $rows);

            $choice = $this->ask('Select backup number to restore (or press Enter to cancel)');
            if ($choice === null || trim((string) $choice) === '' || ! ctype_digit(trim((string) $choice))) {
                $this->info('Restore cancelled.');

                return self::SUCCESS;
            }

            $index = ((int) trim((string) $choice)) - 1;
            if ($index < 0 || $index >= count($backups)) {
                $this->error('Invalid selection number.');

                return self::FAILURE;
            }

            $selectedItem = $backups[$index];
            $filename = (string) $selectedItem['filename'];
        }

        $isEncrypted = ! empty($selectedItem['is_encrypted']) || str_ends_with($filename, '.enc');
        $passphrase = (string) ($this->option('passphrase') ?? '');

        if ($isEncrypted && $passphrase === '') {
            $configuredKey = (string) config('operations.backups.encryption_key', '');
            if ($configuredKey !== '') {
                $passphrase = $configuredKey;
            } elseif (! $force) {
                $passphrase = (string) $this->secret('Enter backup decryption passphrase');
            }

            if ($passphrase === '') {
                $this->error('A decryption passphrase is required to restore this encrypted backup.');

                return self::FAILURE;
            }
        }

        $runMigrations = false;
        if ($this->option('migrate')) {
            $runMigrations = true;
        } elseif ($this->option('no-migrate')) {
            $runMigrations = false;
        } elseif (! $force) {
            $runMigrations = $this->confirm('Do you want to run pending database migrations after restore?', false);
        }

        if (! $force) {
            $this->newLine();
            $this->warn("WARNING: Restoring will overwrite all active database data with '{$filename}'.");
            $this->line('A pre-restore safety snapshot will be created automatically before restoring.');
            if (! $this->confirm('Are you sure you want to proceed?', false)) {
                $this->info('Restore cancelled by user.');

                return self::SUCCESS;
            }
        }

        $this->info("Restoring database from {$filename}...");

        try {
            if ($isEncrypted) {
                $result = $backupService->restoreFromEncryptedBackup($filename, $passphrase, $runMigrations);
            } else {
                $result = $backupService->restoreFromBackup($filename, $runMigrations);
            }

            $this->newLine();
            $this->info('Database restoration completed successfully!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Restored From', $result['restored_from']],
                    ['Pre-Restore Safety Snapshot', $result['pre_restore_snapshot']],
                    ['Migrations Executed', $result['migrations_run'] ? 'Yes' : 'No'],
                    ['Database Integrity Status', 'ok'],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Database restoration failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
