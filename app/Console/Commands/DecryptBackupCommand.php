<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DecryptBackupCommand extends Command
{
    protected $signature = 'ops:decrypt-backup {source : Path to encrypted .sqlite.enc file} {destination : Output .sqlite path} {--password= : Decryption passphrase}';

    protected $description = 'Decrypt and verify an AES-256 encrypted SQLite backup file';

    public function handle(DatabaseBackupService $backupService): int
    {
        $source = (string) $this->argument('source');
        $destination = (string) $this->argument('destination');
        $password = (string) ($this->option('password') ?? '');

        if ($password === '') {
            $password = (string) $this->secret('Backup decryption passphrase');
        }

        if ($password === '') {
            $this->error('A decryption passphrase is required.');

            return self::FAILURE;
        }

        try {
            $backupService->decryptBackupFile($source, $destination, $password);
            $integrity = $backupService->verifySqliteIntegrity($destination);
            $this->info("Backup decrypted successfully to {$destination} (SQLite integrity: {$integrity}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Decryption failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
