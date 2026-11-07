<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Task;
use App\Services\HabitAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, HabitAnalyticsService $habitAnalytics): Response
    {
        $user = $request->user();
        $localToday = $user->localToday();
        $today = $localToday->toDateString();
        $next7 = $localToday->copy()->addDays(7)->toDateString();
        $dayStartUtc = $localToday->copy()->startOfDay()->utc();
        $dayEndUtc = $localToday->copy()->endOfDay()->utc();

        // 1. Today's Overview
        $dueTodayTasks = Task::with(['category', 'project', 'tags', 'checklists'])
            ->where('user_id', $user->id)
            ->where('due_date', $today)
            ->orderByPriority()
            ->get();

        $overdueTasks = Task::with(['category', 'project', 'tags'])
            ->where('user_id', $user->id)
            ->where('due_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->orderByPriority()
            ->get();

        $completedTodayTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->whereBetween('completed_at', [$dayStartUtc, $dayEndUtc])
            ->get();

        $upcomingDeadlines = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('due_date', '>', $today)
            ->where('due_date', '<=', $next7)
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->orderByPriority()
            ->get();

        // 2. Today's Priorities
        $dailyPlan = DailyPlan::with(['planTasks.task.category', 'planTasks.task.project'])
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $priorityTasks = collect();
        if ($dailyPlan) {
            $priorityTasks = $dailyPlan->planTasks
                ->where('is_priority', true)
                ->map(fn ($pt) => $pt->task)
                ->filter();
        }

        // Fallback: If no priorities set in daily plan, grab up to 3 high priority tasks due today or in progress
        if ($priorityTasks->isEmpty()) {
            $priorityTasks = Task::with(['category', 'project'])
                ->where('user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->where('priority', 'high')
                ->orderBy('due_date', 'asc')
                ->take(3)
                ->get();
        }

        // 3. Areas / Categories Overview
        $categories = $user->categories()
            ->active()
            ->ordered()
            ->withCount([
                'tasks as active_tasks_count' => fn ($q) => $q->where('status', '!=', 'completed'),
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
                'tasks as upcoming_deadlines_count' => fn ($q) => $q->where('due_date', '>=', $today)
                    ->where('due_date', '<=', $next7)
                    ->where('status', '!=', 'completed'),
            ])
            ->get()
            ->map(function ($cat) {
                $total = $cat->active_tasks_count + $cat->completed_tasks_count;
                $cat->progress_percent = $total > 0 ? round(($cat->completed_tasks_count / $total) * 100) : 0;

                return $cat;
            });

        // 4. Projects Overview
        $projects = Project::with(['category', 'milestones'])
            ->where('user_id', $user->id)
            ->active()
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->take(6)
            ->get()
            ->map(function ($proj) {
                $proj->progress_percent = $proj->total_tasks_count > 0
                    ? round(($proj->completed_tasks_count / $proj->total_tasks_count) * 100)
                    : 0;

                return $proj;
            });

        // 5. Personal Habits Check-ins for Today
        $habits = Habit::with('checkIns')
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $todayCheckIns = HabitCheckIn::where('user_id', $user->id)
            ->where('date', $today)
            ->get()
            ->keyBy('habit_id');

        $habitsSummary = $habits->map(function (Habit $habit) use ($todayCheckIns, $habitAnalytics, $user): array {
            $checkIn = $todayCheckIns->get($habit->id);
            $metrics = $habitAnalytics->calculateHabitMetrics($habit, $user, $habit->checkIns);

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'category' => $habit->category,
                'status' => $checkIn ? $checkIn->status : 'pending',
                'notes' => $checkIn ? $checkIn->notes : null,
                'current_streak' => $metrics['current_streak'],
                'best_streak' => $metrics['best_streak'],
            ];
        });

        // 6. Phone Usage Today
        $phoneUsage = PhoneUsageLog::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $targetMinutes = $user->setting?->phone_usage_target_minutes ?? 60;

        // 7. Focus & Reminder Summary Today
        $focusMinutesToday = (int) FocusSession::where('user_id', $user->id)
            ->where('mode', 'focus')
            ->whereBetween('started_at', [$dayStartUtc, $dayEndUtc])
            ->get()
            ->sum(fn (FocusSession $s): int => max((int) $s->logged_minutes, intdiv($s->currentElapsedSeconds(), 60)));

        $upcomingRemindersCount = Reminder::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'snoozed'])
            ->whereBetween('scheduled_at', [now()->utc(), $dayEndUtc])
            ->count();

        return Inertia::render('Dashboard', [
            'todayDate' => $localToday->isoFormat('dddd, MMMM D, YYYY'),
            'metrics' => [
                'dueTodayCount' => $dueTodayTasks->count(),
                'overdueCount' => $overdueTasks->count(),
                'completedTodayCount' => $completedTodayTasks->count(),
                'upcomingCount' => $upcomingDeadlines->count(),
                'focusMinutesToday' => $focusMinutesToday,
                'upcomingRemindersCount' => $upcomingRemindersCount,
            ],
            'priorities' => $priorityTasks->values(),
            'dueTodayTasks' => $dueTodayTasks,
            'overdueTasks' => $overdueTasks,
            'areas' => $categories,
            'projects' => $projects,
            'habits' => $habitsSummary,
            'phoneUsage' => [
                'minutes' => $phoneUsage?->minutes ?? 0,
                'target' => $targetMinutes,
                'notes' => $phoneUsage?->notes,
            ],
        ]);
    }
}
