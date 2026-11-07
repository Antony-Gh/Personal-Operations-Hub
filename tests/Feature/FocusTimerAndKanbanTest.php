<?php

use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('focus session tracks server timestamps accurately logs task duration without double counting and notifies on completion', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => false,
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Implement Graph Algorithm',
        'estimated_duration' => 60,
        'actual_duration' => 5,
        'status' => 'in_progress',
    ]);

    // Start 25m focus session
    $startRes = $this->actingAs($user)
        ->postJson('/focus-sessions/start', [
            'mode' => 'focus',
            'planned_minutes' => 25,
            'task_id' => $task->id,
        ])
        ->assertOk();

    $sessionId = $startRes->json('session.id');
    expect($sessionId)->not->toBeNull();

    // Advance 10 minutes -> pause -> logs +10m onto task (5 + 10 = 15)
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:10:00', 'UTC'));
    $this->actingAs($user)
        ->postJson("/focus-sessions/{$sessionId}/pause")
        ->assertOk()
        ->assertJsonPath('session.status', 'paused');

    expect($task->fresh()->actual_duration)->toBe(15);

    // Advance 5 minutes while paused -> elapsed should NOT grow
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:15:00', 'UTC'));
    $this->actingAs($user)
        ->postJson("/focus-sessions/{$sessionId}/resume")
        ->assertOk()
        ->assertJsonPath('session.status', 'running');

    // Advance 15 more minutes -> complete -> logs only the new +15m delta (15 + 15 = 30 total)
    Carbon::setTestNow(Carbon::parse('2026-11-10 10:30:00', 'UTC'));
    $this->actingAs($user)
        ->postJson("/focus-sessions/{$sessionId}/complete", ['reached_target' => true])
        ->assertOk()
        ->assertJsonPath('session.status', 'completed');

    expect($task->fresh()->actual_duration)->toBe(30)
        ->and($user->notifications()->where('data->category', 'focus_timer')->count())->toBe(1);

    // Manual actual_duration adjustment works
    $this->actingAs($user)
        ->patchJson("/tasks/{$task->id}/actual-duration", ['actual_duration' => 45])
        ->assertOk()
        ->assertJsonPath('actual_duration', 45);

    expect($task->fresh()->actual_duration)->toBe(45);
});

test('focus session endpoints enforce strict cross-user authorization', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownerTask = Task::factory()->create(['user_id' => $owner->id]);

    // Other user cannot start focus session linked to owner's task
    $this->actingAs($other)
        ->postJson('/focus-sessions/start', [
            'mode' => 'focus',
            'task_id' => $ownerTask->id,
        ])
        ->assertStatus(422);

    $ownerSession = FocusSession::factory()->create([
        'user_id' => $owner->id,
        'task_id' => $ownerTask->id,
        'status' => 'running',
    ]);

    $this->actingAs($other)
        ->postJson("/focus-sessions/{$ownerSession->id}/pause")
        ->assertForbidden();

    $this->actingAs($other)
        ->postJson("/focus-sessions/{$ownerSession->id}/stop")
        ->assertForbidden();

    $this->actingAs($other)
        ->patchJson("/tasks/{$ownerTask->id}/actual-duration", ['actual_duration' => 99])
        ->assertForbidden();
});

test('kanban view renders and status endpoint transitions tasks and spawns next recurring occurrence', function (): void {
    $user = User::factory()->create();

    $recurringTask = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Weekly Security Scan',
        'status' => 'in_progress',
        'due_date' => '2026-11-10',
        'is_recurring' => true,
        'recurrence_rule' => 'weekly',
        'recurrence_interval' => 1,
    ]);

    $this->actingAs($user)
        ->get('/tasks?view=kanban')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tasks/Index')
            ->where('viewMode', 'kanban')
            ->has('kanbanTasks', 1)
        );

    $this->actingAs($user)
        ->patchJson("/tasks/{$recurringTask->id}/status", [
            'status' => 'completed',
        ])
        ->assertOk()
        ->assertJsonPath('task.status', 'completed');

    expect($recurringTask->fresh()->status)->toBe('completed')
        ->and(Task::where('recurrence_parent_id', $recurringTask->id)->exists())->toBeTrue();
});

test('command palette searches tasks projects milestones and habits with strict user isolation', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Task::factory()->create(['user_id' => $userA->id, 'title' => 'Quantum Kernel Optimization']);
    Project::factory()->create(['user_id' => $userA->id, 'name' => 'Quantum Simulator']);
    Habit::create([
        'user_id' => $userA->id,
        'name' => 'Quantum Paper Reading',
        'category' => 'Study',
        'frequency' => 'daily',
        'target_days_per_week' => 5,
    ]);

    Task::factory()->create(['user_id' => $userB->id, 'title' => 'Quantum Competitor Secret']);

    $res = $this->actingAs($userA)
        ->getJson('/command-palette/search?q=Quantum')
        ->assertOk();

    expect($res->json('tasks'))->toHaveCount(1)
        ->and($res->json('tasks.0.title'))->toBe('Quantum Kernel Optimization')
        ->and($res->json('projects'))->toHaveCount(1)
        ->and($res->json('habits'))->toHaveCount(1);
});
