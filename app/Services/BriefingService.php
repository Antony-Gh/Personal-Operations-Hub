<?php

namespace App\Services;

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\CalendarEvent;
use App\Models\DailyPlan;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Support\UserDateTime;
use Carbon\Carbon;

class BriefingService
{
    public function __construct(
        public HabitAnalyticsService $habitAnalytics,
        public BadHabitAnalyticsService $badHabitAnalytics,
    ) {}

    /**
     * Assemble the complete Daily Briefing payload in the user's configured timezone.
     *
     * @return array<string, mixed>
     */
    public function buildDailyBriefing(User $user, ?string $dateStr = null): array
    {
        $tz = UserDateTime::resolveTimezone($user);
        $localDay = ($dateStr !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr))
            ? Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay()
            : UserDateTime::todayForUser($user);

        $todayStr = $localDay->toDateString();
        $next7Str = $localDay->copy()->addDays(7)->toDateString();

        $dueTodayTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('due_date', $todayStr)
            ->orderByPriority()
            ->get();

        $overdueTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('due_date', '<', $todayStr)
            ->where('status', '!=', 'completed')
            ->orderByPriority()
            ->orderBy('due_date')
            ->get();

        $dailyPlan = DailyPlan::with(['planTasks.task.category', 'planTasks.task.project'])
            ->where('user_id', $user->id)
            ->where('date', $todayStr)
            ->first();

        $priorities = collect();
        if ($dailyPlan) {
            $priorities = $dailyPlan->planTasks
                ->where('is_priority', true)
                ->map(fn ($pt) => $pt->task)
                ->filter()
                ->values();
        }

        if ($priorities->isEmpty()) {
            $priorities = Task::with(['category', 'project'])
                ->where('user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->where('priority', 'high')
                ->orderBy('due_date')
                ->take(3)
                ->get();
        }

        $upcomingDeadlines = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('due_date', '>', $todayStr)
            ->where('due_date', '<=', $next7Str)
            ->where('status', '!=', 'completed')
            ->orderBy('due_date')
            ->orderByPriority()
            ->take(10)
            ->get();

        $projects = Project::with(['category', 'milestones'])
            ->where('user_id', $user->id)
            ->active()
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->get()
            ->map(function (Project $p): Project {
                $p->progress_percent = $p->total_tasks_count > 0
                    ? (int) round(($p->completed_tasks_count / $p->total_tasks_count) * 100)
                    : 0;

                return $p;
            });

        $habits = Habit::where('user_id', $user->id)->active()->orderBy('sort_order')->get();
        $todayCheckIns = HabitCheckIn::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->get()
            ->keyBy('habit_id');

        $habitItems = $habits->map(function (Habit $habit) use ($todayCheckIns, $user): array {
            $ci = $todayCheckIns->get($habit->id);
            $metrics = $this->habitAnalytics->calculateHabitMetrics($habit, $user);

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'category' => $habit->category,
                'status' => $ci?->status ?? 'pending',
                'current_streak' => $metrics['current_streak'],
            ];
        });

        $badHabits = BadHabit::with('checkIns')->where('user_id', $user->id)->active()->orderBy('sort_order')->get();
        $todayBadCheckIns = BadHabitCheckIn::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->get()
            ->keyBy('bad_habit_id');

        $badHabitItems = $badHabits->map(function (BadHabit $bh) use ($todayBadCheckIns, $user): array {
            $ci = $todayBadCheckIns->get($bh->id);
            $metrics = $this->badHabitAnalytics->calculateMetrics($bh, $user, $bh->checkIns);

            return [
                'id' => $bh->id,
                'name' => $bh->name,
                'category' => $bh->category,
                'trigger_cue' => $bh->trigger_cue,
                'replacement_action' => $bh->replacement_action,
                'has_daily_limit' => $bh->has_daily_limit,
                'daily_limit_value' => $bh->daily_limit_value,
                'daily_limit_unit' => $bh->daily_limit_unit,
                'observed_value' => $ci?->observed_value,
                'status' => $ci?->status ?? 'pending',
                'current_clean_streak' => $metrics['current_clean_streak'],
            ];
        });

        $dayStartUtc = $localDay->copy()->startOfDay()->utc();
        $dayEndUtc = $localDay->copy()->endOfDay()->utc();

        $events = CalendarEvent::with('category')
            ->where('user_id', $user->id)
            ->whereBetween('start_time', [$dayStartUtc, $dayEndUtc])
            ->orderBy('start_time')
            ->get();

        $remindersToday = Reminder::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'snoozed'])
            ->whereBetween('scheduled_at', [$dayStartUtc, $dayEndUtc])
            ->orderBy('scheduled_at')
            ->take(10)
            ->get();

        return [
            'date' => $todayStr,
            'formatted_date' => $localDay->isoFormat('dddd, MMMM D, YYYY'),
            'timezone' => $tz,
            'due_today_tasks' => $dueTodayTasks,
            'priorities' => $priorities,
            'overdue_tasks' => $overdueTasks,
            'upcoming_deadlines' => $upcomingDeadlines,
            'projects' => $projects,
            'habits' => [
                'completed_count' => $habitItems->whereIn('status', ['completed', 'partial'])->count(),
                'total_count' => $habitItems->count(),
                'items' => $habitItems->values(),
            ],
            'bad_habits' => [
                'clean_count' => $badHabitItems->where('status', 'clean')->count(),
                'total_count' => $badHabitItems->count(),
                'items' => $badHabitItems->values(),
            ],
            'calendar_events' => $events,
            'reminders' => $remindersToday,
        ];
    }

    /**
     * Assemble the Weekly Digest payload in the user's configured timezone.
     *
     * @return array<string, mixed>
     */
    public function buildWeeklyDigest(User $user, ?string $referenceDateStr = null): array
    {
        $tz = UserDateTime::resolveTimezone($user);
        $anchor = ($referenceDateStr !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDateStr))
            ? Carbon::createFromFormat('Y-m-d', $referenceDateStr, $tz)->startOfDay()
            : UserDateTime::todayForUser($user);

        $weekStartDay = ($user->setting?->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;
        $weekStart = $anchor->copy()->startOfWeek($weekStartDay);
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $weekStartUtc = $weekStart->copy()->utc();
        $weekEndUtc = $weekEnd->copy()->utc();

        $completedTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStartUtc, $weekEndUtc])
            ->orderBy('completed_at', 'desc')
            ->get();

        $incompleteTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->where(function ($q) use ($weekEnd): void {
                $q->whereNull('due_date')
                    ->orWhere('due_date', '<=', $weekEnd->toDateString());
            })
            ->orderByPriority()
            ->take(15)
            ->get();

        $upcomingDeadlines = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->whereBetween('due_date', [
                $anchor->toDateString(),
                $anchor->copy()->addDays(7)->toDateString(),
            ])
            ->orderBy('due_date')
            ->orderByPriority()
            ->get();

        $focusSessions = FocusSession::where('user_id', $user->id)
            ->where('mode', 'focus')
            ->whereBetween('started_at', [$weekStartUtc, $weekEndUtc])
            ->get();

        $totalFocusMinutes = (int) $focusSessions->sum(fn (FocusSession $s): int => max((int) $s->logged_minutes, intdiv((int) $s->elapsed_seconds, 60)));
        $completedFocusSessionsCount = $focusSessions->where('status', 'completed')->count();

        $habits = Habit::where('user_id', $user->id)->active()->orderBy('sort_order')->get();
        $habitConsistency = $habits->map(function (Habit $h) use ($user): array {
            $metrics = $this->habitAnalytics->calculateHabitMetrics($h, $user);

            return [
                'id' => $h->id,
                'name' => $h->name,
                'weekly_consistency' => $metrics['weekly_consistency'],
                'current_streak' => $metrics['current_streak'],
                'best_streak' => $metrics['best_streak'],
            ];
        });

        $avgHabitConsistency = $habitConsistency->isNotEmpty()
            ? (int) round($habitConsistency->avg('weekly_consistency'))
            : 0;

        $badHabits = BadHabit::with('checkIns')->where('user_id', $user->id)->active()->orderBy('sort_order')->get();
        $badHabitConsistency = $badHabits->map(function (BadHabit $bh) use ($user): array {
            $metrics = $this->badHabitAnalytics->calculateMetrics($bh, $user, $bh->checkIns);

            return [
                'id' => $bh->id,
                'name' => $bh->name,
                'weekly_clean_rate' => $metrics['weekly_clean_rate'],
                'current_clean_streak' => $metrics['current_clean_streak'],
                'best_clean_streak' => $metrics['best_clean_streak'],
            ];
        });

        $avgBadHabitCleanRate = $badHabitConsistency->isNotEmpty()
            ? (int) round($badHabitConsistency->avg('weekly_clean_rate'))
            : 0;

        $projects = Project::with('category')
            ->where('user_id', $user->id)
            ->active()
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->get()
            ->map(function (Project $p): Project {
                $p->progress_percent = $p->total_tasks_count > 0
                    ? (int) round(($p->completed_tasks_count / $p->total_tasks_count) * 100)
                    : 0;

                return $p;
            });

        $completedMilestones = ProjectMilestone::with('project')
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_completed', true)
            ->whereBetween('completed_at', [$weekStartUtc, $weekEndUtc])
            ->get();

        $achievements = [];
        if ($completedTasks->count() > 0) {
            $achievements[] = "Completed {$completedTasks->count()} task(s) this week.";
        }
        if ($completedMilestones->count() > 0) {
            $achievements[] = "Reached {$completedMilestones->count()} project milestone(s).";
        }
        if ($totalFocusMinutes >= 60) {
            $hours = round($totalFocusMinutes / 60, 1);
            $achievements[] = "Logged {$hours} hours ({$totalFocusMinutes}m) of deep focus work.";
        } elseif ($totalFocusMinutes > 0) {
            $achievements[] = "Logged {$totalFocusMinutes} minutes of focused execution.";
        }
        $topStreakHabit = $habitConsistency->sortByDesc('current_streak')->first();
        if ($topStreakHabit && $topStreakHabit['current_streak'] >= 3) {
            $achievements[] = "Maintained a {$topStreakHabit['current_streak']}-day streak on \"{$topStreakHabit['name']}\".";
        }
        $topCleanBadHabit = $badHabitConsistency->sortByDesc('current_clean_streak')->first();
        if ($topCleanBadHabit && $topCleanBadHabit['current_clean_streak'] >= 3) {
            $achievements[] = "Held a {$topCleanBadHabit['current_clean_streak']}-day clean streak avoiding \"{$topCleanBadHabit['name']}\".";
        }
        if (empty($achievements)) {
            $achievements[] = 'Ready for a fresh, intentional week ahead.';
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'formatted_range' => $weekStart->format('M d').' – '.$weekEnd->format('M d, Y'),
            'completed_tasks' => $completedTasks,
            'incomplete_tasks' => $incompleteTasks,
            'upcoming_deadlines' => $upcomingDeadlines,
            'focus_summary' => [
                'total_minutes' => $totalFocusMinutes,
                'completed_sessions' => $completedFocusSessionsCount,
            ],
            'habit_summary' => [
                'average_consistency' => $avgHabitConsistency,
                'items' => $habitConsistency->values(),
            ],
            'bad_habit_summary' => [
                'average_clean_rate' => $avgBadHabitCleanRate,
                'items' => $badHabitConsistency->values(),
            ],
            'projects' => $projects,
            'achievements' => $achievements,
        ];
    }

    /**
     * Dispatch scheduled Daily Briefings and Weekly Digests respecting user timezones, quiet hours, and deduplication.
     *
     * @return array{daily_sent: int, weekly_sent: int}
     */
    public function dispatchScheduledBriefings(?Carbon $nowUtc = null): array
    {
        $referenceUtc = ($nowUtc ?? now())->copy()->utc();
        $dailySent = 0;
        $weeklySent = 0;

        $users = User::with('setting')->get();

        foreach ($users as $user) {
            $setting = $user->ensureSetting();
            $tz = UserDateTime::resolveTimezone($user);
            $localNow = $referenceUtc->copy()->setTimezone($tz);

            if (UserDateTime::isInQuietHours($user, $referenceUtc)) {
                continue;
            }

            if ($setting->daily_briefing_enabled && $setting->isNotificationCategoryEnabled('daily_briefing')) {
                $targetHm = substr($setting->daily_briefing_time ?: '07:30', 0, 5);
                $alreadySentToday = $setting->last_daily_briefing_at !== null
                    && $setting->last_daily_briefing_at->copy()->setTimezone($tz)->toDateString() === $localNow->toDateString();

                if (! $alreadySentToday && $localNow->format('H:i') >= $targetHm) {
                    if ($this->sendDailyBriefingNotification($user, $localNow->toDateString(), $referenceUtc)) {
                        $dailySent++;
                    }
                }
            }

            if ($setting->weekly_digest_enabled && $setting->isNotificationCategoryEnabled('weekly_digest')) {
                $targetDay = (int) ($setting->weekly_digest_day ?? 0);
                $targetHm = substr($setting->weekly_digest_time ?: '18:00', 0, 5);
                $weekStartDay = ($setting->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;
                $currentWeekStartStr = $localNow->copy()->startOfWeek($weekStartDay)->toDateString();

                $alreadySentThisWeek = false;
                if ($setting->last_weekly_digest_at !== null) {
                    $lastWeekStartStr = $setting->last_weekly_digest_at
                        ->copy()
                        ->setTimezone($tz)
                        ->startOfWeek($weekStartDay)
                        ->toDateString();
                    $alreadySentThisWeek = $lastWeekStartStr === $currentWeekStartStr;
                }

                if (! $alreadySentThisWeek && (int) $localNow->dayOfWeek === $targetDay && $localNow->format('H:i') >= $targetHm) {
                    if ($this->sendWeeklyDigestNotification($user, $localNow->toDateString(), $referenceUtc)) {
                        $weeklySent++;
                    }
                }
            }
        }

        return [
            'daily_sent' => $dailySent,
            'weekly_sent' => $weeklySent,
        ];
    }

    public function sendDailyBriefingNotification(User $user, ?string $dateStr = null, ?Carbon $referenceUtc = null): bool
    {
        $briefing = $this->buildDailyBriefing($user, $dateStr);
        $dedupKey = "daily_briefing:{$user->id}:{$briefing['date']}";

        if ($user->notifications()->where('data->dedup_key', $dedupKey)->exists()) {
            return false;
        }

        $dueCount = count($briefing['due_today_tasks']);
        $overdueCount = count($briefing['overdue_tasks']);
        $eventsCount = count($briefing['calendar_events']);
        $pendingHabits = max(0, $briefing['habits']['total_count'] - $briefing['habits']['completed_count']);

        $message = "{$dueCount} due today • {$overdueCount} overdue • {$eventsCount} event(s) • {$pendingHabits} habit(s) remaining.";

        $user->notify(new OperationalNotification(
            category: 'daily_briefing',
            title: "Morning Briefing — {$briefing['formatted_date']}",
            message: $message,
            actionUrl: '/briefings',
            entityType: 'briefing',
            dedupKey: $dedupKey,
        ));

        $user->ensureSetting()->update([
            'last_daily_briefing_at' => ($referenceUtc ?? now())->copy()->utc(),
        ]);

        return true;
    }

    public function sendWeeklyDigestNotification(User $user, ?string $dateStr = null, ?Carbon $referenceUtc = null): bool
    {
        $digest = $this->buildWeeklyDigest($user, $dateStr);
        $dedupKey = "weekly_digest:{$user->id}:{$digest['week_start']}";

        if ($user->notifications()->where('data->dedup_key', $dedupKey)->exists()) {
            return false;
        }

        $completedCount = count($digest['completed_tasks']);
        $focusMin = $digest['focus_summary']['total_minutes'];
        $habitAvg = $digest['habit_summary']['average_consistency'];

        $message = "{$completedCount} task(s) completed • {$focusMin}m focus logged • {$habitAvg}% habit consistency.";

        $user->notify(new OperationalNotification(
            category: 'weekly_digest',
            title: "Weekly Digest ({$digest['formatted_range']})",
            message: $message,
            actionUrl: '/briefings?tab=weekly',
            entityType: 'briefing',
            dedupKey: $dedupKey,
        ));

        $user->ensureSetting()->update([
            'last_weekly_digest_at' => ($referenceUtc ?? now())->copy()->utc(),
        ]);

        return true;
    }
}
