<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\PhoneUsageLog;
use App\Models\Task;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tz = UserDateTime::resolveTimezone($user);
        $date = $request->input('date', $user->localTodayDate());

        $dailyPlan = DailyPlan::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        if (! $dailyPlan) {
            $dailyPlan = DailyPlan::create([
                'user_id' => $user->id,
                'date' => $date,
                'fallback_plan' => '',
                'personal_recovery_notes' => '',
                'notes' => '',
            ]);
        }

        $dailyPlan->load([
            'planTasks.task.category',
            'planTasks.task.project',
            'planTasks.task.checklists',
            'planTasks.task.tags',
        ]);

        // Planned tasks for this day
        $plannedTasks = $dailyPlan->planTasks->map(function ($pt) {
            $task = $pt->task;
            if ($task) {
                $task->is_priority = $pt->is_priority;
                $task->plan_task_id = $pt->id;
                $task->plan_sort_order = $pt->sort_order;
                $task->scheduled_start_time = $pt->scheduled_start_time;
                $task->scheduled_end_time = $pt->scheduled_end_time;
            }

            return $task;
        })->filter()->sortBy('plan_sort_order')->values();

        $plannedTaskIds = $plannedTasks->pluck('id')->toArray();

        // Tasks due on this date that are NOT yet in the daily plan
        $unplannedDueTasks = Task::with(['category', 'project', 'tags'])
            ->where('user_id', $user->id)
            ->where('due_date', $date)
            ->whereNotIn('id', $plannedTaskIds)
            ->orderByPriority()
            ->get();

        // General available active tasks for quick adding
        $candidateTasks = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->whereNotIn('id', $plannedTaskIds)
            ->orderBy('due_date', 'asc')
            ->orderByPriority()
            ->take(20)
            ->get();

        $dayLocal = Carbon::createFromFormat('Y-m-d', $date, $tz);

        // Fixed commitments for this date in user's timezone
        $fixedCommitments = CalendarEvent::where('user_id', $user->id)
            ->whereBetween('start_time', [
                $dayLocal->copy()->startOfDay()->utc(),
                $dayLocal->copy()->endOfDay()->utc(),
            ])
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function (CalendarEvent $evt) use ($tz): CalendarEvent {
                if ($evt->start_time) {
                    $evt->local_start_hm = $evt->start_time->copy()->setTimezone($tz)->format('H:i');
                }
                if ($evt->end_time) {
                    $evt->local_end_hm = $evt->end_time->copy()->setTimezone($tz)->format('H:i');
                }

                return $evt;
            });

        // Capacity & Overbooking Calculation (12-hour productive window 08:00-20:00 = 720m)
        $workdayMinutes = 720;
        $committedEventMinutes = (int) $fixedCommitments->sum(function (CalendarEvent $evt): int {
            if ($evt->all_day || ! $evt->start_time || ! $evt->end_time) {
                return 0;
            }

            return max(0, (int) $evt->start_time->diffInMinutes($evt->end_time, false));
        });

        $availableFreeMinutes = max(0, $workdayMinutes - $committedEventMinutes);
        $defaultTaskDuration = (int) ($user->setting?->default_task_duration ?? 30);

        $plannedTaskMinutes = (int) $plannedTasks
            ->filter(fn (Task $t): bool => $t->status !== 'completed')
            ->sum(fn (Task $t): int => max(5, (int) ($t->estimated_duration ?: $defaultTaskDuration)));

        $overbookedByMinutes = max(0, $plannedTaskMinutes - $availableFreeMinutes);
        $isOverbooked = $plannedTaskMinutes > $availableFreeMinutes;
        $utilizationPercent = $availableFreeMinutes > 0
            ? min(999, (int) round(($plannedTaskMinutes / $availableFreeMinutes) * 100))
            : ($plannedTaskMinutes > 0 ? 100 : 0);

        // Phone usage for this date
        $phoneUsage = PhoneUsageLog::where('user_id', $user->id)
            ->where('date', $date)
            ->first();

        return Inertia::render('MyDay/Index', [
            'date' => $date,
            'formattedDate' => $dayLocal->isoFormat('dddd, MMMM D, YYYY'),
            'dailyPlan' => $dailyPlan,
            'plannedTasks' => $plannedTasks,
            'unplannedDueTasks' => $unplannedDueTasks,
            'candidateTasks' => $candidateTasks,
            'fixedCommitments' => $fixedCommitments,
            'capacity' => [
                'workday_minutes' => $workdayMinutes,
                'committed_event_minutes' => $committedEventMinutes,
                'available_free_minutes' => $availableFreeMinutes,
                'planned_task_minutes' => $plannedTaskMinutes,
                'is_overbooked' => $isOverbooked,
                'overbooked_by_minutes' => $overbookedByMinutes,
                'utilization_percent' => $utilizationPercent,
            ],
            'phoneUsage' => $phoneUsage,
            'targetPhoneMinutes' => $user->setting?->phone_usage_target_minutes ?? 60,
        ]);
    }

    public function updatePlan(Request $request, DailyPlan $dailyPlan): RedirectResponse
    {
        if ($dailyPlan->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'fallback_plan' => 'nullable|string',
            'personal_recovery_notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $dailyPlan->update($validated);

        return back()->with('success', 'Plan details updated.');
    }

    public function addTask(Request $request, DailyPlan $dailyPlan): RedirectResponse
    {
        if ($dailyPlan->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'task_id' => [
                'required',
                'integer',
                Rule::exists('tasks', 'id')->where('user_id', $request->user()->id),
            ],
            'is_priority' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_priority']) && $validated['is_priority']) {
            $currentPriorities = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
                ->where('is_priority', true)
                ->count();
            if ($currentPriorities >= 3) {
                return back()->with('error', 'You can only have up to 3 main priorities per day.');
            }
        }

        $maxSort = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)->max('sort_order') ?? 0;

        DailyPlanTask::firstOrCreate(
            ['daily_plan_id' => $dailyPlan->id, 'task_id' => $validated['task_id']],
            [
                'is_priority' => $validated['is_priority'] ?? false,
                'sort_order' => $maxSort + 1,
            ]
        );

        return back()->with('success', 'Task added to My Day.');
    }

    public function togglePriority(DailyPlan $dailyPlan, Task $task): RedirectResponse
    {
        if ($dailyPlan->user_id !== request()->user()->id) {
            abort(403);
        }

        $planTask = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
            ->where('task_id', $task->id)
            ->firstOrFail();

        if (! $planTask->is_priority) {
            $currentPriorities = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
                ->where('is_priority', true)
                ->count();
            if ($currentPriorities >= 3) {
                return back()->with('error', 'Maximum 3 priorities allowed.');
            }
            $planTask->update(['is_priority' => true]);
        } else {
            $planTask->update(['is_priority' => false]);
        }

        return back()->with('success', 'Priority updated.');
    }

    public function removeTask(DailyPlan $dailyPlan, Task $task): RedirectResponse
    {
        if ($dailyPlan->user_id !== request()->user()->id) {
            abort(403);
        }

        DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
            ->where('task_id', $task->id)
            ->delete();

        return back()->with('success', 'Task removed from My Day.');
    }

    public function reorder(Request $request, DailyPlan $dailyPlan): RedirectResponse
    {
        if ($dailyPlan->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => [
                'integer',
                Rule::exists('tasks', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        DB::transaction(function () use ($validated, $dailyPlan): void {
            foreach ($validated['order'] as $index => $taskId) {
                DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
                    ->where('task_id', $taskId)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return back()->with('success', 'Tasks reordered.');
    }

    public function updateTimeBlock(Request $request, DailyPlan $dailyPlan, Task $task): RedirectResponse
    {
        $user = $request->user();
        if ($dailyPlan->user_id !== $user->id || $task->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'scheduled_start_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'scheduled_end_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $planTask = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)
            ->where('task_id', $task->id)
            ->firstOrFail();

        $startHm = $validated['scheduled_start_time'] ?? null;
        $endHm = $validated['scheduled_end_time'] ?? null;

        if ($startHm !== null && $endHm === null) {
            $duration = max(15, (int) ($task->estimated_duration ?: ($user->setting?->default_task_duration ?? 30)));
            [$h, $m] = array_map('intval', explode(':', $startHm));
            $totalEndMinutes = min(23 * 60 + 59, ($h * 60) + $m + $duration);
            $endHm = sprintf('%02d:%02d', intdiv($totalEndMinutes, 60), $totalEndMinutes % 60);
        }

        if ($startHm === null) {
            $endHm = null;
        }

        $planTask->update([
            'scheduled_start_time' => $startHm,
            'scheduled_end_time' => $endHm,
        ]);

        return back()->with('success', $startHm ? "Task time-blocked ({$startHm} – {$endHm})." : 'Time block cleared.');
    }

    public function rescheduleTask(Request $request, Task $task): RedirectResponse
    {
        if ($task->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'due_date' => 'required|date',
        ]);

        $task->update(['due_date' => $validated['due_date']]);

        return back()->with('success', 'Task rescheduled.');
    }
}
