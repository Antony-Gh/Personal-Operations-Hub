<?php

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

it('creates a task with duration, checklists, and tags', function () {
    $user = User::factory()->create();
    $cat = Category::create(['user_id' => $user->id, 'name' => 'University']);

    $response = $this->actingAs($user)->post(route('tasks.store'), [
        'title' => 'Submit Signals Homework',
        'description' => 'Problems 1 to 5',
        'category_id' => $cat->id,
        'priority' => 'high',
        'status' => 'not_started',
        'due_date' => Carbon::today()->toDateString(),
        'estimated_duration' => 60,
        'next_action' => 'Read textbook chapter 4',
        'checklists' => [
            ['title' => 'Problem 1'],
            ['title' => 'Problem 2'],
        ],
    ]);

    $response->assertSessionHas('success');
    $task = Task::where('user_id', $user->id)->where('title', 'Submit Signals Homework')->first();
    expect($task)->not->toBeNull();
    expect($task->checklists)->toHaveCount(2);
    expect($task->estimated_duration)->toBe(60);
});

it('toggles task completion and records completed_at timestamp', function () {
    $user = User::factory()->create();
    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Test Task',
        'priority' => 'medium',
        'status' => 'not_started',
    ]);

    // Complete
    $this->actingAs($user)->post(route('tasks.toggle', $task->id));
    expect($task->fresh()->status)->toBe('completed');
    expect($task->fresh()->completed_at)->not->toBeNull();

    // Reopen
    $this->actingAs($user)->post(route('tasks.toggle', $task->id));
    expect($task->fresh()->status)->toBe('in_progress');
    expect($task->fresh()->completed_at)->toBeNull();
});

it('duplicates a task and its checklists', function () {
    $user = User::factory()->create();
    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Original Task',
        'priority' => 'high',
        'status' => 'in_progress',
    ]);
    $task->checklists()->create(['title' => 'Subtask 1', 'sort_order' => 1]);

    $this->actingAs($user)->post(route('tasks.duplicate', $task->id));

    $duplicate = Task::where('user_id', $user->id)->where('title', 'Original Task (Copy)')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->checklists)->toHaveCount(1);
    expect($duplicate->status)->toBe('not_started');
});

it('executes bulk actions on multiple tasks', function () {
    $user = User::factory()->create();
    $task1 = Task::create(['user_id' => $user->id, 'title' => 'Task 1', 'priority' => 'low', 'status' => 'not_started']);
    $task2 = Task::create(['user_id' => $user->id, 'title' => 'Task 2', 'priority' => 'low', 'status' => 'not_started']);

    $this->actingAs($user)->post(route('tasks.bulk'), [
        'task_ids' => [$task1->id, $task2->id],
        'action' => 'complete',
    ]);

    expect($task1->fresh()->status)->toBe('completed');
    expect($task2->fresh()->status)->toBe('completed');
});

it('isolates user tasks from other users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $task = Task::create([
        'user_id' => $user1->id,
        'title' => 'User 1 Private Task',
        'priority' => 'high',
        'status' => 'not_started',
    ]);

    $response = $this->actingAs($user2)->delete(route('tasks.destroy', $task->id));
    $response->assertStatus(403);
    expect($task->fresh()->trashed())->toBeFalse();
});

it('rejects assigning another users category or project to a task', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $otherUserCategory = Category::create(['user_id' => $user2->id, 'name' => 'Foreign Area']);

    $response = $this->actingAs($user1)->post(route('tasks.store'), [
        'title' => 'Sneaky Task',
        'category_id' => $otherUserCategory->id,
        'priority' => 'medium',
        'status' => 'not_started',
    ]);

    $response->assertSessionHasErrors(['category_id']);
});
