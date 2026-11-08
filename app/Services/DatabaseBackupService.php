<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OperationalNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SQLite3;
use Throwable;

class DatabaseBackupService
{
    public function backupDirectory(): string
    {
        $configured = (string) config('operations.backups.directory', storage_path('app/backups'));

        if (! File::isDirectory($configured)) {
            File::makeDirectory($configured, 0755, true);
        }

        $gitignorePath = $configured.DIRECTORY_SEPARATOR.'.gitignore';
        if (! File::exists($gitignorePath)) {
            File::put($gitignorePath, "*\n!.gitignore\n");
        }

        $htaccessPath = $configured.DIRECTORY_SEPARATOR.'.htaccess';
        if (! File::exists($htaccessPath)) {
            File::put($htaccessPath, "Deny from all\n");
        }

        return $configured;
    }

    public function sourceDatabasePath(): string
    {
        $override = config('operations.backups.source_database');
        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        $configured = (string) config('database.connections.sqlite.database', database_path('database.sqlite'));

        if ($configured === ':memory:') {
            return database_path('database.sqlite');
        }

        return $configured;
    }

    /**
     * Create a transaction-safe SQLite snapshot with integrity verification and JSON metadata sidecar.
     *
     * @return array<string, mixed>
     */
    public function createBackup(string $trigger = 'manual', ?User $notifyUser = null): array
    {
        $sourcePath = $this->sourceDatabasePath();

        if (! File::exists($sourcePath)) {
            Log::error('Database backup failed: SQLite file does not exist.', ['path' => $sourcePath]);
            throw new RuntimeException("SQLite database file not found at {$sourcePath}.");
        }

        $dir = $this->backupDirectory();
        $safeTrigger = preg_replace('/[^a-z0-9_-]/i', '', $trigger) ?: 'manual';
        $timestamp = now()->format('Y-m-d-His');
        $suffix = substr(bin2hex(random_bytes(2)), 0, 4);
        $filename = "ops-hub-backup-{$timestamp}-{$safeTrigger}-{$suffix}.sqlite";
        $targetPath = $dir.DIRECTORY_SEPARATOR.$filename;

        try {
            $this->performSafeSqliteSnapshot($sourcePath, $targetPath);
            $integrity = $this->verifySqliteIntegrity($targetPath);

            if ($integrity !== 'ok') {
                File::delete($targetPath);
                throw new RuntimeException("Backup integrity check failed: {$integrity}");
            }

            $sizeBytes = (int) File::size($targetPath);
            $sha256 = hash_file('sha256', $targetPath) ?: '';
            $tablesCount = $this->countTables($targetPath);

            $metadata = [
                'filename' => $filename,
                'created_at' => now()->toIso8601String(),
                'size_bytes' => $sizeBytes,
                'size_human' => $this->formatBytes($sizeBytes),
                'sha256' => $sha256,
                'integrity_status' => 'ok',
                'trigger' => $safeTrigger,
                'tables_count' => $tablesCount,
                'sqlite_version' => SQLite3::version()['versionString'] ?? 'unknown',
                'mirrored_to' => null,
                'encrypted_mirror' => false,
            ];

            $mirrorOutcome = $this->mirrorToSecondaryFolderIfConfigured($targetPath, $filename, $notifyUser);
            if ($mirrorOutcome !== null) {
                $metadata['mirrored_to'] = $mirrorOutcome['mirrored_to'];
                $metadata['encrypted_mirror'] = $mirrorOutcome['encrypted_mirror'];
            }

            File::put(
                $this->metadataPathFor($targetPath),
                json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            Log::info('SQLite database backup created successfully.', $metadata);

            if ($notifyUser !== null && $safeTrigger === 'scheduled') {
                $setting = $notifyUser->ensureSetting();
                if ($setting->isNotificationCategoryEnabled('system_notifications')) {
                    $notifyUser->notify(new OperationalNotification(
                        category: 'system_notifications',
                        title: 'Daily Database Backup Completed',
                        message: "Verified SQLite snapshot saved ({$metadata['size_human']}).",
                        actionUrl: '/settings/data',
                        entityType: 'backup',
                        dedupKey: 'backup:'.now()->toDateString(),
                    ));
                }
            }

            return $metadata;
        } catch (Throwable $e) {
            if (File::exists($targetPath)) {
                File::delete($targetPath);
            }
            Log::error('SQLite database backup failed.', [
                'trigger' => $safeTrigger,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Database backup failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * List all available backups sorted newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        $dir = $this->backupDirectory();
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'*.sqlite') ?: [];

        $items = [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $metaPath = $this->metadataPathFor($filePath);
            $meta = null;

            if (File::exists($metaPath)) {
                $decoded = json_decode((string) File::get($metaPath), true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $sizeBytes = (int) File::size($filePath);
            $createdAt = $meta['created_at'] ?? Carbon::createFromTimestamp(File::lastModified($filePath))->toIso8601String();

            $items[] = [
                'filename' => $filename,
                'created_at' => $createdAt,
                'size_bytes' => $sizeBytes,
                'size_human' => $this->formatBytes($sizeBytes),
                'sha256' => $meta['sha256'] ?? '',
                'integrity_status' => $meta['integrity_status'] ?? 'ok',
                'trigger' => $meta['trigger'] ?? 'manual',
                'tables_count' => $meta['tables_count'] ?? null,
                'mirrored_to' => $meta['mirrored_to'] ?? null,
                'encrypted_mirror' => (bool) ($meta['encrypted_mirror'] ?? false),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $items;
    }

    /**
     * Prune backups older than the retention window in days.
     */
    public function pruneOldBackups(?int $retentionDays = null): int
    {
        $days = max(1, (int) ($retentionDays ?? config('operations.backups.default_retention_days', 14)));
        $cutoff = now()->subDays($days);
        $dir = $this->backupDirectory();
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'*.sqlite') ?: [];
        $pruned = 0;

        foreach ($files as $filePath) {
            $metaPath = $this->metadataPathFor($filePath);
            $fileTime = Carbon::createFromTimestamp(File::lastModified($filePath));

            if (File::exists($metaPath)) {
                $decoded = json_decode((string) File::get($metaPath), true);
                if (is_array($decoded) && ! empty($decoded['created_at'])) {
                    try {
                        $fileTime = Carbon::parse($decoded['created_at']);
                    } catch (Throwable) {
                    }
                }
            }

            if ($fileTime->lessThan($cutoff)) {
                File::delete($filePath);
                if (File::exists($metaPath)) {
                    File::delete($metaPath);
                }
                $pruned++;
            }
        }

        if ($pruned > 0) {
            Log::info("Pruned {$pruned} expired SQLite backup(s) older than {$days} days.");
        }

        return $pruned;
    }

    /**
     * Resolve and validate a backup file path safely within the backup directory.
     */
    public function resolveBackupPath(string $filename): string
    {
        $cleanName = basename($filename);
        if ($cleanName !== $filename || ! preg_match('/^[a-zA-Z0-9._-]+\.sqlite$/', $cleanName)) {
            throw new RuntimeException('Invalid backup filename.');
        }

        $fullPath = $this->backupDirectory().DIRECTORY_SEPARATOR.$cleanName;
        if (! File::exists($fullPath)) {
            throw new RuntimeException('Selected backup file does not exist.');
        }

        return $fullPath;
    }

    /**
     * Delete a single backup and its metadata sidecar.
     */
    public function deleteBackup(string $filename): void
    {
        $path = $this->resolveBackupPath($filename);
        $metaPath = $this->metadataPathFor($path);

        File::delete($path);
        if (File::exists($metaPath)) {
            File::delete($metaPath);
        }

        Log::info('Deleted SQLite backup file.', ['filename' => basename($path)]);
    }

    /**
     * Safely restore the active SQLite database from a verified backup after creating a pre-restore snapshot.
     *
     * @return array<string, mixed>
     */
    public function restoreFromBackup(string $filename): array
    {
        $backupPath = $this->resolveBackupPath($filename);

        $header = file_get_contents($backupPath, false, null, 0, 16);
        if ($header !== "SQLite format 3\0") {
            throw new RuntimeException('Backup file does not have a valid SQLite 3 header.');
        }

        $integrity = $this->verifySqliteIntegrity($backupPath);
        if ($integrity !== 'ok') {
            throw new RuntimeException("Cannot restore corrupt backup ({$integrity}).");
        }

        $safetySnapshot = $this->createBackup('pre_restore');
        $destinationPath = $this->sourceDatabasePath();

        $isUsingInMemoryConnection = (string) config('database.connections.sqlite.database') === ':memory:';

        try {
            if (! $isUsingInMemoryConnection) {
                DB::disconnect('sqlite');
            }

            $sourceDb = new SQLite3($backupPath, SQLITE3_OPEN_READONLY);
            $destDb = new SQLite3($destinationPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $destDb->busyTimeout(5000);

            $copied = $sourceDb->backup($destDb);
            $sourceDb->close();
            $destDb->close();

            if (! $copied) {
                throw new RuntimeException('SQLite online restore operation returned false.');
            }

            if (! $isUsingInMemoryConnection) {
                DB::reconnect('sqlite');
                Artisan::call('migrate', ['--force' => true]);
            }

            Log::warning('Database restored from local backup.', [
                'restored_from' => basename($backupPath),
                'pre_restore_snapshot' => $safetySnapshot['filename'],
            ]);

            return [
                'restored_from' => basename($backupPath),
                'pre_restore_snapshot' => $safetySnapshot['filename'],
            ];
        } catch (Throwable $e) {
            if (! $isUsingInMemoryConnection) {
                DB::reconnect('sqlite');
            }
            Log::error('Database restoration failed.', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Database restore failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function performSafeSqliteSnapshot(string $sourcePath, string $targetPath): void
    {
        $activeConfigPath = (string) config('database.connections.sqlite.database');

        if ($activeConfigPath !== ':memory:' && realpath($activeConfigPath) === realpath($sourcePath)) {
            try {
                $escapedTarget = str_replace("'", "''", $targetPath);
                DB::connection('sqlite')->getPdo()->exec("VACUUM INTO '{$escapedTarget}'");
                if (File::exists($targetPath) && File::size($targetPath) > 0) {
                    return;
                }
            } catch (Throwable) {
            }
        }

        $sourceDb = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $sourceDb->busyTimeout(5000);

        try {
            $escapedTarget = str_replace("'", "''", $targetPath);
            $vacuumed = @$sourceDb->exec("VACUUM INTO '{$escapedTarget}'");
            if ($vacuumed && File::exists($targetPath) && File::size($targetPath) > 0) {
                $sourceDb->close();

                return;
            }
        } catch (Throwable) {
        }

        $destDb = new SQLite3($targetPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $ok = $sourceDb->backup($destDb);
        $destDb->close();
        $sourceDb->close();

        if (! $ok || ! File::exists($targetPath)) {
            throw new RuntimeException('Failed to snapshot SQLite database.');
        }
    }

    public function verifySqliteIntegrity(string $sqlitePath): string
    {
        $db = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
        $result = (string) $db->querySingle('PRAGMA integrity_check;');
        $db->close();

        return strtolower(trim($result));
    }

    private function countTables(string $sqlitePath): int
    {
        $db = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
        $count = (int) $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
        $db->close();

        return $count;
    }

    private function metadataPathFor(string $sqlitePath): string
    {
        return $sqlitePath.'.json';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }

    /**
     * Encrypt a verified SQLite snapshot using AES-256-CBC + PBKDF2-SHA256 + HMAC-SHA256 authentication.
     *
     * Binary layout: "OPSHUBENC1" (10B) | salt (16B) | iv (16B) | hmac (32B) | ciphertext
     *
     * @return array{path: string, size_bytes: int}
     */
    public function encryptBackupFile(string $sourceSqlitePath, string $destEncPath, string $passphrase): array
    {
        if (! File::exists($sourceSqlitePath)) {
            throw new RuntimeException('Source SQLite snapshot does not exist.');
        }

        if ($passphrase === '') {
            throw new RuntimeException('Encryption passphrase cannot be empty.');
        }

        $plaintext = (string) File::get($sourceSqlitePath);
        $salt = random_bytes(16);
        $iv = random_bytes(16);
        $keyMaterial = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 64, true);
        $encKey = substr($keyMaterial, 0, 32);
        $macKey = substr($keyMaterial, 32, 32);

        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('OpenSSL AES-256-CBC encryption failed.');
        }

        $magic = 'OPSHUBENC1';
        $hmac = hash_hmac('sha256', $magic.$salt.$iv.$ciphertext, $macKey, true);
        $bundle = $magic.$salt.$iv.$hmac.$ciphertext;

        $destDir = dirname($destEncPath);
        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::put($destEncPath, $bundle);

        return [
            'path' => $destEncPath,
            'size_bytes' => strlen($bundle),
        ];
    }

    /**
     * Authenticate and decrypt an AES-256-CBC encrypted backup bundle back into a SQLite file.
     */
    public function decryptBackupFile(string $sourceEncPath, string $destSqlitePath, string $passphrase): void
    {
        if (! File::exists($sourceEncPath)) {
            throw new RuntimeException('Encrypted backup file not found.');
        }

        $bundle = (string) File::get($sourceEncPath);
        $headerLen = 10 + 16 + 16 + 32;

        if (strlen($bundle) <= $headerLen || substr($bundle, 0, 10) !== 'OPSHUBENC1') {
            throw new RuntimeException('Invalid encrypted backup file header.');
        }

        $magic = substr($bundle, 0, 10);
        $salt = substr($bundle, 10, 16);
        $iv = substr($bundle, 26, 16);
        $storedHmac = substr($bundle, 42, 32);
        $ciphertext = substr($bundle, 74);

        $keyMaterial = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 64, true);
        $encKey = substr($keyMaterial, 0, 32);
        $macKey = substr($keyMaterial, 32, 32);

        $computedHmac = hash_hmac('sha256', $magic.$salt.$iv.$ciphertext, $macKey, true);
        if (! hash_equals($storedHmac, $computedHmac)) {
            throw new RuntimeException('Backup authentication failed — wrong passphrase or corrupted archive.');
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false || substr($plaintext, 0, 16) !== "SQLite format 3\0") {
            throw new RuntimeException('Failed to decrypt valid SQLite payload.');
        }

        $destDir = dirname($destSqlitePath);
        if (! File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::put($destSqlitePath, $plaintext);
    }

    /**
     * Mirror a newly verified snapshot to the user's secondary backup folder (optionally AES-256 encrypted).
     *
     * @return array{mirrored_to: string, encrypted_mirror: bool}|null
     */
    private function mirrorToSecondaryFolderIfConfigured(string $sourceSqlitePath, string $filename, ?User $notifyUser = null): ?array
    {
        try {
            $owner = $notifyUser ?? User::with('setting')->first();
            $setting = $owner?->setting;
            if (! $setting || ! $setting->secondary_backup_enabled || empty($setting->secondary_backup_path)) {
                return null;
            }

            $secondaryDir = rtrim(trim((string) $setting->secondary_backup_path), '\\/');
            if ($secondaryDir === '') {
                return null;
            }

            if (! File::isDirectory($secondaryDir)) {
                File::makeDirectory($secondaryDir, 0755, true);
            }

            $passphrase = $setting->backup_encryption_enabled ? $setting->decryptedBackupEncryptionPassword() : null;

            if ($setting->backup_encryption_enabled && ! empty($passphrase)) {
                $encTarget = $secondaryDir.DIRECTORY_SEPARATOR.$filename.'.enc';
                $this->encryptBackupFile($sourceSqlitePath, $encTarget, $passphrase);

                return [
                    'mirrored_to' => $encTarget,
                    'encrypted_mirror' => true,
                ];
            }

            $plainTarget = $secondaryDir.DIRECTORY_SEPARATOR.$filename;
            File::copy($sourceSqlitePath, $plainTarget);

            return [
                'mirrored_to' => $plainTarget,
                'encrypted_mirror' => false,
            ];
        } catch (Throwable $e) {
            Log::warning('Secondary backup folder mirror skipped.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
