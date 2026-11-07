<?php

namespace App\Console\Commands;

use App\Models\FocusSession;
use App\Models\Reminder;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class RunOperationsMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:maintenance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale operational records, expired backups, and abandoned focus timers';

    public function handle(DatabaseBackupService $backupService): int
    {
        $deletedNotifications = 0;
        if (Schema::hasTable('notifications')) {
            $deletedNotifications = DatabaseNotification::whereNotNull('read_at')
                ->where('read_at', '<', now()->subDays(60))
                ->delete();
        }

        $deletedReminders = 0;
        if (Schema::hasTable('reminders')) {
            $deletedReminders = Reminder::whereIn('status', ['cancelled', 'dismissed', 'sent'])
                ->where('updated_at', '<', now()->subDays(60))
                ->delete();
        }

        $closedSessions = 0;
        if (Schema::hasTable('focus_sessions')) {
            $abandoned = FocusSession::whereIn('status', ['running', 'paused'])
                ->where('updated_at', '<', now()->subHours(12))
                ->get();

            foreach ($abandoned as $session) {
                $maxSeconds = max(60, ((int) $session->planned_minutes) * 60);
                $clampedSeconds = min($maxSeconds, $session->currentElapsedSeconds());
                $session->update([
                    'status' => 'stopped',
                    'elapsed_seconds' => $clampedSeconds,
                    'ended_at' => now(),
                ]);
                $closedSessions++;
            }
        }

        $primaryUser = User::with('setting')->orderBy('id')->first();
        $retentionDays = $primaryUser?->setting?->backup_retention_days ?? (int) config('operations.backups.default_retention_days', 14);
        $prunedBackups = $backupService->pruneOldBackups($retentionDays);

        $this->info(
            "Maintenance complete: {$deletedNotifications} old read notification(s), "
            ."{$deletedReminders} old reminder(s), {$closedSessions} abandoned focus session(s), "
            ."{$prunedBackups} expired backup(s) cleaned."
        );

        return self::SUCCESS;
    }
}
