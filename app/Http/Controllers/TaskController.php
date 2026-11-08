<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Services\NaturalLanguageTaskParser;
use App\Services\RecurringTaskService;
use App\Services\TaskDependencyService;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $localToday = $user->localToday();
        $today = $localToday->toDateString();

        $query = Task::with(['category', 'project', 'tags', 'checklists', 'blockedByTasks:id,title,status,priority'])
            ->where('user_id', $user->id);

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
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
        if ($dateFilter === 'today') {
            $query->where('due_date', $today);
        } elseif ($dateFilter === 'overdue') {
            $query->where('due_date', '<', $today)->where('status', '!=', 'completed');
        } elseif ($dateFilter === 'upcoming') {
            $query->where('due_date', '>', $today)
                ->where('due_date', '<=', $localToday->copy()->addDays(7)->toDateString())
                ->where('status', '!=', 'completed');
        } elseif ($dateFilter === 'unscheduled') {
            $query->whereNull('due_date');
        }

        // Sorting
        $sortBy = (string) $request->input('sort_by', 'due_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'priority') {
            $query->orderByPriority($sortDir)->orderBy('due_date', 'asc');
        } elseif (in_array($sortBy, ['title', 'created_at', 'due_date'], true)) {
            $query->orderBy($sortBy, $sortDir);
            if ($sortBy === 'due_date') {
                $query->orderByPriority('asc');
            }
        } else {
            $query->orderBy('due_date', 'asc')->orderByPriority('asc');
        }

        $viewMode = $request->input('view') === 'kanban' ? 'kanban' : 'list';
        $kanbanTasks = (clone $query)->limit(250)->get();
        $tasks = $query->paginate(25)->withQueryString();

        $projects = Project::where('user_id', $user->id)
            ->active()
            ->orderBy('name')
            ->get();

        $tags = Tag::where('user_id', $user->id)->orderBy('name')->get();

        $dependencyCandidates = Task::where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title', 'status', 'project_id']);

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'kanbanTasks' => $kanbanTasks,
            'filters' => $request->only([
                'search',
                'status',
                'category_id',
                'project_id',
                'priority',
                'date_filter',
                'sort_by',
                'sort_dir',
                'view',
            ]),
            'viewMode' => $viewMode,
            'projects' => $projects,
            'tags' => $tags,
            'dependencyCandidates' => $dependencyCandidates,
        ]);
    }

    public function quickCapture(Request $request, NaturalLanguageTaskParser $parser): RedirectResponse
    {
        $validated = $request->validate([
            'input' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $setting = $user->ensureSetting();
        $parsed = $parser->parse($validated['input'], $user);

        $defaultPreset = $setting->default_task_reminder_preset ?: 'none';
        $dueDate = $parsed['due_date'] ?? $user->localTodayDate();

        $task = Task::create([
            'user_id' => $user->id,
            'category_id' => $parsed['category_id'],
            'project_id' => $parsed['project_id'],
            'title' => $parsed['title'],
            'priority' => $parsed['priority'] ?? ($setting->default_priority ?: 'medium'),
            'status' => 'not_started',
            'due_date' => $dueDate,
            'due_time' => $parsed['due_time'],
            'estimated_duration' => $parsed['estimated_duration'] ?? ($setting->default_task_duration ?: 30),
            'reminder_preset' => $defaultPreset,
        ]);

        return back()->with('success', "Captured task: \"{$task->title}\".");
    }

    public function store(Request $request, NaturalLanguageTaskParser $parser, TaskDependencyService $dependencyService): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateTaskPayload($request, isUpdate: false);

        if ($request->boolean('smart_parse')) {
            $parsed = $parser->parse($validated['title'], $user);
            $validated['title'] = $parsed['title'];
            if ($parsed['priority'] !== null) {
                $validated['priority'] = $parsed['priority'];
            }
            if ($parsed['category_id'] !== null && empty($validated['category_id'])) {
                $validated['category_id'] = $parsed['category_id'];
            }
            if ($parsed['project_id'] !== null && empty($validated['project_id'])) {
                $validated['project_id'] = $parsed['project_id'];
            }
            if ($parsed['due_date'] !== null) {
                $validated['due_date'] = $parsed['due_date'];
            }
            if ($parsed['due_time'] !== null && empty($validated['due_time'])) {
                $validated['due_time'] = $parsed['due_time'];
            }
            if ($parsed['estimated_duration'] !== null) {
                $validated['estimated_duration'] = $parsed['estimated_duration'];
            }
        }

        $remindAtUtc = null;
        if (! empty($validated['remind_at'])) {
            $tz = UserDateTime::resolveTimezone($user);
            $remindAtUtc = Carbon::parse($validated['remind_at'], $tz)->utc();
        }

        $defaultPreset = $user->setting?->default_task_reminder_preset ?: 'none';
        $preset = $validated['reminder_preset']
            ?? ((! empty($validated['due_date']) && $defaultPreset !== 'none') ? $defaultPreset : 'none');

        $isRecurring = (bool) ($validated['is_recurring'] ?? false);

        DB::transaction(function () use ($validated, $user, $remindAtUtc, $preset, $isRecurring, $dependencyService): void {
            $task = Task::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'priority' => $validated['priority'],
                'status' => $validated['status'],
                'due_date' => $validated['due_date'] ?? null,
                'due_time' => $validated['due_time'] ?? null,
                'reminder_preset' => $preset,
                'remind_at' => $remindAtUtc,
                'custom_reminder_times' => $validated['custom_reminder_times'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'actual_duration' => $validated['actual_duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'is_recurring' => $isRecurring,
                'recurrence_rule' => $isRecurring ? ($validated['recurrence_rule'] ?? 'daily') : null,
                'recurrence_interval' => $isRecurring ? max(1, (int) ($validated['recurrence_interval'] ?? 1)) : 1,
                'recurrence_end_type' => $isRecurring ? ($validated['recurrence_end_type'] ?? 'never') : 'never',
                'recurrence_end_date' => $isRecurring ? ($validated['recurrence_end_date'] ?? null) : null,
                'recurrence_max_occurrences' => $isRecurring ? ($validated['recurrence_max_occurrences'] ?? null) : null,
                'completed_at' => ($validated['status'] === 'completed') ? now() : null,
            ]);

            if (! empty($validated['tag_ids'])) {
                $task->tags()->sync($validated['tag_ids']);
            }

            if (! empty($validated['blocked_by_ids'])) {
                $dependencyService->syncDependencies($task, $validated['blocked_by_ids'], $user);
            }

            if (! empty($validated['checklists'])) {
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

    public function update(Request $request, Task $task, TaskDependencyService $dependencyService): RedirectResponse
    {
        $this->authorize('update', $task);
        $user = $request->user();
        $validated = $this->validateTaskPayload($request, isUpdate: true);

        $remindAtUtc = $task->remind_at;
        if (array_key_exists('remind_at', $validated)) {
            $tz = UserDateTime::resolveTimezone($user);
            $remindAtUtc = ! empty($validated['remind_at'])
                ? Carbon::parse($validated['remind_at'], $tz)->utc()
                : null;
        }

        $isRecurring = (bool) ($validated['is_recurring'] ?? false);

        DB::transaction(function () use ($task, $validated, $remindAtUtc, $isRecurring, $dependencyService, $user): void {
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
                'due_time' => array_key_exists('due_time', $validated) ? ($validated['due_time'] ?? null) : $task->due_time,
                'reminder_preset' => $validated['reminder_preset'] ?? ($task->reminder_preset ?: 'none'),
                'remind_at' => $remindAtUtc,
                'custom_reminder_times' => array_key_exists('custom_reminder_times', $validated)
                    ? ($validated['custom_reminder_times'] ?? null)
                    : $task->custom_reminder_times,
                'start_date' => $validated['start_date'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'actual_duration' => $validated['actual_duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'is_recurring' => $isRecurring,
                'recurrence_rule' => $isRecurring ? ($validated['recurrence_rule'] ?? $task->recurrence_rule ?? 'daily') : null,
                'recurrence_interval' => $isRecurring ? max(1, (int) ($validated['recurrence_interval'] ?? $task->recurrence_interval ?? 1)) : 1,
                'recurrence_end_type' => $isRecurring ? ($validated['recurrence_end_type'] ?? $task->recurrence_end_type ?? 'never') : 'never',
                'recurrence_end_date' => $isRecurring ? ($validated['recurrence_end_date'] ?? null) : null,
                'recurrence_max_occurrences' => $isRecurring ? ($validated['recurrence_max_occurrences'] ?? null) : null,
                'completed_at' => $statusChangedToCompleted ? now() : ($statusReopened ? null : $task->completed_at),
            ]);

            if (isset($validated['tag_ids'])) {
                $task->tags()->sync($validated['tag_ids']);
            }

            if (array_key_exists('blocked_by_ids', $validated)) {
                $dependencyService->syncDependencies($task, $validated['blocked_by_ids'] ?? [], $user);
            }

            if (isset($validated['checklists'])) {
                $existingIds = [];
                foreach ($validated['checklists'] as $idx => $item) {
                    if (! empty($item['id'])) {
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

            if ($statusChangedToCompleted) {
                $dependencyService->handleTaskCompleted($task);
                if ($task->is_recurring) {
                    app(RecurringTaskService::class)->generateNextOccurrence($task);
                }
            }
        });

        return back()->with('success', 'Task updated successfully.');
    }

    public function updateStatus(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => 'required|in:not_started,in_progress,waiting,completed,deferred',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'completed' && $task->status !== 'completed') {
            $task->markAsCompleted();
        } elseif ($newStatus !== 'completed') {
            $updates = [
                'status' => $newStatus,
                'completed_at' => null,
            ];
            if (isset($validated['sort_order'])) {
                $updates['sort_order'] = $validated['sort_order'];
            }
            $task->update($updates);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'task' => $task->fresh(['category', 'project', 'tags', 'checklists', 'blockedByTasks:id,title,status,priority']),
            ]);
        }

        return back()->with('success', 'Task status updated.');
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

        DB::transaction(function () use ($task): void {
            $clone = $task->replicate([
                'completed_at',
                'recurrence_parent_id',
                'recurrence_generated',
                'recurrence_occurrences_count',
                'actual_duration',
            ]);
            $clone->title = "{$task->title} (Copy)";
            $clone->status = 'not_started';
            $clone->recurrence_generated = false;
            $clone->recurrence_occurrences_count = 1;
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
        $user = $request->user();

        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => [
                'integer',
                Rule::exists('tasks', 'id')->where('user_id', $user->id),
            ],
            'action' => 'required|string|in:complete,reopen,delete,set_priority,set_category',
            'priority' => 'nullable|in:high,medium,low',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $user->id),
            ],
        ]);

        $tasksQuery = Task::where('user_id', $user->id)->whereIn('id', $validated['task_ids']);

        if ($validated['action'] === 'complete') {
            foreach ($tasksQuery->get() as $item) {
                if ($item->status !== 'completed') {
                    $item->markAsCompleted();
                }
            }
        } elseif ($validated['action'] === 'reopen') {
            foreach ($tasksQuery->get() as $item) {
                $item->reopen();
            }
        } elseif ($validated['action'] === 'delete') {
            foreach ($tasksQuery->get() as $item) {
                $item->delete();
            }
        } elseif ($validated['action'] === 'set_priority' && ! empty($validated['priority'])) {
            foreach ($tasksQuery->get() as $item) {
                $item->update(['priority' => $validated['priority']]);
            }
        } elseif ($validated['action'] === 'set_category') {
            $tasksQuery->update(['category_id' => $validated['category_id'] ?? null]);
        }

        return back()->with('success', 'Tasks updated in bulk.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTaskPayload(Request $request, bool $isUpdate): array
    {
        $user = $request->user();

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $user->id),
            ],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('user_id', $user->id),
            ],
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:not_started,in_progress,waiting,completed,deferred',
            'due_date' => 'nullable|date',
            'due_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'reminder_preset' => 'nullable|in:none,at_due,15m,30m,1h,1d,custom',
            'remind_at' => 'nullable|date',
            'custom_reminder_times' => 'nullable|array',
            'custom_reminder_times.*' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'estimated_duration' => 'nullable|integer|min:1',
            'actual_duration' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'next_action' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|max:100',
            'recurrence_interval' => 'nullable|integer|min:1|max:365',
            'recurrence_end_type' => 'nullable|in:never,on_date,after_occurrences',
            'recurrence_end_date' => 'nullable|date',
            'recurrence_max_occurrences' => 'nullable|integer|min:1|max:999',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $user->id),
            ],
            'blocked_by_ids' => 'nullable|array',
            'blocked_by_ids.*' => [
                'integer',
                Rule::exists('tasks', 'id')->where('user_id', $user->id),
            ],
            'smart_parse' => 'nullable|boolean',
            'checklists' => 'nullable|array',
            'checklists.*.title' => 'required|string|max:255',
        ];

        if ($isUpdate) {
            $rules['checklists.*.id'] = 'nullable|integer';
            $rules['checklists.*.is_completed'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        if (! empty($validated['due_time'])) {
            $validated['due_time'] = substr((string) $validated['due_time'], 0, 5);
        }

        return $validated;
    }
}
