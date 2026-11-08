<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Support\UserDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TaskDependencyService
{
    /**
     * Validate ownership & cycle safety, sync prerequisites, and auto-move to 'waiting' if blocked.
     *
     * @param  array<int, int|string>  $rawDependsOnIds
     */
    public function syncDependencies(Task $task, array $rawDependsOnIds, User $user): void
    {
        if (! Schema::hasTable('task_dependencies')) {
            return;
        }

        $candidateIds = collect($rawDependsOnIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($candidateIds->contains($task->id)) {
            throw ValidationException::withMessages([
                'blocked_by_ids' => 'A task cannot depend on itself.',
            ]);
        }

        if ($candidateIds->isEmpty()) {
            $task->blockedByTasks()->detach();

            return;
        }

        $validTasks = Task::where('user_id', $user->id)
            ->whereIn('id', $candidateIds)
            ->get(['id', 'status']);

        if ($validTasks->count() !== $candidateIds->count()) {
            throw ValidationException::withMessages([
                'blocked_by_ids' => 'One or more selected prerequisite tasks are invalid.',
            ]);
        }

        foreach ($validTasks as $blocker) {
            if ($this->wouldCreateCycle($task->id, $blocker->id)) {
                throw ValidationException::withMessages([
                    'blocked_by_ids' => 'Circular dependency detected between prerequisite tasks.',
                ]);
            }
        }

        $task->blockedByTasks()->sync($validTasks->pluck('id')->all());

        $hasIncompleteBlocker = $validTasks->contains(fn (Task $b): bool => $b->status !== 'completed');
        if ($hasIncompleteBlocker && $task->status === 'not_started') {
            $task->update(['status' => 'waiting']);
        }
    }

    /**
     * Promote 'waiting' tasks to 'not_started' and notify the owner once their last blocker finishes.
     */
    public function handleTaskCompleted(Task $completedTask): int
    {
        if (! Schema::hasTable('task_dependencies')) {
            return 0;
        }

        $dependents = $completedTask->blockingTasks()
            ->with(['blockedByTasks:id,status', 'user.setting'])
            ->where('status', '!=', 'completed')
            ->get();

        $unblockedCount = 0;

        foreach ($dependents as $dependent) {
            $stillBlocked = $dependent->blockedByTasks->contains(
                fn (Task $b): bool => $b->id !== $completedTask->id && $b->status !== 'completed'
            );

            if ($stillBlocked) {
                continue;
            }

            if ($dependent->status === 'waiting') {
                $dependent->update(['status' => 'not_started']);
            }

            $unblockedCount++;

            $owner = $dependent->user;
            if ($owner) {
                $setting = $owner->ensureSetting();
                if ($setting->isNotificationCategoryEnabled('task_reminders') && ! UserDateTime::isInQuietHours($owner)) {
                    $dedupKey = "task_unblocked:{$dependent->id}:{$completedTask->id}:".now()->format('YmdHi');
                    $alreadySent = $owner->notifications()->where('data->dedup_key', $dedupKey)->exists();

                    if (! $alreadySent) {
                        $owner->notify(new OperationalNotification(
                            category: 'task_reminders',
                            title: "Task Unblocked: {$dependent->title}",
                            message: "Prerequisite \"{$completedTask->title}\" is complete — ready to start.",
                            actionUrl: '/tasks?search='.urlencode($dependent->title),
                            entityType: 'task',
                            entityId: $dependent->id,
                            dedupKey: $dedupKey,
                        ));
                    }
                }
            }
        }

        return $unblockedCount;
    }

    /**
     * Check whether $blockerId already transitively depends on $taskId.
     */
    private function wouldCreateCycle(int $taskId, int $blockerId): bool
    {
        if ($taskId === $blockerId) {
            return true;
        }

        $visited = [];
        $queue = [$blockerId];

        while (! empty($queue)) {
            $current = array_shift($queue);
            if ($current === $taskId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $upstreamIds = DB::table('task_dependencies')
                ->where('task_id', $current)
                ->pluck('depends_on_task_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach ($upstreamIds as $upId) {
                if ($upId === $taskId) {
                    return true;
                }
                if (! isset($visited[$upId])) {
                    $queue[] = $upId;
                }
            }
        }

        return false;
    }
}
