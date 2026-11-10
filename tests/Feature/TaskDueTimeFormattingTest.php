<?php

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Support\UserDateTime;
use Inertia\Testing\AssertableInertia as Assert;

test('UserDateTime formatTimeAmPm correctly formats various time formats to 12-hour AM/PM', function (): void {
    expect(UserDateTime::formatTimeAmPm(null))->toBeNull();
    expect(UserDateTime::formatTimeAmPm(''))->toBeNull();
    expect(UserDateTime::formatTimeAmPm('14:30'))->toBe('2:30 PM');
    expect(UserDateTime::formatTimeAmPm('09:15'))->toBe('9:15 AM');
    expect(UserDateTime::formatTimeAmPm('00:00'))->toBe('12:00 AM');
    expect(UserDateTime::formatTimeAmPm('12:00'))->toBe('12:00 PM');
    expect(UserDateTime::formatTimeAmPm('23:59:00'))->toBe('11:59 PM');
    expect(UserDateTime::formatTimeAmPm('08:05:30'))->toBe('8:05 AM');
    expect(UserDateTime::formatTimeAmPm('2:30 PM'))->toBe('2:30 PM');
    expect(UserDateTime::formatTimeAmPm('09:15 am'))->toBe('9:15 AM');
});

test('Task formatted_due_time attribute and dueTimeAmPm method format due time with AM/PM', function (): void {
    $user = User::factory()->create();

    $taskAfternoon = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Afternoon Task',
        'due_date' => '2026-11-10',
        'due_time' => '14:30',
    ]);

    $taskMorning = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Morning Task',
        'due_date' => '2026-11-10',
        'due_time' => '09:05',
    ]);

    $taskNoTime = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'No Time Task',
        'due_date' => '2026-11-10',
        'due_time' => null,
    ]);

    expect($taskAfternoon->formatted_due_time)->toBe('2:30 PM');
    expect($taskAfternoon->dueTimeAmPm())->toBe('2:30 PM');
    expect($taskAfternoon->toArray())->toHaveKey('formatted_due_time', '2:30 PM');

    expect($taskMorning->formatted_due_time)->toBe('9:05 AM');
    expect($taskMorning->dueTimeAmPm())->toBe('9:05 AM');
    expect($taskMorning->toArray())->toHaveKey('formatted_due_time', '9:05 AM');

    expect($taskNoTime->formatted_due_time)->toBeNull();
    expect($taskNoTime->dueTimeAmPm())->toBeNull();
    expect($taskNoTime->toArray())->toHaveKey('formatted_due_time', null);
});

test('ProjectMilestone formatted_due_time attribute and dueTimeAmPm method format due time with AM/PM', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $milestone = ProjectMilestone::create([
        'project_id' => $project->id,
        'title' => 'Milestone Beta',
        'due_date' => '2026-11-15',
        'due_time' => '17:45',
        'is_completed' => false,
        'sort_order' => 1,
    ]);

    expect($milestone->formatted_due_time)->toBe('5:45 PM');
    expect($milestone->dueTimeAmPm())->toBe('5:45 PM');
    expect($milestone->toArray())->toHaveKey('formatted_due_time', '5:45 PM');
});

test('Tasks Index returns formatted due time in task payload for frontend AM/PM rendering', function (): void {
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Team Sync',
        'due_date' => '2026-11-10',
        'due_time' => '15:30',
        'status' => 'not_started',
    ]);

    $this->actingAs($user)
        ->get('/tasks')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tasks/Index')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.due_time', '15:30')
            ->where('tasks.data.0.formatted_due_time', '3:30 PM')
        );
});
