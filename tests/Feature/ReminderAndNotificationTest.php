<?php

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('task reminders sync on create update and completion and dispatch notifications via artisan command without duplicates', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => false,
    ]);

    $this->actingAs($user)
        ->post('/tasks', [
            'title' => 'Submit Architecture Proposal',
            'priority' => 'high',
            'status' => 'not_started',
            'due_date' => '2026-11-10',
            'due_time' => '09:00',
            'reminder_preset' => '15m',
            'custom_reminder_times' => ['1h'],
        ])
        ->assertSessionHasNoErrors();

    $task = Task::where('user_id', $user->id)->firstOrFail();

    // Expect: 15m before (08:45), 1h before (08:00), and overdue (10:00)
    $pending = $task->reminders()->where('status', 'pending')->orderBy('scheduled_at')->get();
    expect($pending)->toHaveCount(3)
        ->and($pending[0]->scheduled_at->format('H:i'))->toBe('08:00')
        ->and($pending[1]->scheduled_at->format('H:i'))->toBe('08:45')
        ->and($pending[2]->scheduled_at->format('H:i'))->toBe('10:00');

    // At 08:01 UTC, the 08:00 reminder is due
    Carbon::setTestNow(Carbon::parse('2026-11-10 08:01:00', 'UTC'));
    $this->artisan('ops:process-reminders')->assertExitCode(0);

    expect($user->unreadNotifications()->count())->toBe(1);
    $firstNotification = $user->unreadNotifications()->first();
    expect($firstNotification->data['title'])->toContain('Submit Architecture Proposal')
        ->and($firstNotification->data['is_critical'])->toBeTrue();

    // Re-running at the same minute does not send duplicates
    $this->artisan('ops:process-reminders')->assertExitCode(0);
    expect($user->unreadNotifications()->count())->toBe(1);

    // Snoozing the 08:45 reminder for 30 minutes
    $secondReminder = $pending[1]->fresh();
    $this->actingAs($user)
        ->postJson("/reminders/{$secondReminder->id}/snooze", [
            'preset' => '30m',
        ])
        ->assertOk();

    expect($secondReminder->fresh()->status)->toBe('snoozed')
        ->and($secondReminder->fresh()->snoozed_until->format('H:i'))->toBe('08:31');

    // Completing task directly from the notification cancels remaining reminders
    $this->actingAs($user)
        ->postJson("/notifications/{$firstNotification->id}/complete-task")
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($task->fresh()->status)->toBe('completed')
        ->and($task->reminders()->whereIn('status', ['pending', 'snoozed'])->count())->toBe(0);
});

test('quiet hours defer standard reminders until quiet hours end while allowing critical overrides when enabled', function (): void {
    // 23:15 UTC is inside 22:00 -> 07:00 quiet hours
    Carbon::setTestNow(Carbon::parse('2026-11-10 23:15:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
        'allow_critical_in_quiet_hours' => true,
    ]);

    $normalTask = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Low Priority Reading',
        'priority' => 'low',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
        'due_time' => '23:00',
        'reminder_preset' => 'at_due',
    ]);

    $criticalTask = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Critical Server Cutoff',
        'priority' => 'high',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
        'due_time' => '23:00',
        'reminder_preset' => 'at_due',
    ]);

    $this->artisan('ops:process-reminders')->assertExitCode(0);

    $normalReminder = $normalTask->reminders()->where('category', 'task_reminders')->firstOrFail();
    $criticalReminder = $criticalTask->reminders()->where('category', 'task_reminders')->firstOrFail();

    expect($normalReminder->status)->toBe('snoozed')
        ->and($normalReminder->suppressed_by_quiet_hours)->toBeTrue()
        ->and($normalReminder->snoozed_until->format('Y-m-d H:i'))->toBe('2026-11-11 07:00')
        ->and($criticalReminder->status)->toBe('sent')
        ->and($user->unreadNotifications()->count())->toBe(1);
});

test('project and milestone reminders sync automatically and milestone completion dispatches notification', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => false,
    ]);

    $this->actingAs($user)
        ->post('/projects', [
            'name' => 'Thesis Defense',
            'status' => 'in_progress',
            'target_date' => '2026-11-17',
            'reminders_enabled' => true,
            'reminder_days' => [7, 1, 0],
            'overdue_reminders_enabled' => true,
            'milestones' => [
                [
                    'title' => 'Slide Deck Finalized',
                    'due_date' => '2026-11-13',
                    'due_time' => '14:00',
                ],
            ],
        ])
        ->assertRedirect();

    $project = Project::where('user_id', $user->id)->firstOrFail();
    $milestone = ProjectMilestone::where('project_id', $project->id)->firstOrFail();

    // Project has 7d, 1d, 0d + overdue = 4 pending reminders
    expect($project->reminders()->where('status', 'pending')->count())->toBe(4)
        ->and($milestone->reminders()->where('status', 'pending')->count())->toBeGreaterThanOrEqual(3);

    // Toggle milestone to completed -> cancels milestone pending reminders and sends completion notification
    $this->actingAs($user)
        ->post("/milestones/{$milestone->id}/toggle")
        ->assertRedirect();

    expect($milestone->fresh()->is_completed)->toBeTrue()
        ->and($milestone->reminders()->where('status', 'pending')->count())->toBe(0)
        ->and($user->notifications()->where('data->category', 'milestone_deadlines')->exists())->toBeTrue();
});

test('notification center enforces strict user isolation across read delete snooze and dismiss endpoints', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $task = Task::factory()->create([
        'user_id' => $owner->id,
        'due_date' => '2026-11-12',
        'due_time' => '10:00',
        'reminder_preset' => 'at_due',
    ]);
    $reminder = $task->reminders()->firstOrFail();

    $this->actingAs($intruder)
        ->postJson("/reminders/{$reminder->id}/snooze", ['preset' => '15m'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->postJson("/reminders/{$reminder->id}/dismiss")
        ->assertForbidden();

    // Trigger notification for owner
    Carbon::setTestNow(Carbon::parse('2026-11-12 10:05:00', 'UTC'));
    $owner->ensureSetting()->update(['timezone' => 'UTC', 'quiet_hours_enabled' => false]);
    $this->artisan('ops:process-reminders')->assertExitCode(0);

    $ownerNotification = $owner->notifications()->firstOrFail();

    $this->actingAs($intruder)
        ->postJson("/notifications/{$ownerNotification->id}/read")
        ->assertNotFound();

    $this->actingAs($intruder)
        ->deleteJson("/notifications/{$ownerNotification->id}")
        ->assertNotFound();

    // Owner can list, mark all read, and clear all
    $this->actingAs($owner)
        ->getJson('/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1);

    $this->actingAs($owner)
        ->postJson('/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    $this->actingAs($owner)
        ->deleteJson('/notifications/clear-all')
        ->assertOk();

    expect($owner->notifications()->count())->toBe(0);
});
