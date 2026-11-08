<?php

namespace App\Console\Commands;

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;

class OpsStatusCommand extends Command
{
    protected $signature = 'ops:status {--json : Emit machine-readable JSON for the Windows System Tray companion} {--user= : User ID to report for}';

    protected $description = 'Report live local Personal Operations Hub operational status';

    public function handle(): int
    {
        $userId = $this->option('user');
        $user = $userId
            ? User::with('setting')->find($userId)
            : User::with('setting')->first();

        if (! $user) {
            $payload = ['ok' => false, 'message' => 'No local user found.'];
            if ($this->option('json')) {
                $this->line((string) json_encode($payload));
            } else {
                $this->warn($payload['message']);
            }

            return self::SUCCESS;
        }

        $today = $user->localTodayDate();
        $dueTodayCount = Task::where('user_id', $user->id)->where('due_date', $today)->where('status', '!=', 'completed')->count();
        $overdueCount = Task::where('user_id', $user->id)->where('due_date', '<', $today)->where('status', '!=', 'completed')->count();
        $unreadNotifications = $user->unreadNotifications()->count();

        $activeSession = FocusSession::with('task:id,title')
            ->where('user_id', $user->id)
            ->whereIn('status', ['running', 'paused'])
            ->latest('id')
            ->first();

        $goodTotal = Habit::where('user_id', $user->id)->active()->count();
        $goodLogged = HabitCheckIn::where('user_id', $user->id)->where('date', $today)->count();
        $badTotal = BadHabit::where('user_id', $user->id)->active()->count();
        $badLogged = BadHabitCheckIn::where('user_id', $user->id)->where('date', $today)->count();

        $payload = [
            'ok' => true,
            'today' => $today,
            'due_today_count' => $dueTodayCount,
            'overdue_count' => $overdueCount,
            'unread_notifications' => $unreadNotifications,
            'unread_notifications_count' => $unreadNotifications,
            'unlogged_habits_count' => max(0, ($goodTotal - $goodLogged) + ($badTotal - $badLogged)),
            'active_focus' => $activeSession ? [
                'mode' => $activeSession->mode,
                'status' => $activeSession->status,
                'remaining_seconds' => $activeSession->remainingSeconds(),
                'task_title' => $activeSession->task?->title,
            ] : null,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Personal Operations Hub — {$today}");
        $this->line("  Due Today: {$dueTodayCount} | Overdue: {$overdueCount} | Unread Alerts: {$unreadNotifications}");
        $this->line("  Unlogged Habits Today: {$payload['unlogged_habits_count']}");

        return self::SUCCESS;
    }
}
