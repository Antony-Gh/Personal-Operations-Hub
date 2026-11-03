<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        // 1. Today's Overview
        $dueTodayTasks = Task::with(['category', 'project', 'tags', 'checklists'])
            ->where('user_id', $user->id)
            ->where('due_date', $today)
            ->orderBy('priority', 'desc')
            ->get();

        $overdueTasks = Task::with(['category', 'project', 'tags'])
            ->where('user_id', $user->id)
            ->where('due_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->get();

        $completedTodayTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->whereDate('completed_at', $today)
            ->get();

        $upcomingDeadlines = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('due_date', '>', $today)
            ->where('due_date', '<=', Carbon::today()->addDays(7)->toDateString())
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
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
                    ->where('due_date', '<=', Carbon::today()->addDays(7)->toDateString())
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
        $habits = Habit::where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $todayCheckIns = HabitCheckIn::where('user_id', $user->id)
            ->where('date', $today)
            ->get()
            ->keyBy('habit_id');

        $habitsSummary = $habits->map(function ($habit) use ($todayCheckIns) {
            $checkIn = $todayCheckIns->get($habit->id);
            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'category' => $habit->category,
                'status' => $checkIn ? $checkIn->status : 'pending', // completed, partial, not_completed, pending
                'notes' => $checkIn ? $checkIn->notes : null,
            ];
        });

        // 6. Phone Usage Today
        $phoneUsage = PhoneUsageLog::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $targetMinutes = $user->setting?->phone_usage_target_minutes ?? 60;

        return Inertia::render('Dashboard', [
            'todayDate' => Carbon::today()->isoFormat('dddd, MMMM D, YYYY'),
            'metrics' => [
                'dueTodayCount' => $dueTodayTasks->count(),
                'overdueCount' => $overdueTasks->count(),
                'completedTodayCount' => $completedTodayTasks->count(),
                'upcomingCount' => $upcomingDeadlines->count(),
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
