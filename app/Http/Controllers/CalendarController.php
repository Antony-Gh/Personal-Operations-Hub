<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tz = UserDateTime::resolveTimezone($user);
        $localToday = $user->localToday();

        // Target month / date range
        $currentMonth = $request->input('month', $localToday->format('Y-m'));
        $startOfMonth = Carbon::createFromFormat('Y-m-d', $currentMonth.'-01', $tz)->startOfMonth()->subDays(7);
        $endOfMonth = Carbon::createFromFormat('Y-m-d', $currentMonth.'-01', $tz)->endOfMonth()->addDays(7);

        $categoryId = $request->input('category_id');
        $projectId = $request->input('project_id');

        // 1. Calendar Events (fixed commitments)
        $eventsQuery = CalendarEvent::with('category')
            ->where('user_id', $user->id)
            ->whereBetween('start_time', [
                $startOfMonth->copy()->startOfDay()->utc(),
                $endOfMonth->copy()->endOfDay()->utc(),
            ]);

        if ($categoryId) {
            $eventsQuery->where('category_id', $categoryId);
        }

        $events = $eventsQuery->get()->map(function (CalendarEvent $event) use ($tz): array {
            return [
                'id' => 'event-'.$event->id,
                'real_id' => $event->id,
                'type' => 'event',
                'title' => $event->title,
                'start_time' => $event->start_time->toIso8601String(),
                'end_time' => $event->end_time->toIso8601String(),
                'date' => $event->start_time->copy()->setTimezone($tz)->toDateString(),
                'all_day' => $event->all_day,
                'location' => $event->location,
                'reminder_minutes' => $event->reminder_minutes,
                'color' => $event->color ?: ($event->category?->color ?: '#6366f1'),
            ];
        });

        // 2. Tasks with due dates
        $tasksQuery = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()]);

        if ($categoryId) {
            $tasksQuery->where('category_id', $categoryId);
        }
        if ($projectId) {
            $tasksQuery->where('project_id', $projectId);
        }

        $taskItems = $tasksQuery->orderByPriority()->get()->map(function (Task $task): array {
            return [
                'id' => 'task-'.$task->id,
                'real_id' => $task->id,
                'type' => 'task',
                'title' => $task->title,
                'date' => $task->due_date?->toDateString(),
                'due_time' => $task->due_time,
                'priority' => $task->priority,
                'status' => $task->status,
                'is_completed' => $task->status === 'completed',
                'color' => $task->category?->color ?: '#3b82f6',
            ];
        });

        // 3. Project Milestones
        $milestones = ProjectMilestone::whereHas('project', function ($q) use ($user, $categoryId, $projectId): void {
            $q->where('user_id', $user->id);
            if ($categoryId) {
                $q->where('category_id', $categoryId);
            }
            if ($projectId) {
                $q->where('id', $projectId);
            }
        })
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->with('project.category')
            ->get()
            ->map(function (ProjectMilestone $m): array {
                return [
                    'id' => 'milestone-'.$m->id,
                    'real_id' => $m->id,
                    'type' => 'milestone',
                    'title' => '[Milestone] '.$m->title.' ('.$m->project->name.')',
                    'date' => $m->due_date?->toDateString(),
                    'is_completed' => $m->is_completed,
                    'color' => '#10b981',
                ];
            });

        // 4. Project Target Deadlines
        $projectDeadlinesQuery = Project::with('category')
            ->where('user_id', $user->id)
            ->whereBetween('target_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()]);

        if ($categoryId) {
            $projectDeadlinesQuery->where('category_id', $categoryId);
        }
        if ($projectId) {
            $projectDeadlinesQuery->where('id', $projectId);
        }

        $projectItems = $projectDeadlinesQuery->get()->map(function (Project $p): array {
            return [
                'id' => 'project-'.$p->id,
                'real_id' => $p->id,
                'type' => 'project',
                'title' => '[Project] '.$p->name,
                'date' => $p->target_date?->toDateString(),
                'is_completed' => $p->status === 'completed',
                'color' => $p->category?->color ?: '#f59e0b',
            ];
        });

        $projects = Project::where('user_id', $user->id)->active()->get();

        $setting = $user->ensureSetting();
        $rawFeedToken = $setting->calendar_feed_enabled ? $setting->decryptedCalendarFeedToken() : null;
        $calendarFeedUrl = $rawFeedToken ? route('calendar.feed', ['token' => $rawFeedToken]) : null;

        return Inertia::render('Calendar/Index', [
            'currentMonth' => $currentMonth,
            'todayDate' => $localToday->toDateString(),
            'items' => $events->concat($taskItems)->concat($milestones)->concat($projectItems)->values(),
            'projects' => $projects,
            'calendarFeedUrl' => $calendarFeedUrl,
            'filters' => [
                'category_id' => $categoryId,
                'project_id' => $projectId,
                'month' => $currentMonth,
            ],
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $request->user()->id),
            ],
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'all_day' => 'nullable|boolean',
            'reminder_minutes' => 'nullable|integer|min:0|max:10080',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:50',
        ]);

        CalendarEvent::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'all_day' => $validated['all_day'] ?? false,
            'reminder_minutes' => $validated['reminder_minutes'] ?? null,
            'location' => $validated['location'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('success', 'Event scheduled.');
    }

    public function updateEvent(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $request->user()->id),
            ],
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'all_day' => 'nullable|boolean',
            'reminder_minutes' => 'nullable|integer|min:0|max:10080',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:50',
        ]);

        $event->update($validated);

        return back()->with('success', 'Event updated.');
    }

    public function destroyEvent(CalendarEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return back()->with('success', 'Event deleted.');
    }

    public function moveTask(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'due_date' => 'required|date',
        ]);

        $task->update(['due_date' => $validated['due_date']]);

        return back()->with('success', 'Task moved.');
    }
}
