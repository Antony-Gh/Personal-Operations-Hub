<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
});

test('smart task import imports due tasks preserves priority limits and prevents duplicates', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    // Create 4 tasks due today for Alice: 2 urgent/high, 2 medium/low
    $task1 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Urgent Architecture Review',
        'priority' => 'urgent',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'High Priority Security Audit',
        'priority' => 'high',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $task3 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Medium Routine Documentation',
        'priority' => 'medium',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $task4 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Low Priority Backlog Refinement',
        'priority' => 'low',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    // Bob task due today (must never be imported)
    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Unrelated Task',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    // Import all due tasks
    $response = $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect();

    $response->assertSessionHas('success', 'Imported 4 task(s) into My Day.');

    $imported = DailyPlanTask::where('daily_plan_id', $plan->id)
        ->orderBy('sort_order')
        ->get();

    expect($imported)->toHaveCount(4);

    // Verify Bob's task is NOT in Alice's plan
    expect($imported->pluck('task_id'))->not->toContain($bobTask->id);

    // Verify urgent and high tasks became priorities, but count does not exceed 3
    $priorities = $imported->where('is_priority', true);
    expect($priorities->count())->toBe(2)
        ->and($priorities->pluck('task_id'))->toContain($task1->id)
        ->and($priorities->pluck('task_id'))->toContain($task2->id);

    // Re-running the import must deduplicate and import 0 new tasks
    $secondResponse = $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect();

    $secondResponse->assertSessionHas('info', 'No matching tasks available to import.');
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(4);
});

test('smart task import allows selective task IDs and rejects other users tasks', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    $aliceTaskA = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task A',
        'due_date' => $today,
    ]);

    $aliceTaskB = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task B',
        'due_date' => $today,
    ]);

    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Task',
        'due_date' => $today,
    ]);

    // Selective import of Alice Task A only
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [$aliceTaskA->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 1 task(s) into My Day.');

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(1)
        ->and(DailyPlanTask::where('daily_plan_id', $plan->id)->first()->task_id)->toBe($aliceTaskA->id);

    // Attempting to pass Bob's task ID must fail validation
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [$bobTask->id],
        ])
        ->assertSessionHasErrors(['task_ids.0']);

    // Bob cannot invoke smart import on Alice's plan
    $this->actingAs($this->bob)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertForbidden();
});

test('smart task import can include overdue tasks when requested', function (): void {
    $today = Carbon::today()->toDateString();
    $yesterday = Carbon::yesterday()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    $overdueTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Overdue Item from Yesterday',
        'due_date' => $yesterday,
        'status' => 'in_progress',
    ]);

    $todayTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Due Today Item',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    // Import with include_overdue: true
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'include_overdue' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 2 task(s) into My Day.');

    $importedTaskIds = DailyPlanTask::where('daily_plan_id', $plan->id)->pluck('task_id')->toArray();
    expect($importedTaskIds)->toContain($overdueTask->id)
        ->and($importedTaskIds)->toContain($todayTask->id);
});

