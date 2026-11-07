<?php

namespace App\Http\Controllers;

use App\Models\BadHabit;
use App\Models\Habit;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandPaletteController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->input('q', ''));

        $tasksQuery = Task::with(['category:id,name,color', 'project:id,name'])
            ->where('user_id', $user->id);

        $projectsQuery = Project::with('category:id,name,color')
            ->where('user_id', $user->id);

        $milestonesQuery = ProjectMilestone::with('project:id,name')
            ->whereHas('project', fn ($pq) => $pq->where('user_id', $user->id));

        $habitsQuery = Habit::where('user_id', $user->id)->active();
        $badHabitsQuery = BadHabit::where('user_id', $user->id)->active();

        if ($q !== '') {
            $tasksQuery->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('next_action', 'like', "%{$q}%");
            });

            $projectsQuery->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });

            $milestonesQuery->where('title', 'like', "%{$q}%");

            $habitsQuery->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            });

            $badHabitsQuery->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('trigger_cue', 'like', "%{$q}%")
                    ->orWhere('replacement_action', 'like', "%{$q}%");
            });
        } else {
            $tasksQuery->where('status', '!=', 'completed');
            $projectsQuery->active();
            $milestonesQuery->where('is_completed', false);
        }

        $tasks = $tasksQuery
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END ASC")
            ->orderByPriority()
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(fn (Task $t): array => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'status' => $t->status,
                'due_date' => $t->due_date?->toDateString(),
                'due_time' => $t->due_time,
                'category' => $t->category?->name,
                'project' => $t->project?->name,
                'url' => '/tasks?search='.urlencode($t->title),
            ]);

        $projects = $projectsQuery
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (Project $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'target_date' => $p->target_date?->toDateString(),
                'category' => $p->category?->name,
                'url' => "/projects/{$p->id}",
            ]);

        $milestones = $milestonesQuery
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(fn (ProjectMilestone $m): array => [
                'id' => $m->id,
                'title' => $m->title,
                'is_completed' => $m->is_completed,
                'due_date' => $m->due_date?->toDateString(),
                'project_id' => $m->project_id,
                'project_name' => $m->project?->name,
                'url' => "/projects/{$m->project_id}",
            ]);

        $habits = $habitsQuery
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(fn (Habit $h): array => [
                'id' => $h->id,
                'name' => $h->name,
                'category' => $h->category,
                'frequency' => $h->frequency,
                'url' => '/habits',
            ]);

        $badHabits = $badHabitsQuery
            ->orderBy('sort_order')
            ->limit(6)
            ->get()
            ->map(fn (BadHabit $bh): array => [
                'id' => $bh->id,
                'name' => $bh->name,
                'category' => $bh->category,
                'replacement_action' => $bh->replacement_action,
                'url' => '/bad-habits',
            ]);

        return response()->json([
            'query' => $q,
            'tasks' => $tasks,
            'projects' => $projects,
            'milestones' => $milestones,
            'habits' => $habits,
            'bad_habits' => $badHabits,
        ]);
    }
}
