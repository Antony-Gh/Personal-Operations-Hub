<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklist;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Task::with(['category', 'project', 'tags', 'checklists'])
            ->where('user_id', $user->id);

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('next_action', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('status', '!=', 'completed');
            } elseif ($status === 'completed') {
                $query->where('status', 'completed');
            } else {
                $query->where('status', $status);
            }
        }

        // Category filter
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Project filter
        if ($projectId = $request->input('project_id')) {
            $query->where('project_id', $projectId);
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Date view filter
        $dateFilter = $request->input('date_filter');
        $today = Carbon::today()->toDateString();
        if ($dateFilter === 'today') {
            $query->where('due_date', $today);
        } elseif ($dateFilter === 'overdue') {
            $query->where('due_date', '<', $today)->where('status', '!=', 'completed');
        } elseif ($dateFilter === 'upcoming') {
            $query->where('due_date', '>', $today)
                ->where('due_date', '<=', Carbon::today()->addDays(7)->toDateString())
                ->where('status', '!=', 'completed');
        } elseif ($dateFilter === 'unscheduled') {
            $query->whereNull('due_date');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'due_date');
        $sortDir = $request->input('sort_dir', 'asc');

        if ($sortBy === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END {$sortDir}");
        } elseif (in_array($sortBy, ['title', 'created_at', 'due_date'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('due_date', 'asc');
        }

        $tasks = $query->paginate(25)->withQueryString();

        $projects = Project::where('user_id', $user->id)
            ->active()
            ->orderBy('name')
            ->get();

        $tags = Tag::where('user_id', $user->id)->orderBy('name')->get();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only(['search', 'status', 'category_id', 'project_id', 'priority', 'date_filter', 'sort_by', 'sort_dir']),
            'projects' => $projects,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:not_started,in_progress,waiting,completed,deferred',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_duration' => 'nullable|integer|min:1',
            'actual_duration' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|max:100',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'checklists' => 'nullable|array',
            'checklists.*.title' => 'required|string|max:255',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {
            $task = Task::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'due_date' => $validated['due_date'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'actual_duration' => $validated['actual_duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'is_recurring' => $validated['is_recurring'] ?? false,
                'recurrence_rule' => $validated['recurrence_rule'] ?? null,
                'completed_at' => ($validated['status'] === 'completed') ? now() : null,
            ]);

            if (!empty($validated['tag_ids'])) {
                $task->tags()->sync($validated['tag_ids']);
            }

            if (!empty($validated['checklists'])) {
                foreach ($validated['checklists'] as $idx => $cl) {
                    TaskChecklist::create([
                        'task_id' => $task->id,
                        'title' => $cl['title'],
                        'is_completed' => false,
                        'sort_order' => $idx + 1,
                    ]);
                }
            }
        });

        return back()->with('success', 'Task created successfully.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'project_id' => 'nullable|exists:projects,id',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:not_started,in_progress,waiting,completed,deferred',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_duration' => 'nullable|integer|min:1',
            'actual_duration' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|max:100',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'checklists' => 'nullable|array',
            'checklists.*.id' => 'nullable|integer',
            'checklists.*.title' => 'required|string|max:255',
            'checklists.*.is_completed' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($task, $validated) {
            $statusChangedToCompleted = $validated['status'] === 'completed' && $task->status !== 'completed';
            $statusReopened = $validated['status'] !== 'completed' && $task->status === 'completed';

            $task->update([
                'category_id' => $validated['category_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'due_date' => $validated['due_date'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'actual_duration' => $validated['actual_duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'is_recurring' => $validated['is_recurring'] ?? false,
                'recurrence_rule' => $validated['recurrence_rule'] ?? null,
                'completed_at' => $statusChangedToCompleted ? now() : ($statusReopened ? null : $task->completed_at),
            ]);

            if (isset($validated['tag_ids'])) {
                $task->tags()->sync($validated['tag_ids']);
            }

            if (isset($validated['checklists'])) {
                $existingIds = [];
                foreach ($validated['checklists'] as $idx => $item) {
                    if (!empty($item['id'])) {
                        $cl = TaskChecklist::where('id', $item['id'])->where('task_id', $task->id)->first();
                        if ($cl) {
                            $cl->update([
                                'title' => $item['title'],
                                'is_completed' => $item['is_completed'] ?? false,
                                'sort_order' => $idx + 1,
                            ]);
                            $existingIds[] = $cl->id;
                        }
                    } else {
                        $newCl = TaskChecklist::create([
                            'task_id' => $task->id,
                            'title' => $item['title'],
                            'is_completed' => $item['is_completed'] ?? false,
                            'sort_order' => $idx + 1,
                        ]);
                        $existingIds[] = $newCl->id;
                    }
                }
                TaskChecklist::where('task_id', $task->id)->whereNotIn('id', $existingIds)->delete();
            }
        });

        return back()->with('success', 'Task updated successfully.');
    }

    public function toggleStatus(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if ($task->status === 'completed') {
            $task->reopen();
            $msg = 'Task reopened.';
        } else {
            $task->markAsCompleted();
            $msg = 'Task marked as completed.';
        }

        return back()->with('success', $msg);
    }

    public function duplicate(Task $task): RedirectResponse
    {
        $this->authorize('view', $task);

        DB::transaction(function () use ($task) {
            $clone = $task->replicate([
                'completed_at',
            ]);
            $clone->title = "{$task->title} (Copy)";
            $clone->status = 'not_started';
            $clone->save();

            $clone->tags()->sync($task->tags->pluck('id'));

            foreach ($task->checklists as $idx => $cl) {
                TaskChecklist::create([
                    'task_id' => $clone->id,
                    'title' => $cl->title,
                    'is_completed' => false,
                    'sort_order' => $idx + 1,
                ]);
            }
        });

        return back()->with('success', 'Task duplicated.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:tasks,id',
            'action' => 'required|string|in:complete,reopen,delete,set_priority,set_category',
            'priority' => 'nullable|in:high,medium,low',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $user = $request->user();
        $tasks = Task::where('user_id', $user->id)->whereIn('id', $validated['task_ids']);

        if ($validated['action'] === 'complete') {
            $tasks->update(['status' => 'completed', 'completed_at' => now()]);
        } elseif ($validated['action'] === 'reopen') {
            $tasks->update(['status' => 'in_progress', 'completed_at' => null]);
        } elseif ($validated['action'] === 'delete') {
            $tasks->delete();
        } elseif ($validated['action'] === 'set_priority' && !empty($validated['priority'])) {
            $tasks->update(['priority' => $validated['priority']]);
        } elseif ($validated['action'] === 'set_category') {
            $tasks->update(['category_id' => $validated['category_id'] ?? null]);
        }

        return back()->with('success', 'Tasks updated in bulk.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}

