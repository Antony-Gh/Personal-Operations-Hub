<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\OperationalNotification;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReminderService
{
    /**
     * Synchronize all reminders for a Task when created, updated, completed, or deleted.
     */
    public function syncTaskReminders(Task $task): void
    {
        if (! Schema::hasTable('reminders')) {
            return;
        }

        if (
            $task->trashed()
            || in_array($task->status, ['completed', 'cancelled'], true)
            || ($task->due_date === null && $task->remind_at === null && empty($task->custom_reminder_times))
        ) {
            $this->cancelPendingRemindersFor($task);

            return;
        }

        $user = $task->user()->with('setting')->first();
        if (! $user) {
            return;
        }

        $specs = [];
        $dueDateStr = $task->due_date?->toDateString();
        $dueTimeStr = $task->due_time ?: '09:00';
        $isHighPriority = $task->priority === 'high';

        if ($dueDateStr !== null) {
            $dueLocal = UserDateTime::parseLocalDateTime($dueDateStr, $dueTimeStr, $user, '09:00');
            $preset = $task->reminder_preset ?: 'none';

            if ($preset !== 'none' && $preset !== 'custom') {
                $scheduledLocal = match ($preset) {
                    'at_due' => $dueLocal->copy(),
                    '15m' => $dueLocal->copy()->subMinutes(15),
                    '30m' => $dueLocal->copy()->subMinutes(30),
                    '1h' => $dueLocal->copy()->subHour(),
                    '1d' => $dueLocal->copy()->subDay(),
                    default => null,
                };

                if ($scheduledLocal !== null) {
                    $label = $this->presetLabel($preset);
                    $key = "preset:{$preset}:{$scheduledLocal->copy()->utc()->format('YmdHi')}";
                    $specs[$key] = [
                        'category' => 'task_reminders',
                        'title' => "Task Reminder: {$task->title}",
                        'message' => "{$label} — Due {$dueLocal->format('Y-m-d H:i')}",
                        'offset_label' => $label,
                        'is_critical' => $isHighPriority,
                        'scheduled_at' => $scheduledLocal->copy()->utc(),
                        'metadata' => [
                            'action_url' => '/tasks?search='.urlencode($task->title),
                            'due_date' => $dueDateStr,
                            'due_time' => $task->due_time,
                            'priority' => $task->priority,
                        ],
                    ];
                }
            }

            $overdueLocal = $task->due_time
                ? $dueLocal->copy()->addHour()
                : UserDateTime::parseLocalDateTime($dueDateStr, '09:00', $user, '09:00')->addDay();

            $overdueKey = "overdue:{$dueDateStr}:{$dueTimeStr}";
            $formattedTime = $task->formatted_due_time ?: $task->due_time;
            $specs[$overdueKey] = [
                'category' => 'overdue_tasks',
                'title' => "Overdue Task: {$task->title}",
                'message' => "Task deadline ({$dueDateStr}".($task->due_time ? " {$formattedTime}" : '').') has passed.',
                'offset_label' => 'Overdue',
                'is_critical' => $isHighPriority,
                'scheduled_at' => $overdueLocal->utc(),
                'metadata' => [
                    'action_url' => '/tasks?date_filter=overdue',
                    'due_date' => $dueDateStr,
                    'priority' => $task->priority,
                ],
            ];
        }

        if ($task->reminder_preset === 'custom' && $task->remind_at !== null) {
            $customUtc = $task->remind_at->copy()->utc();
            $customLocal = $customUtc->copy()->setTimezone(UserDateTime::resolveTimezone($user));
            $key = "custom:{$customUtc->format('YmdHi')}";
            $specs[$key] = [
                'category' => 'task_reminders',
                'title' => "Task Reminder: {$task->title}",
                'message' => "Scheduled reminder for {$customLocal->format('Y-m-d H:i')}",
                'offset_label' => 'Custom time',
                'is_critical' => $isHighPriority,
                'scheduled_at' => $customUtc,
                'metadata' => [
                    'action_url' => '/tasks?search='.urlencode($task->title),
                    'due_date' => $dueDateStr,
                    'priority' => $task->priority,
                ],
            ];
        }

        if (is_array($task->custom_reminder_times)) {
            foreach ($task->custom_reminder_times as $rawItem) {
                if (! is_string($rawItem) || trim($rawItem) === '') {
                    continue;
                }
                $trimmed = trim($rawItem);
                $extraLocal = null;
                $label = 'Additional reminder';

                if ($dueDateStr !== null && in_array($trimmed, ['at_due', '15m', '30m', '1h', '1d'], true)) {
                    $dueLocal = UserDateTime::parseLocalDateTime($dueDateStr, $dueTimeStr, $user, '09:00');
                    $extraLocal = match ($trimmed) {
                        'at_due' => $dueLocal->copy(),
                        '15m' => $dueLocal->copy()->subMinutes(15),
                        '30m' => $dueLocal->copy()->subMinutes(30),
                        '1h' => $dueLocal->copy()->subHour(),
                        '1d' => $dueLocal->copy()->subDay(),
                    };
                    $label = $this->presetLabel($trimmed);
                } else {
                    try {
                        $extraLocal = Carbon::parse($trimmed, UserDateTime::resolveTimezone($user));
                    } catch (Throwable) {
                        $extraLocal = null;
                    }
                }

                if ($extraLocal !== null) {
                    $extraUtc = $extraLocal->copy()->utc();
                    $key = "extra:{$extraUtc->format('YmdHi')}";
                    $specs[$key] = [
                        'category' => 'task_reminders',
                        'title' => "Task Reminder: {$task->title}",
                        'message' => "{$label} — {$extraLocal->format('Y-m-d H:i')}",
                        'offset_label' => $label,
                        'is_critical' => $isHighPriority,
                        'scheduled_at' => $extraUtc,
                        'metadata' => [
                            'action_url' => '/tasks?search='.urlencode($task->title),
                            'due_date' => $dueDateStr,
                            'priority' => $task->priority,
                        ],
                    ];
                }
            }
        }

        $this->reconcileEntityReminders($task, $user->id, $specs);
    }

    /**
     * Synchronize automatic countdown and overdue reminders for a Project.
     */
    public function syncProjectReminders(Project $project): void
    {
        if (! Schema::hasTable('reminders')) {
            return;
        }

        if (
            $project->trashed()
            || in_array($project->status, ['completed', 'archived', 'cancelled'], true)
            || ! $project->reminders_enabled
            || $project->target_date === null
        ) {
            $this->cancelPendingRemindersFor($project);

            return;
        }

        $user = $project->user()->with('setting')->first();
        if (! $user) {
            return;
        }

        $targetDateStr = $project->target_date->toDateString();
        $localToday = UserDateTime::todayForUser($user);
        $targetLocalDay = Carbon::createFromFormat('Y-m-d', $targetDateStr, UserDateTime::resolveTimezone($user))->startOfDay();

        $daysOffsets = $project->reminder_days;
        if (! is_array($daysOffsets) || empty($daysOffsets)) {
            $daysOffsets = $user->setting?->project_reminder_days ?? UserSetting::defaultProjectReminderDays();
        }

        $normalizedDays = collect($daysOffsets)
            ->map(fn (mixed $d): int => max(0, (int) $d))
            ->unique()
            ->sortDesc()
            ->values();

        $specs = [];

        foreach ($normalizedDays as $daysBefore) {
            $triggerDay = $targetLocalDay->copy()->subDays($daysBefore);

            if ($triggerDay->lessThan($localToday)) {
                continue;
            }

            $scheduledLocal = UserDateTime::parseLocalDateTime($triggerDay->toDateString(), '09:00', $user, '09:00');
            $label = $daysBefore === 0
                ? 'Due today'
                : ($daysBefore === 1 ? '1 day before' : "{$daysBefore} days before");

            $key = "project:{$targetDateStr}:minus_{$daysBefore}d";
            $specs[$key] = [
                'category' => 'project_deadlines',
                'title' => "Project Deadline: {$project->name}",
                'message' => "{$label} (Target: {$targetDateStr})",
                'offset_label' => $label,
                'is_critical' => $daysBefore <= 1,
                'scheduled_at' => $scheduledLocal->utc(),
                'metadata' => [
                    'action_url' => "/projects/{$project->id}",
                    'target_date' => $targetDateStr,
                ],
            ];
        }

        if ($project->overdue_reminders_enabled) {
            $overdueDay = $targetLocalDay->copy()->addDay();
            $scheduledOverdue = UserDateTime::parseLocalDateTime($overdueDay->toDateString(), '09:00', $user, '09:00');
            $key = "project:{$targetDateStr}:overdue";
            $specs[$key] = [
                'category' => 'project_deadlines',
                'title' => "Project Overdue: {$project->name}",
                'message' => "Target deadline ({$targetDateStr}) has passed while project is still active.",
                'offset_label' => 'Overdue',
                'is_critical' => true,
                'scheduled_at' => $scheduledOverdue->utc(),
                'metadata' => [
                    'action_url' => "/projects/{$project->id}",
                    'target_date' => $targetDateStr,
                ],
            ];
        }

        $this->reconcileEntityReminders($project, $user->id, $specs);
    }

    /**
     * Synchronize reminders for a ProjectMilestone.
     */
    public function syncMilestoneReminders(ProjectMilestone $milestone): void
    {
        if (! Schema::hasTable('reminders')) {
            return;
        }

        $project = $milestone->project()->withTrashed()->first();

        if (
            ! $project
            || $project->trashed()
            || in_array($project->status, ['completed', 'archived', 'cancelled'], true)
            || $milestone->is_completed
            || ! $milestone->reminders_enabled
            || $milestone->due_date === null
        ) {
            $this->cancelPendingRemindersFor($milestone);

            return;
        }

        $user = $project->user()->with('setting')->first();
        if (! $user) {
            return;
        }

        $dueDateStr = $milestone->due_date->toDateString();
        $dueTimeStr = $milestone->due_time ?: '09:00';
        $localToday = UserDateTime::todayForUser($user);
        $dueLocalDay = Carbon::createFromFormat('Y-m-d', $dueDateStr, UserDateTime::resolveTimezone($user))->startOfDay();

        $offsets = is_array($milestone->reminder_offsets) && ! empty($milestone->reminder_offsets)
            ? $milestone->reminder_offsets
            : [3, 1, 0];

        $normalizedDays = collect($offsets)
            ->map(fn (mixed $d): int => max(0, (int) $d))
            ->unique()
            ->sortDesc()
            ->values();

        $specs = [];

        foreach ($normalizedDays as $daysBefore) {
            $triggerDay = $dueLocalDay->copy()->subDays($daysBefore);

            if ($triggerDay->lessThan($localToday)) {
                continue;
            }

            $scheduledLocal = UserDateTime::parseLocalDateTime($triggerDay->toDateString(), $dueTimeStr, $user, '09:00');
            $label = $daysBefore === 0
                ? 'At milestone deadline'
                : ($daysBefore === 1 ? '1 day before' : "{$daysBefore} days before");

            $key = "milestone:{$dueDateStr}:{$dueTimeStr}:minus_{$daysBefore}d";
            $specs[$key] = [
                'category' => 'milestone_deadlines',
                'title' => "Milestone: {$milestone->title}",
                'message' => "{$project->name} — {$label} ({$dueDateStr})",
                'offset_label' => $label,
                'is_critical' => $daysBefore <= 1,
                'scheduled_at' => $scheduledLocal->utc(),
                'metadata' => [
                    'action_url' => "/projects/{$project->id}",
                    'project_id' => $project->id,
                    'due_date' => $dueDateStr,
                ],
            ];
        }

        $overdueTime = $milestone->due_time
            ? UserDateTime::parseLocalDateTime($dueDateStr, $dueTimeStr, $user, '09:00')->addHour()
            : UserDateTime::parseLocalDateTime($dueLocalDay->copy()->addDay()->toDateString(), '09:00', $user, '09:00');

        $specs["milestone:{$dueDateStr}:overdue"] = [
            'category' => 'milestone_deadlines',
            'title' => "Overdue Milestone: {$milestone->title}",
            'message' => "Milestone in {$project->name} passed its target date ({$dueDateStr}).",
            'offset_label' => 'Overdue',
            'is_critical' => true,
            'scheduled_at' => $overdueTime->utc(),
            'metadata' => [
                'action_url' => "/projects/{$project->id}",
                'project_id' => $project->id,
                'due_date' => $dueDateStr,
            ],
        ];

        $this->reconcileEntityReminders($milestone, $user->id, $specs);
    }

    /**
     * Send an immediate milestone completion notification when a milestone is marked complete.
     */
    public function notifyMilestoneCompleted(ProjectMilestone $milestone): void
    {
        $project = $milestone->project;
        if (! $project) {
            return;
        }

        $user = $project->user()->with('setting')->first();
        if (! $user) {
            return;
        }

        $setting = $user->ensureSetting();
        if (! $setting->isNotificationCategoryEnabled('milestone_deadlines')) {
            return;
        }

        if (UserDateTime::isInQuietHours($user)) {
            return;
        }

        $dedupKey = "milestone_completed:{$milestone->id}:".($milestone->completed_at?->timestamp ?? now()->timestamp);
        if ($this->notificationExists($user, $dedupKey)) {
            return;
        }

        $user->notify(new OperationalNotification(
            category: 'milestone_deadlines',
            title: "Milestone Completed: {$milestone->title}",
            message: "Stage gate completed in project \"{$project->name}\".",
            actionUrl: "/projects/{$project->id}",
            entityType: 'milestone',
            entityId: $milestone->id,
            reminderId: null,
            isCritical: false,
            dedupKey: $dedupKey,
        ));
    }

    /**
     * Cancel all pending or snoozed reminders attached to an entity.
     */
    public function cancelPendingRemindersFor(Model $entity): void
    {
        if (! Schema::hasTable('reminders')) {
            return;
        }

        Reminder::where('remindable_type', $entity->getMorphClass())
            ->where('remindable_id', $entity->getKey())
            ->whereIn('status', ['pending', 'snoozed'])
            ->update([
                'status' => 'cancelled',
                'snoozed_until' => null,
            ]);
    }

    /**
     * Reconcile all reminders for a user (useful when user changes timezone or default reminder days).
     */
    public function resyncAllForUser(User $user): void
    {
        $user->tasks()->where('status', '!=', 'completed')->each(function (Task $task): void {
            $this->syncTaskReminders($task);
        });

        $user->projects()->active()->with('milestones')->each(function (Project $project): void {
            $this->syncProjectReminders($project);
            foreach ($project->milestones as $milestone) {
                $this->syncMilestoneReminders($milestone);
            }
        });
    }

    /**
     * Process all due reminders safely with transactions, deduplication, preferences, and quiet hours.
     *
     * @return array{processed: int, sent: int, deferred: int, cancelled: int, failed: int}
     */
    public function processDueReminders(?Carbon $nowUtc = null): array
    {
        $referenceUtc = ($nowUtc ?? now())->copy()->utc();
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'deferred' => 0,
            'cancelled' => 0,
            'failed' => 0,
        ];

        $dueIds = Reminder::due($referenceUtc)
            ->orderBy('scheduled_at')
            ->limit(250)
            ->pluck('id');

        foreach ($dueIds as $reminderId) {
            $outcome = $this->processSingleReminder((int) $reminderId, $referenceUtc);
            if ($outcome !== 'skipped') {
                $stats['processed']++;
                if (isset($stats[$outcome])) {
                    $stats[$outcome]++;
                }
            }
        }

        return $stats;
    }

    /**
     * Snooze a reminder for a preset ('15m', '30m', '1h', 'tomorrow') or custom duration in minutes.
     */
    public function snoozeReminder(Reminder $reminder, string $preset, ?int $customMinutes = null, ?Carbon $nowUtc = null): Reminder
    {
        $user = $reminder->user()->with('setting')->first();
        $referenceUtc = ($nowUtc ?? now())->copy()->utc();

        $targetUtc = match ($preset) {
            '15m' => $referenceUtc->copy()->addMinutes(15),
            '30m' => $referenceUtc->copy()->addMinutes(30),
            '1h' => $referenceUtc->copy()->addHour(),
            'tomorrow' => UserDateTime::parseLocalDateTime(
                UserDateTime::todayForUser($user)->addDay()->toDateString(),
                '09:00',
                $user,
                '09:00'
            )->utc(),
            'custom' => $referenceUtc->copy()->addMinutes(max(1, (int) ($customMinutes ?: 15))),
            default => $referenceUtc->copy()->addMinutes(15),
        };

        $reminder->update([
            'status' => 'snoozed',
            'snoozed_until' => $targetUtc,
            'suppressed_by_quiet_hours' => false,
        ]);

        return $reminder->fresh();
    }

    /**
     * Dismiss a reminder permanently.
     */
    public function dismissReminder(Reminder $reminder): Reminder
    {
        $reminder->update([
            'status' => 'dismissed',
            'snoozed_until' => null,
            'dismissed_at' => now()->utc(),
        ]);

        return $reminder->fresh();
    }

    private function processSingleReminder(int $reminderId, Carbon $referenceUtc): string
    {
        return DB::transaction(function () use ($reminderId, $referenceUtc): string {
            /** @var Reminder|null $reminder */
            $reminder = Reminder::with(['user.setting', 'remindable'])
                ->whereKey($reminderId)
                ->lockForUpdate()
                ->first();

            if (! $reminder || ! in_array($reminder->status, ['pending', 'snoozed'], true)) {
                return 'skipped';
            }

            $effectiveDue = $reminder->status === 'snoozed' && $reminder->snoozed_until !== null
                ? $reminder->snoozed_until
                : $reminder->scheduled_at;

            if ($effectiveDue->greaterThan($referenceUtc)) {
                return 'skipped';
            }

            $entity = $reminder->remindable;
            if (! $this->isEntityStillRemindable($entity)) {
                $reminder->update([
                    'status' => 'cancelled',
                    'snoozed_until' => null,
                ]);

                return 'cancelled';
            }

            $user = $reminder->user;
            if (! $user) {
                $reminder->update(['status' => 'cancelled']);

                return 'cancelled';
            }

            $setting = $user->ensureSetting();
            if (! $setting->isNotificationCategoryEnabled($reminder->category)) {
                $reminder->update([
                    'status' => 'cancelled',
                    'snoozed_until' => null,
                    'last_error' => 'Disabled by user notification preferences.',
                ]);

                return 'cancelled';
            }

            if (UserDateTime::isInQuietHours($user, $referenceUtc)) {
                $allowCritical = $reminder->is_critical && $setting->allow_critical_in_quiet_hours;
                if (! $allowCritical) {
                    $reminder->update([
                        'status' => 'snoozed',
                        'snoozed_until' => UserDateTime::nextQuietHoursEndUtc($user, $referenceUtc),
                        'suppressed_by_quiet_hours' => true,
                    ]);

                    return 'deferred';
                }
            }

            try {
                $snoozeSuffix = ($reminder->status === 'snoozed' && $reminder->snoozed_until !== null && ! $reminder->suppressed_by_quiet_hours)
                    ? ':snooze:'.$reminder->snoozed_until->timestamp
                    : '';
                $dedupKey = "reminder:{$reminder->id}:{$reminder->reminder_key}{$snoozeSuffix}";

                if (! $this->notificationExists($user, $dedupKey)) {
                    [$entityType, $defaultUrl] = $this->entityNavigation($entity);
                    $actionUrl = is_array($reminder->metadata) && ! empty($reminder->metadata['action_url'])
                        ? (string) $reminder->metadata['action_url']
                        : $defaultUrl;

                    $user->notify(new OperationalNotification(
                        category: $reminder->category,
                        title: $reminder->title,
                        message: $reminder->message ?? '',
                        actionUrl: $actionUrl,
                        entityType: $entityType,
                        entityId: (int) $entity->getKey(),
                        reminderId: $reminder->id,
                        isCritical: (bool) $reminder->is_critical,
                        dedupKey: $dedupKey,
                        meta: is_array($reminder->metadata) ? $reminder->metadata : [],
                    ));
                }

                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => $referenceUtc,
                    'snoozed_until' => null,
                    'suppressed_by_quiet_hours' => false,
                    'attempts' => $reminder->attempts + 1,
                    'last_error' => null,
                ]);

                return 'sent';
            } catch (Throwable $e) {
                $nextAttempts = $reminder->attempts + 1;
                if ($nextAttempts >= 3) {
                    $reminder->update([
                        'status' => 'failed',
                        'attempts' => $nextAttempts,
                        'last_error' => $e->getMessage(),
                    ]);

                    return 'failed';
                }

                $reminder->update([
                    'status' => 'snoozed',
                    'snoozed_until' => $referenceUtc->copy()->addMinutes(5),
                    'attempts' => $nextAttempts,
                    'last_error' => $e->getMessage(),
                ]);

                return 'failed';
            }
        });
    }

    private function isEntityStillRemindable(?Model $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        if (method_exists($entity, 'trashed') && $entity->trashed()) {
            return false;
        }

        if ($entity instanceof Task) {
            return ! in_array($entity->status, ['completed', 'cancelled'], true);
        }

        if ($entity instanceof Project) {
            return ! in_array($entity->status, ['completed', 'archived', 'cancelled'], true);
        }

        if ($entity instanceof ProjectMilestone) {
            if ($entity->is_completed) {
                return false;
            }
            $project = $entity->project()->withTrashed()->first();

            return $project !== null
                && ! $project->trashed()
                && ! in_array($project->status, ['completed', 'archived', 'cancelled'], true);
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function entityNavigation(Model $entity): array
    {
        if ($entity instanceof Task) {
            return ['task', '/tasks'];
        }

        if ($entity instanceof Project) {
            return ['project', "/projects/{$entity->id}"];
        }

        if ($entity instanceof ProjectMilestone) {
            return ['milestone', "/projects/{$entity->project_id}"];
        }

        return ['system', '/dashboard'];
    }

    /**
     * @param  array<string, array<string, mixed>>  $specs
     */
    private function reconcileEntityReminders(Model $entity, int $userId, array $specs): void
    {
        DB::transaction(function () use ($entity, $userId, $specs): void {
            $morphType = $entity->getMorphClass();
            $entityId = $entity->getKey();
            $desiredKeys = array_keys($specs);

            $cancelQuery = Reminder::where('remindable_type', $morphType)
                ->where('remindable_id', $entityId)
                ->whereIn('status', ['pending', 'snoozed']);

            if (! empty($desiredKeys)) {
                $cancelQuery->whereNotIn('reminder_key', $desiredKeys);
            }

            $cancelQuery->update([
                'status' => 'cancelled',
                'snoozed_until' => null,
            ]);

            foreach ($specs as $key => $spec) {
                $existing = Reminder::where('remindable_type', $morphType)
                    ->where('remindable_id', $entityId)
                    ->where('reminder_key', $key)
                    ->first();

                if ($existing !== null) {
                    if (in_array($existing->status, ['sent', 'dismissed'], true)) {
                        continue;
                    }

                    $existing->update([
                        'user_id' => $userId,
                        'category' => $spec['category'],
                        'title' => $spec['title'],
                        'message' => $spec['message'],
                        'offset_label' => $spec['offset_label'],
                        'is_critical' => $spec['is_critical'],
                        'scheduled_at' => $spec['scheduled_at'],
                        'status' => $existing->status === 'snoozed' ? 'snoozed' : 'pending',
                        'metadata' => $spec['metadata'] ?? [],
                    ]);
                } else {
                    Reminder::create([
                        'user_id' => $userId,
                        'remindable_type' => $morphType,
                        'remindable_id' => $entityId,
                        'reminder_key' => $key,
                        'category' => $spec['category'],
                        'title' => $spec['title'],
                        'message' => $spec['message'],
                        'offset_label' => $spec['offset_label'],
                        'is_critical' => $spec['is_critical'],
                        'scheduled_at' => $spec['scheduled_at'],
                        'status' => 'pending',
                        'metadata' => $spec['metadata'] ?? [],
                    ]);
                }
            }
        });
    }

    private function notificationExists(User $user, string $dedupKey): bool
    {
        return $user->notifications()
            ->where('data->dedup_key', $dedupKey)
            ->exists();
    }

    private function presetLabel(string $preset): string
    {
        return match ($preset) {
            'at_due' => 'At due time',
            '15m' => '15 minutes before',
            '30m' => '30 minutes before',
            '1h' => '1 hour before',
            '1d' => '1 day before',
            'custom' => 'Custom time',
            default => 'Reminder',
        };
    }
}
