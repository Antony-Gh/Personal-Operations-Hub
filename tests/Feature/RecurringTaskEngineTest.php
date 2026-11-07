<?php

use App\Models\Category;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\User;
use App\Services\RecurringTaskService;
use Carbon\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('completing recurring tasks spawns accurate next occurrences across rules including weekdays and month-end', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC']);
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Engineering',
        'slug' => 'engineering',
        'color' => '#4f46e5',
        'icon' => 'Code',
    ]);
    $project = Project::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
    $tag = Tag::create(['user_id' => $user->id, 'name' => 'Routine', 'color' => '#10b981']);

    // 1. Weekdays rule: Friday 2026-11-06 -> Monday 2026-11-09
    $fridayTask = Task::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'project_id' => $project->id,
        'title' => 'Daily Standup Prep',
        'priority' => 'high',
        'status' => 'in_progress',
        'due_date' => '2026-11-06',
        'due_time' => '08:45',
        'reminder_preset' => '15m',
        'actual_duration' => 25,
        'is_recurring' => true,
        'recurrence_rule' => 'weekdays',
        'recurrence_interval' => 1,
        'recurrence_end_type' => 'never',
    ]);
    $fridayTask->tags()->sync([$tag->id]);
    TaskChecklist::create([
        'task_id' => $fridayTask->id,
        'title' => 'Check blockers',
        'is_completed' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->post("/tasks/{$fridayTask->id}/toggle")
        ->assertRedirect();

    $mondayTask = Task::where('recurrence_parent_id', $fridayTask->id)->first();
    expect($mondayTask)->not->toBeNull()
        ->and($mondayTask->due_date->toDateString())->toBe('2026-11-09')
        ->and($mondayTask->due_time)->toBe('08:45')
        ->and($mondayTask->status)->toBe('not_started')
        ->and($mondayTask->category_id)->toBe($category->id)
        ->and($mondayTask->project_id)->toBe($project->id)
        ->and($mondayTask->priority)->toBe('high')
        ->and($mondayTask->actual_duration)->toBeNull()
        ->and($mondayTask->recurrence_occurrences_count)->toBe(2)
        ->and($mondayTask->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($mondayTask->checklists)->toHaveCount(1)
        ->and($mondayTask->checklists->first()->is_completed)->toBeFalse();

    // 2. Monthly month-end rule: 2026-01-31 -> 2026-02-28 (no overflow into March)
    $janTask = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Close Monthly Ledger',
        'status' => 'not_started',
        'due_date' => '2026-01-31',
        'is_recurring' => true,
        'recurrence_rule' => 'monthly',
        'recurrence_interval' => 1,
    ]);

    $febTask = $janTask->markAsCompleted();
    expect($febTask)->not->toBeNull()
        ->and($febTask->due_date->toDateString())->toBe('2026-02-28');

    // Leap year check via service: 2028-01-31 -> 2028-02-29
    $service = app(RecurringTaskService::class);
    $leapNext = $service->calculateNextDate(Carbon::parse('2028-01-31'), 'monthly', 1);
    expect($leapNext?->toDateString())->toBe('2028-02-29');

    // Custom interval: every 3 days
    $customNext = $service->calculateNextDate(Carbon::parse('2026-11-01'), 'custom_days', 3);
    expect($customNext?->toDateString())->toBe('2026-11-04');
});

test('recurring task respects max occurrences and end date limits and prevents duplicate children', function (): void {
    $user = User::factory()->create();

    // Max occurrences = 2
    $limitedTask = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Two-Part Sprint Check',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
        'is_recurring' => true,
        'recurrence_rule' => 'daily',
        'recurrence_interval' => 1,
        'recurrence_end_type' => 'after_occurrences',
        'recurrence_max_occurrences' => 2,
        'recurrence_occurrences_count' => 1,
    ]);

    $secondOccurrence = $limitedTask->markAsCompleted();
    expect($secondOccurrence)->not->toBeNull()
        ->and($secondOccurrence->due_date->toDateString())->toBe('2026-11-11')
        ->and($secondOccurrence->recurrence_occurrences_count)->toBe(2);

    // Calling generateNextOccurrence again on the first task does NOT create a duplicate
    $duplicateAttempt = app(RecurringTaskService::class)->generateNextOccurrence($limitedTask->fresh());
    expect($duplicateAttempt)->toBeNull()
        ->and(Task::where('recurrence_parent_id', $limitedTask->id)->count())->toBe(1);

    // Completing the 2nd occurrence stops because max_occurrences = 2
    $thirdOccurrence = $secondOccurrence->markAsCompleted();
    expect($thirdOccurrence)->toBeNull();

    // End on date limit
    $dateBounded = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Exam Prep Block',
        'status' => 'not_started',
        'due_date' => '2026-11-14',
        'is_recurring' => true,
        'recurrence_rule' => 'weekly',
        'recurrence_interval' => 1,
        'recurrence_end_type' => 'on_date',
        'recurrence_end_date' => '2026-11-18',
    ]);

    expect($dateBounded->markAsCompleted())->toBeNull();
});

test('ops:process-recurring command catches up completed recurring tasks safely', function (): void {
    $user = User::factory()->create();

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Weekly Backup Audit',
        'status' => 'completed',
        'completed_at' => now(),
        'due_date' => '2026-11-01',
        'is_recurring' => true,
        'recurrence_rule' => 'weekly',
        'recurrence_interval' => 1,
        'recurrence_generated' => false,
    ]);

    $this->artisan('ops:process-recurring')
        ->assertExitCode(0);

    expect(Task::where('recurrence_parent_id', $task->id)->count())->toBe(1);

    // Running a second time produces 0 duplicates
    $this->artisan('ops:process-recurring')
        ->assertExitCode(0);

    expect(Task::where('recurrence_parent_id', $task->id)->count())->toBe(1);
});
