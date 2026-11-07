<?php

namespace App\Services;

use App\Models\Task;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTaskService
{
    /**
     * Generate the next occurrence of a completed recurring task if eligible.
     */
    public function generateNextOccurrence(Task $task): ?Task
    {
        if (! $task->is_recurring || empty($task->recurrence_rule)) {
            return null;
        }

        return DB::transaction(function () use ($task): ?Task {
            /** @var Task|null $locked */
            $locked = Task::withTrashed()->whereKey($task->id)->lockForUpdate()->first();

            if (! $locked || $locked->trashed() || ! $locked->is_recurring || empty($locked->recurrence_rule)) {
                return null;
            }

            if ($locked->recurrence_generated) {
                return null;
            }

            $existingChild = Task::withTrashed()
                ->where('recurrence_parent_id', $locked->id)
                ->first();

            if ($existingChild !== null) {
                $locked->forceFill(['recurrence_generated' => true])->saveQuietly();

                return null;
            }

            $currentOccurrence = max(1, (int) ($locked->recurrence_occurrences_count ?: 1));

            if (
                $locked->recurrence_end_type === 'after_occurrences'
                && $locked->recurrence_max_occurrences !== null
                && $currentOccurrence >= (int) $locked->recurrence_max_occurrences
            ) {
                $locked->forceFill(['recurrence_generated' => true])->saveQuietly();

                return null;
            }

            $user = $locked->user()->with('setting')->first();
            $tz = UserDateTime::resolveTimezone($user);
            $localToday = UserDateTime::todayForUser($user);

            $baseDueDate = $locked->due_date
                ? Carbon::createFromFormat('Y-m-d', $locked->due_date->toDateString(), $tz)->startOfDay()
                : $localToday->copy();

            $nextDueDate = $this->calculateNextDate(
                $baseDueDate,
                (string) $locked->recurrence_rule,
                max(1, (int) ($locked->recurrence_interval ?: 1))
            );

            if ($nextDueDate === null) {
                return null;
            }

            if ($locked->recurrence_end_type === 'on_date' && $locked->recurrence_end_date !== null) {
                $endLimit = Carbon::createFromFormat('Y-m-d', $locked->recurrence_end_date->toDateString(), $tz)->endOfDay();
                if ($nextDueDate->greaterThan($endLimit)) {
                    $locked->forceFill(['recurrence_generated' => true])->saveQuietly();

                    return null;
                }
            }

            $nextStartDate = null;
            if ($locked->start_date !== null && $locked->due_date !== null) {
                $leadDays = Carbon::createFromFormat('Y-m-d', $locked->start_date->toDateString(), $tz)
                    ->startOfDay()
                    ->diffInDays($baseDueDate, false);
                $nextStartDate = $nextDueDate->copy()->subDays($leadDays);
            } elseif ($locked->start_date !== null) {
                $baseStart = Carbon::createFromFormat('Y-m-d', $locked->start_date->toDateString(), $tz)->startOfDay();
                $nextStartDate = $this->calculateNextDate(
                    $baseStart,
                    (string) $locked->recurrence_rule,
                    max(1, (int) ($locked->recurrence_interval ?: 1))
                );
            }

            $nextRemindAt = null;
            if ($locked->reminder_preset === 'custom' && $locked->remind_at !== null) {
                $dayShift = $baseDueDate->diffInDays($nextDueDate, false);
                $nextRemindAt = $locked->remind_at->copy()->setTimezone($tz)->addDays($dayShift)->utc();
            }

            $locked->forceFill([
                'recurrence_generated' => true,
                'recurrence_occurrences_count' => $currentOccurrence,
            ])->saveQuietly();

            $nextTask = Task::create([
                'user_id' => $locked->user_id,
                'category_id' => $locked->category_id,
                'project_id' => $locked->project_id,
                'title' => $locked->title,
                'description' => $locked->description,
                'priority' => $locked->priority,
                'status' => 'not_started',
                'due_date' => $nextDueDate->toDateString(),
                'due_time' => $locked->due_time,
                'reminder_preset' => $locked->reminder_preset,
                'remind_at' => $nextRemindAt,
                'custom_reminder_times' => $locked->custom_reminder_times,
                'start_date' => $nextStartDate?->toDateString(),
                'estimated_duration' => $locked->estimated_duration,
                'actual_duration' => null,
                'notes' => $locked->notes,
                'next_action' => $locked->next_action,
                'sort_order' => $locked->sort_order,
                'is_recurring' => true,
                'recurrence_rule' => $locked->recurrence_rule,
                'recurrence_interval' => max(1, (int) ($locked->recurrence_interval ?: 1)),
                'recurrence_end_type' => $locked->recurrence_end_type ?: 'never',
                'recurrence_end_date' => $locked->recurrence_end_date?->toDateString(),
                'recurrence_max_occurrences' => $locked->recurrence_max_occurrences,
                'recurrence_occurrences_count' => $currentOccurrence + 1,
                'recurrence_parent_id' => $locked->id,
                'recurrence_generated' => false,
                'completed_at' => null,
            ]);

            $tagIds = $locked->tags()->pluck('tags.id')->all();
            if (! empty($tagIds)) {
                $nextTask->tags()->sync($tagIds);
            }

            foreach ($locked->checklists()->orderBy('sort_order')->get() as $idx => $checklistItem) {
                $nextTask->checklists()->create([
                    'title' => $checklistItem->title,
                    'is_completed' => false,
                    'sort_order' => $idx + 1,
                ]);
            }

            return $nextTask;
        });
    }

    /**
     * Catch-up scan for completed recurring tasks that have not yet spawned their next occurrence.
     */
    public function processPendingCompletedRecurringTasks(): int
    {
        $generatedCount = 0;

        $tasks = Task::where('is_recurring', true)
            ->whereNotNull('recurrence_rule')
            ->where('status', 'completed')
            ->where('recurrence_generated', false)
            ->get();

        foreach ($tasks as $task) {
            if ($this->generateNextOccurrence($task) !== null) {
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    /**
     * Calculate the next date in the task's local timezone with safe month-end and weekday handling.
     */
    public function calculateNextDate(Carbon $baseDate, string $rule, int $interval = 1): ?Carbon
    {
        $normalized = strtolower(trim($rule));
        $step = max(1, $interval);
        $next = $baseDate->copy()->startOfDay();

        if (preg_match('/^every_(\d+)_(day|days|week|weeks|month|months)$/', $normalized, $matches)) {
            $step = max(1, (int) $matches[1]);
            $unit = $matches[2];
            $normalized = str_starts_with($unit, 'day')
                ? 'daily'
                : (str_starts_with($unit, 'week') ? 'weekly' : 'monthly');
        }

        return match ($normalized) {
            'daily', 'custom_days' => $next->addDays($step),
            'weekdays' => $this->addWeekdays($next, $step),
            'weekly', 'custom_weeks' => $next->addWeeks($step),
            'monthly', 'custom_months' => $next->addMonthsNoOverflow($step),
            default => null,
        };
    }

    private function addWeekdays(Carbon $date, int $weekdaysToAdd = 1): Carbon
    {
        $cursor = $date->copy();
        $remaining = max(1, $weekdaysToAdd);

        while ($remaining > 0) {
            $cursor->addDay();
            if (! $cursor->isWeekend()) {
                $remaining--;
            }
        }

        return $cursor;
    }
}
