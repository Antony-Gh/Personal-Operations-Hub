<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
});

afterEach(function (): void {
    DailyPlanTask::flushEventListeners();
    Carbon::setTestNow();
});

test('smart task import imports due tasks preserves priority limits and prevents duplicates', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    // 4 high/medium/low tasks due today (only production-supported priorities)
    $task1 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'High Priority Architecture Review',
        'priority' => 'high',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'High Priority Security Audit',
        'priority' => 'high',
        'due_date' => $today,
        'status' => 'in_progress',
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
        'status' => 'waiting',
    ]);

    // Bob's task due today must never be imported
    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Unrelated Task',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $response = $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect();

    $response->assertSessionHas('success', 'Imported 4 task(s) into My Day.');

    $imported = DailyPlanTask::where('daily_plan_id', $plan->id)
        ->orderBy('sort_order')
        ->get();

    expect($imported)->toHaveCount(4)
        ->and($imported->pluck('task_id'))->not->toContain($bobTask->id);

    $priorities = $imported->where('is_priority', true);
    expect($priorities->count())->toBe(2)
        ->and($priorities->pluck('task_id'))->toContain($task1->id)
        ->and($priorities->pluck('task_id'))->toContain($task2->id);

    // Re-running import is idempotent and does not duplicate or falsely report imports
    $secondResponse = $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect();

    $secondResponse->assertSessionHas('info', 'No matching tasks available to import.');
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(4);
});

test('smart task import excludes completed deferred and undated tasks and respects include_overdue flag', function (): void {
    $today = Carbon::today()->toDateString();
    $yesterday = Carbon::yesterday()->toDateString();
    $tomorrow = Carbon::tomorrow()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    $dueTodayTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Due Today Active',
        'due_date' => $today,
        'status' => 'not_started',
    ]);

    $completedToday = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Completed Today',
        'due_date' => $today,
        'status' => 'completed',
    ]);

    $deferredToday = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Deferred Today',
        'due_date' => $today,
        'status' => 'deferred',
    ]);

    $overdueTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Overdue Yesterday',
        'due_date' => $yesterday,
        'status' => 'in_progress',
    ]);

    $futureTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Future Tomorrow',
        'due_date' => $tomorrow,
        'status' => 'not_started',
    ]);

    $undatedBacklogTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Undated Backlog Item',
        'due_date' => null,
        'status' => 'not_started',
    ]);

    // Verify DailyPlanController::index() exposes only eligible unplannedDueTasks (excluding completed/deferred)
    $this->actingAs($this->alice)
        ->get(route('my-day.index', ['date' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyDay/Index')
            ->has('unplannedDueTasks', 1)
            ->where('unplannedDueTasks.0.id', $dueTodayTask->id)
        );

    // 1. Default import (include_overdue omitted) imports ONLY due today active task
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 1 task(s) into My Day.');

    $plannedIds = DailyPlanTask::where('daily_plan_id', $plan->id)->pluck('task_id')->all();
    expect($plannedIds)->toBe([$dueTodayTask->id])
        ->and($plannedIds)->not->toContain($overdueTask->id)
        ->and($plannedIds)->not->toContain($completedToday->id)
        ->and($plannedIds)->not->toContain($deferredToday->id)
        ->and($plannedIds)->not->toContain($undatedBacklogTask->id)
        ->and($plannedIds)->not->toContain($futureTask->id);

    // 2. Import with include_overdue => false still imports nothing further
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'include_overdue' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('info', 'No matching tasks available to import.');

    // 3. Import with include_overdue => true imports ONLY overdue non-null due_date task (NEVER undated/completed/deferred/future)
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'include_overdue' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 1 task(s) into My Day.');

    $plannedAfterOverdue = DailyPlanTask::where('daily_plan_id', $plan->id)->pluck('task_id')->all();
    expect($plannedAfterOverdue)->toContain($dueTodayTask->id)
        ->and($plannedAfterOverdue)->toContain($overdueTask->id)
        ->and($plannedAfterOverdue)->not->toContain($undatedBacklogTask->id)
        ->and($plannedAfterOverdue)->not->toContain($completedToday->id)
        ->and($plannedAfterOverdue)->not->toContain($deferredToday->id)
        ->and($plannedAfterOverdue)->not->toContain($futureTask->id);
});

test('smart task import preserves 3 existing manual priorities and sequential sort order', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    // Pre-populate 3 manual priority tasks with explicit sort order 1, 2, 5
    $manualPriorities = [];
    foreach ([1, 2, 5] as $index => $sortOrder) {
        $task = Task::factory()->create([
            'user_id' => $this->alice->id,
            'title' => 'Manual Priority '.($index + 1),
            'priority' => 'medium',
            'due_date' => $today,
        ]);

        $manualPriorities[] = $task;

        DailyPlanTask::create([
            'daily_plan_id' => $plan->id,
            'task_id' => $task->id,
            'is_priority' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    // Create 4 new HIGH priority tasks due today
    $newHigh1 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Imported High 1',
        'priority' => 'high',
        'due_date' => $today,
    ]);
    $newHigh2 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Imported High 2',
        'priority' => 'high',
        'due_date' => $today,
    ]);

    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 2 task(s) into My Day.');

    $allRows = DailyPlanTask::where('daily_plan_id', $plan->id)
        ->orderBy('sort_order')
        ->get()
        ->keyBy('task_id');

    expect($allRows)->toHaveCount(5);

    // Existing 3 manual priorities MUST stay prioritized and retain original sort_order (1, 2, 5)
    expect($allRows[$manualPriorities[0]->id]->is_priority)->toBeTrue()
        ->and($allRows[$manualPriorities[0]->id]->sort_order)->toBe(1)
        ->and($allRows[$manualPriorities[1]->id]->is_priority)->toBeTrue()
        ->and($allRows[$manualPriorities[1]->id]->sort_order)->toBe(2)
        ->and($allRows[$manualPriorities[2]->id]->is_priority)->toBeTrue()
        ->and($allRows[$manualPriorities[2]->id]->sort_order)->toBe(5);

    // Newly imported high-priority tasks must NOT exceed 3 priorities total and get sequential sort orders after max(5) -> 6, 7
    expect($allRows[$newHigh1->id]->is_priority)->toBeFalse()
        ->and($allRows[$newHigh1->id]->sort_order)->toBe(6)
        ->and($allRows[$newHigh2->id]->is_priority)->toBeFalse()
        ->and($allRows[$newHigh2->id]->sort_order)->toBe(7)
        ->and($allRows->where('is_priority', true)->count())->toBe(3);
});

test('smart task import caps new high priority tasks at remaining slots up to 3 total', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    // 1 manual priority already selected -> 2 priority slots remain
    $manualTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Existing Manual Priority',
        'due_date' => $today,
    ]);
    DailyPlanTask::create([
        'daily_plan_id' => $plan->id,
        'task_id' => $manualTask->id,
        'is_priority' => true,
        'sort_order' => 1,
    ]);

    // Create 4 high-priority due tasks
    for ($i = 1; $i <= 4; $i++) {
        Task::factory()->create([
            'user_id' => $this->alice->id,
            'title' => "High Task {$i}",
            'priority' => 'high',
            'due_date' => $today,
        ]);
    }

    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 4 task(s) into My Day.');

    $prioritiesCount = DailyPlanTask::where('daily_plan_id', $plan->id)
        ->where('is_priority', true)
        ->count();

    expect($prioritiesCount)->toBe(3);
});

test('smart task import handles selective task_ids empty arrays malformed IDs and authorization boundaries', function (): void {
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

    Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task B Due Today',
        'due_date' => $today,
    ]);

    $softDeletedTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Trashed Task',
        'due_date' => $today,
    ]);
    $softDeletedTask->delete();

    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Task',
        'due_date' => $today,
    ]);

    // 1. Explicit empty task_ids: [] must import NOTHING (must NOT fall back to importing Task A & Task B)
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHas('info', 'No tasks were selected to import.');

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(0);

    // 2. Malformed non-integer task ID rejected cleanly
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => ['invalid-id'],
        ])
        ->assertSessionHasErrors(['task_ids.0']);

    // 3. Soft-deleted task ID rejected cleanly
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [$softDeletedTask->id],
        ])
        ->assertSessionHasErrors(['task_ids.0']);

    // 4. Cross-user task ID rejected cleanly
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [$bobTask->id],
        ])
        ->assertSessionHasErrors(['task_ids.0']);

    // 5. Selective import with duplicate ID in payload deduplicates cleanly
    $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id), [
            'task_ids' => [$aliceTaskA->id, $aliceTaskA->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Imported 1 task(s) into My Day.');

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(1)
        ->and(DailyPlanTask::where('daily_plan_id', $plan->id)->first()->task_id)->toBe($aliceTaskA->id);

    // 6. Unauthorized user cannot import into Alice's daily plan
    $this->actingAs($this->bob)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertForbidden();
});

test('smart task import enforces database uniqueness handles concurrent row arrival and rolls back on failure', function (): void {
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => $today,
    ]);

    $task1 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'First Task',
        'due_date' => $today,
        'priority' => 'high',
    ]);

    $task2 = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Second Task',
        'due_date' => $today,
        'priority' => 'medium',
    ]);

    // 1. Verify database-level UNIQUE constraint on (daily_plan_id, task_id) is active
    DailyPlanTask::create([
        'daily_plan_id' => $plan->id,
        'task_id' => $task1->id,
        'sort_order' => 1,
        'is_priority' => false,
    ]);

    expect(fn () => DailyPlanTask::create([
        'daily_plan_id' => $plan->id,
        'task_id' => $task1->id,
        'sort_order' => 2,
        'is_priority' => false,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Clear pivot table for rollback simulation
    DailyPlanTask::where('daily_plan_id', $plan->id)->delete();

    // 2. Simulate concurrent insertion racing in for $task2 during transaction before $task2's iteration
    DailyPlanTask::creating(function (DailyPlanTask $pivot) use ($plan, $task1, $task2): void {
        if ($pivot->task_id === $task1->id) {
            DB::table('daily_plan_tasks')->insert([
                'daily_plan_id' => $plan->id,
                'task_id' => $task2->id,
                'is_priority' => true,
                'sort_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $concurrentResp = $this->actingAs($this->alice)
        ->post(route('my-day.import-due-tasks', $plan->id))
        ->assertRedirect();

    // Only $task1 was newly inserted by this request; $task2 was already inserted concurrently so count MUST be 1
    $concurrentResp->assertSessionHas('success', 'Imported 1 task(s) into My Day.');
    $row2 = DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task2->id)->firstOrFail();
    expect($row2->sort_order)->toBe(99)
        ->and($row2->is_priority)->toBeTrue();

    DailyPlanTask::flushEventListeners();
    DailyPlanTask::where('daily_plan_id', $plan->id)->delete();

    // 3. Simulate mid-import failure on the 2nd task -> verifies full transaction rollback of 1st task
    DailyPlanTask::creating(function (DailyPlanTask $pivot) use ($task2): void {
        if ($pivot->task_id === $task2->id) {
            throw new RuntimeException('Simulated database insert failure mid-import.');
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->alice)->post(route('my-day.import-due-tasks', $plan->id)))
        ->toThrow(RuntimeException::class, 'Simulated database insert failure mid-import.');

    // Both task1 and task2 must be absent from daily_plan_tasks after rollback
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->count())->toBe(0);
});
