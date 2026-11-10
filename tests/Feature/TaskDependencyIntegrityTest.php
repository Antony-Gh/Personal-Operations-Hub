<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
});

test('task cannot depend on itself', function (): void {
    $task = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Self Dependent Candidate',
        'status' => 'not_started',
    ]);

    $this->actingAs($this->alice)
        ->put(route('tasks.update', $task), [
            'title' => $task->title,
            'priority' => $task->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$task->id],
        ])
        ->assertSessionHasErrors('blocked_by_ids');

    expect(DB::table('task_dependencies')->where('task_id', $task->id)->count())->toBe(0);
});

test('task cannot depend on another users task', function (): void {
    $aliceTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Alice Task',
        'status' => 'not_started',
    ]);

    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Task',
        'status' => 'not_started',
    ]);

    $this->actingAs($this->alice)
        ->put(route('tasks.update', $aliceTask), [
            'title' => $aliceTask->title,
            'priority' => $aliceTask->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$bobTask->id],
        ])
        ->assertSessionHasErrors(['blocked_by_ids.0']);

    expect(DB::table('task_dependencies')->where('task_id', $aliceTask->id)->count())->toBe(0);
});

test('task dependency engine prevents multi-node indirect cycles and rolls back atomically', function (): void {
    // Chain: Task C depends on Task B, Task B depends on Task A
    $taskA = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task A (Root)',
        'status' => 'in_progress',
    ]);

    $taskB = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task B (Middle)',
        'status' => 'not_started',
    ]);

    $taskC = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Task C (Leaf)',
        'status' => 'not_started',
    ]);

    // B is blocked by A
    $this->actingAs($this->alice)
        ->put(route('tasks.update', $taskB), [
            'title' => $taskB->title,
            'priority' => $taskB->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$taskA->id],
        ])
        ->assertSessionHasNoErrors();

    // C is blocked by B
    $this->actingAs($this->alice)
        ->put(route('tasks.update', $taskC), [
            'title' => $taskC->title,
            'priority' => $taskC->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$taskB->id],
        ])
        ->assertSessionHasNoErrors();

    // Now attempt indirect cycle: A is blocked by C (forming A -> C -> B -> A cycle)
    $this->actingAs($this->alice)
        ->put(route('tasks.update', $taskA), [
            'title' => $taskA->title,
            'priority' => $taskA->priority,
            'status' => 'in_progress',
            'blocked_by_ids' => [$taskC->id],
        ])
        ->assertSessionHasErrors('blocked_by_ids');

    // Verify atomic rollback: Task A must NOT have any dependencies in database
    expect(DB::table('task_dependencies')->where('task_id', $taskA->id)->count())->toBe(0);
});

test('valid dependency chain automatically transitions statuses from waiting to not_started on completion', function (): void {
    $blocker = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Foundation Task',
        'status' => 'in_progress',
    ]);

    $dependent = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Downstream Task',
        'status' => 'not_started',
    ]);

    $this->actingAs($this->alice)
        ->put(route('tasks.update', $dependent), [
            'title' => $dependent->title,
            'priority' => $dependent->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$blocker->id],
        ])
        ->assertSessionHasNoErrors();

    // Dependent auto-moves to waiting
    expect($dependent->fresh()->status)->toBe('waiting');

    // Complete blocker -> dependent auto-moves to not_started
    $this->actingAs($this->alice)
        ->post(route('tasks.toggle', $blocker))
        ->assertRedirect();

    expect($blocker->fresh()->status)->toBe('completed')
        ->and($dependent->fresh()->status)->toBe('not_started');
});
