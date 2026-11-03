<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Target month / date range
        $currentMonth = $request->input('month', Carbon::today()->format('Y-m'));
        $startOfMonth = Carbon::parse($currentMonth . '-01')->startOfMonth()->subDays(7);
        $endOfMonth = Carbon::parse($currentMonth . '-01')->endOfMonth()->addDays(7);

        $categoryId = $request->input('category_id');
        $projectId = $request->input('project_id');

        // 1. Calendar Events (fixed commitments)
        $eventsQuery = CalendarEvent::with('category')
            ->where('user_id', $user->id)
            ->whereBetween('start_time', [$startOfMonth, $endOfMonth]);

        if ($categoryId) {
            $eventsQuery->where('category_id', $categoryId);
        }

        $events = $eventsQuery->get()->map(function ($event) {
            return [
                'id' => 'event-' . $event->id,
                'real_id' => $event->id,
                'type' => 'event',
                'title' => $event->title,
                'start_time' => $event->start_time->toIso8601String(),
                'end_time' => $event->end_time->toIso8601String(),
                'date' => $event->start_time->toDateString(),
                'all_day' => $event->all_day,
                'location' => $event->location,
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

        $taskItems = $tasksQuery->get()->map(function ($task) {
            return [
                'id' => 'task-' . $task->id,
                'real_id' => $task->id,
                'type' => 'task',
                'title' => $task->title,
                'date' => $task->due_date ? Carbon::parse($task->due_date)->toDateString() : null,
                'priority' => $task->priority,
                'status' => $task->status,
                'is_completed' => $task->status === 'completed',
                'color' => $task->category?->color ?: '#3b82f6',
            ];
        });

        // 3. Project Milestones
        $milestones = ProjectMilestone::whereHas('project', function ($q) use ($user, $categoryId, $projectId) {
            $q->where('user_id', $user->id);
            if ($categoryId) $q->where('category_id', $categoryId);
            if ($projectId) $q->where('id', $projectId);
        })
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->with('project.category')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => 'milestone-' . $m->id,
                    'real_id' => $m->id,
                    'type' => 'milestone',
                    'title' => '[Milestone] ' . $m->title . ' (' . $m->project->name . ')',
                    'date' => $m->due_date ? Carbon::parse($m->due_date)->toDateString() : null,
                    'is_completed' => $m->is_completed,
                    'color' => '#10b981',
                ];
            });

        $projects = Project::where('user_id', $user->id)->active()->get();

        return Inertia::render('Calendar/Index', [
            'currentMonth' => $currentMonth,
            'items' => $events->concat($taskItems)->concat($milestones)->values(),
            'projects' => $projects,
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
            'category_id' => 'nullable|exists:categories,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'all_day' => 'nullable|boolean',
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
            'category_id' => 'nullable|exists:categories,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'all_day' => 'nullable|boolean',
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
