<?php

use App\Models\Category;
use App\Models\Task;
use App\Models\User;

it('allows user to create a category', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'University Studies',
        'color' => '#3b82f6',
        'icon' => 'GraduationCap',
    ]);

    $response->assertSessionHas('success');
    expect(Category::where('user_id', $user->id)->where('name', 'University Studies')->exists())->toBeTrue();
});

it('returns json when creating a category via json request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('categories.store'), [
        'name' => 'Fitness & Health',
        'color' => '#10b981',
        'icon' => 'Heart',
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'category' => [
                'name' => 'Fitness & Health',
                'color' => '#10b981',
                'icon' => 'Heart',
                'user_id' => $user->id,
            ],
            'message' => 'Area created successfully.',
        ]);

    expect(Category::where('user_id', $user->id)->where('name', 'Fitness & Health')->exists())->toBeTrue();
});

it('allows user to update a category', function () {
    $user = User::factory()->create();
    $cat = Category::create([
        'user_id' => $user->id,
        'name' => 'Software Team',
        'color' => '#8b5cf6',
        'icon' => 'Code',
    ]);

    $response = $this->actingAs($user)->put(route('categories.update', $cat->id), [
        'name' => 'Core Software Engineering',
        'color' => '#6366f1',
        'icon' => 'Code',
    ]);

    $response->assertSessionHas('success');
    expect($cat->fresh()->name)->toBe('Core Software Engineering');
});

it('enforces safe deletion with task handling', function () {
    $user = User::factory()->create();
    $cat1 = Category::create(['user_id' => $user->id, 'name' => 'Cat 1']);
    $cat2 = Category::create(['user_id' => $user->id, 'name' => 'Cat 2']);

    $task = Task::create([
        'user_id' => $user->id,
        'category_id' => $cat1->id,
        'title' => 'Important Task',
        'priority' => 'medium',
        'status' => 'not_started',
    ]);

    // Test reassign option
    $response = $this->actingAs($user)->delete(route('categories.destroy', $cat1->id), [
        'task_action' => 'reassign',
        'reassign_to_category_id' => $cat2->id,
    ]);

    $response->assertSessionHas('success');
    expect($task->fresh()->category_id)->toBe($cat2->id);
    expect($cat1->fresh()->trashed())->toBeTrue();
});

it('prevents user from modifying another users category', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $cat = Category::create(['user_id' => $user1->id, 'name' => 'Secret Category']);

    $response = $this->actingAs($user2)->put(route('categories.update', $cat->id), [
        'name' => 'Hacked',
    ]);

    $response->assertStatus(403);
    expect($cat->fresh()->name)->toBe('Secret Category');
});
