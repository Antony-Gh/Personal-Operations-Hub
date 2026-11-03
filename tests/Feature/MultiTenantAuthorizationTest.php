<?php

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\DailyPlan;
use App\Models\Habit;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\Carbon;

beforeEach(function () {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
});

test('user cannot update another users category', function () {
    $cat = Category::create(['user_id' => $this->user1->id, 'name' => 'Private Cat']);

    $response = $this->actingAs($this->user2)->put(route('categories.update', $cat->id), [
        'name' => 'Hijacked Cat',
    ]);

    $response->assertStatus(403);
    expect($cat->fresh()->name)->toBe('Private Cat');
});

test('user cannot delete another users category', function () {
    $cat = Category::create(['user_id' => $this->user1->id, 'name' => 'Private Cat']);

    $response = $this->actingAs($this->user2)->delete(route('categories.destroy', $cat->id), [
        'task_action' => 'keep_unassigned',
    ]);

    $response->assertStatus(403);
    expect($cat->fresh()->trashed())->toBeFalse();
});

test('user cannot update another users task', function () {
    $task = Task::create([
        'user_id' => $this->user1->id,
        'title' => 'Private Task',
        'priority' => 'high',
        'status' => 'not_started',
    ]);

    $response = $this->actingAs($this->user2)->put(route('tasks.update', $task->id), [
        'title' => 'Malicious Update',
        'priority' => 'low',
        'status' => 'completed',
    ]);

    $response->assertStatus(403);
    expect($task->fresh()->title)->toBe('Private Task');
});

test('user cannot toggle status or duplicate another users task', function () {
    $task = Task::create([
        'user_id' => $this->user1->id,
        'title' => 'Private Task',
        'priority' => 'medium',
        'status' => 'not_started',
    ]);

    $this->actingAs($this->user2)->post(route('tasks.toggle', $task->id))->assertStatus(403);
    expect($task->fresh()->status)->toBe('not_started');

    $this->actingAs($this->user2)->post(route('tasks.duplicate', $task->id))->assertStatus(403);
    expect(Task::where('user_id', $this->user2->id)->count())->toBe(0);
});

test('user cannot view or update another users project', function () {
    $project = Project::create([
        'user_id' => $this->user1->id,
        'name' => 'Top Secret Project',
        'status' => 'in_progress',
    ]);

    $this->actingAs($this->user2)->get(route('projects.show', $project->id))->assertStatus(403);

    $this->actingAs($this->user2)->put(route('projects.update', $project->id), [
        'name' => 'Hacked Name',
        'status' => 'archived',
    ])->assertStatus(403);

    expect($project->fresh()->name)->toBe('Top Secret Project');
});

test('user cannot mutate another users project milestones', function () {
    $project = Project::create([
        'user_id' => $this->user1->id,
        'name' => 'Initiative',
        'status' => 'in_progress',
    ]);

    $milestone = ProjectMilestone::create([
        'project_id' => $project->id,
        'title' => 'Phase 1',
        'is_completed' => false,
        'sort_order' => 1,
    ]);

    // Attempt to add milestone to user 1 project
    $this->actingAs($this->user2)->post(route('projects.milestones.store', $project->id), [
        'title' => 'Illegal Milestone',
    ])->assertStatus(403);

    // Attempt to toggle user 1 milestone
    $this->actingAs($this->user2)->post(route('milestones.toggle', $milestone->id))->assertStatus(403);
    expect($milestone->fresh()->is_completed)->toBeFalse();

    // Attempt to delete user 1 milestone
    $this->actingAs($this->user2)->delete(route('milestones.destroy', $milestone->id))->assertStatus(403);
    expect($milestone->fresh())->not->toBeNull();
});

test('user cannot mutate another users calendar events', function () {
    $event = CalendarEvent::create([
        'user_id' => $this->user1->id,
        'title' => 'Confidential Meeting',
        'start_time' => Carbon::now(),
        'end_time' => Carbon::now()->addHour(),
    ]);

    $this->actingAs($this->user2)->put(route('calendar.events.update', $event->id), [
        'title' => 'Hacked Event',
        'start_time' => Carbon::now()->toIso8601String(),
        'end_time' => Carbon::now()->addHour()->toIso8601String(),
    ])->assertStatus(403);

    $this->actingAs($this->user2)->delete(route('calendar.events.destroy', $event->id))->assertStatus(403);
    expect($event->fresh())->not->toBeNull();
});

test('user cannot mutate another users habits or submit check-ins', function () {
    $habit = Habit::create([
        'user_id' => $this->user1->id,
        'name' => 'Prayer',
        'category' => 'Spiritual Life',
        'frequency' => 'daily',
        'target_days_per_week' => 7,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->user2)->put(route('habits.update', $habit->id), [
        'name' => 'Altered Habit',
        'frequency' => 'weekly',
        'target_days_per_week' => 1,
    ])->assertStatus(403);

    $this->actingAs($this->user2)->post(route('habits.check-in', $habit->id), [
        'date' => Carbon::today()->toDateString(),
        'status' => 'completed',
    ])->assertStatus(403);

    $this->actingAs($this->user2)->delete(route('habits.destroy', $habit->id))->assertStatus(403);
    expect($habit->fresh()->is_archived)->toBeFalse();
});

test('user cannot modify another users daily plan or weekly review', function () {
    $plan = DailyPlan::create([
        'user_id' => $this->user1->id,
        'date' => Carbon::today()->toDateString(),
    ]);

    $task = Task::create([
        'user_id' => $this->user2->id,
        'title' => 'User 2 Task',
        'priority' => 'medium',
        'status' => 'not_started',
    ]);

    // Attempt to update plan details
    $this->actingAs($this->user2)->put(route('my-day.update', $plan->id), [
        'notes' => 'Tampered notes',
    ])->assertStatus(403);

    // Attempt to add task to another user's plan
    $this->actingAs($this->user2)->post(route('my-day.add-task', $plan->id), [
        'task_id' => $task->id,
    ])->assertStatus(403);

    // Weekly review modification
    $review = WeeklyReview::create([
        'user_id' => $this->user1->id,
        'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
        'week_end_date' => Carbon::today()->endOfWeek()->toDateString(),
    ]);

    $this->actingAs($this->user2)->put(route('weekly-review.update', $review->id), [
        'notes' => 'Hacked review notes',
    ])->assertStatus(403);
});
