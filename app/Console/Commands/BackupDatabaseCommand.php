<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:backup-database
                            {--if-needed : Only create a scheduled backup if none was created in the last 24 hours}
                            {--trigger=scheduled : Trigger label for backup metadata (scheduled or manual)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a verified local SQLite database backup and prune expired snapshots';

    public function handle(DatabaseBackupService $backupService): int
    {
        $primaryUser = User::with('setting')->orderBy('id')->first();
        $setting = $primaryUser?->ensureSetting();
        $trigger = (string) ($this->option('trigger') ?: 'scheduled');
        $onlyIfNeeded = (bool) $this->option('if-needed');

        if ($trigger === 'scheduled' && $setting !== null && ! $setting->backup_enabled) {
            $this->info('Automated database backups are disabled in user settings.');

            return self::SUCCESS;
        }

        if ($onlyIfNeeded) {
            $existing = $backupService->listBackups();
            foreach ($existing as $item) {
                if (($item['trigger'] ?? '') !== 'scheduled') {
                    continue;
                }

                try {
                    $createdAt = Carbon::parse((string) $item['created_at']);
                    if ($createdAt->greaterThan(now()->subHours(23))) {
                        $this->info("Recent scheduled backup already exists ({$item['filename']}). Skipping.");

                        return self::SUCCESS;
                    }
                } catch (Throwable) {
                }
            }
        }

        try {
            $meta = $backupService->createBackup($trigger, $trigger === 'scheduled' ? $primaryUser : null);
            $retentionDays = $setting?->backup_retention_days ?? (int) config('operations.backups.default_retention_days', 14);
            $pruned = $backupService->pruneOldBackups($retentionDays);

            $this->info("Created verified SQLite backup: {$meta['filename']} ({$meta['size_human']}). Pruned {$pruned} expired backup(s).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
