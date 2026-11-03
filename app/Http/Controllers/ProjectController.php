<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Project::with(['category', 'milestones'])
            ->where('user_id', $user->id)
            ->withCount([
                'tasks as total_tasks_count',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
            ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $projects = $query->orderBy('name')->get()->map(function ($project) {
            $project->progress_percent = $project->total_tasks_count > 0
                ? round(($project->completed_tasks_count / $project->total_tasks_count) * 100)
                : 0;
            return $project;
        });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'category_id']),
            'categories' => $user->categories()->active()->ordered()->get(),
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'category',
            'milestones' => fn ($q) => $q->orderBy('sort_order'),
            'tasks' => fn ($q) => $q->with(['tags', 'checklists'])->orderBy('sort_order'),
        ]);

        $project->total_tasks_count = $project->tasks->count();
        $project->completed_tasks_count = $project->tasks->where('status', 'completed')->count();
        $project->progress_percent = $project->total_tasks_count > 0
            ? round(($project->completed_tasks_count / $project->total_tasks_count) * 100)
            : 0;

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,in_progress,on_hold,completed,archived',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'milestones' => 'nullable|array',
            'milestones.*.title' => 'required|string|max:255',
            'milestones.*.due_date' => 'nullable|date',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {
            $project = Project::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'start_date' => $validated['start_date'] ?? null,
                'target_date' => $validated['target_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['milestones'])) {
                foreach ($validated['milestones'] as $idx => $m) {
                    ProjectMilestone::create([
                        'project_id' => $project->id,
                        'title' => $m['title'],
                        'due_date' => $m['due_date'] ?? null,
                        'is_completed' => false,
                        'sort_order' => $idx + 1,
                    ]);
                }
            }
        });

        return back()->with('success', 'Project created successfully.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,in_progress,on_hold,completed,archived',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated successfully.');
    }

    public function storeMilestone(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $maxOrder = $project->milestones()->max('sort_order') ?? 0;

        ProjectMilestone::create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'due_date' => $validated['due_date'] ?? null,
            'is_completed' => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Milestone added.');
    }

    public function toggleMilestone(ProjectMilestone $milestone): RedirectResponse
    {
        $project = $milestone->project;
        $this->authorize('update', $project);

        $milestone->update([
            'is_completed' => !$milestone->is_completed,
            'completed_at' => !$milestone->is_completed ? now() : null,
        ]);

        return back()->with('success', 'Milestone status updated.');
    }

    public function destroyMilestone(ProjectMilestone $milestone): RedirectResponse
    {
        $project = $milestone->project;
        $this->authorize('update', $project);

        $milestone->delete();

        return back()->with('success', 'Milestone removed.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        DB::transaction(function () use ($project) {
            Task::where('project_id', $project->id)->update(['project_id' => null]);
            $project->delete();
        });

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }
}

