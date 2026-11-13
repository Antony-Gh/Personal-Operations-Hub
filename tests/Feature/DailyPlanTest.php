<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\PhoneUsageLog;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;

it('adds tasks to daily plan and enforces maximum 3 priorities', function () {
    $user = User::factory()->create();
    $today = Carbon::today()->toDateString();

    $plan = DailyPlan::create(['user_id' => $user->id, 'date' => $today]);

    $tasks = [];
    for ($i = 1; $i <= 4; $i++) {
        $tasks[] = Task::create([
            'user_id' => $user->id,
            'title' => "Task {$i}",
            'priority' => 'high',
            'status' => 'not_started',
        ]);
    }

    // Add 3 priority tasks
    $this->actingAs($user)->post(route('my-day.add-task', $plan->id), ['task_id' => $tasks[0]->id, 'is_priority' => true]);
    $this->actingAs($user)->post(route('my-day.add-task', $plan->id), ['task_id' => $tasks[1]->id, 'is_priority' => true]);
    $this->actingAs($user)->post(route('my-day.add-task', $plan->id), ['task_id' => $tasks[2]->id, 'is_priority' => true]);

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('is_priority', true)->count())->toBe(3);

    // 4th priority should be rejected
    $response = $this->actingAs($user)->post(route('my-day.add-task', $plan->id), [
        'task_id' => $tasks[3]->id,
        'is_priority' => true,
    ]);

    $response->assertSessionHas('error');
});

it('updates fallback plan and personal recovery notes', function () {
    $user = User::factory()->create();
    $today = Carbon::today()->toDateString();
    $plan = DailyPlan::create(['user_id' => $user->id, 'date' => $today]);

    $this->actingAs($user)->put(route('my-day.update', $plan->id), [
        'fallback_plan' => 'Focus strictly on assignment submission',
        'personal_recovery_notes' => '15 minute evening stroll',
    ]);

    expect($plan->fresh()->fallback_plan)->toBe('Focus strictly on assignment submission');
    expect($plan->fresh()->personal_recovery_notes)->toBe('15 minute evening stroll');
});

it('removes task association from active daily plan when rescheduled to a different date', function () {
    $user = User::factory()->create();
    $planDate = '2026-11-14';
    $newDate = '2026-11-15';

    $plan = DailyPlan::create(['user_id' => $user->id, 'date' => $planDate]);
    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Important Thesis Milestone',
        'description' => 'Write methodology section',
        'priority' => 'high',
        'status' => 'in_progress',
        'due_date' => $planDate,
    ]);

    DailyPlanTask::create([
        'daily_plan_id' => $plan->id,
        'task_id' => $task->id,
        'is_priority' => true,
        'sort_order' => 1,
    ]);

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task->id)->exists())->toBeTrue();

    $response = $this->actingAs($user)->post(route('tasks.reschedule', $task), [
        'due_date' => $newDate,
        'daily_plan_id' => $plan->id,
    ]);

    $response->assertSessionHas('success', 'Task rescheduled.');

    // Due date updated
    expect($task->fresh()->due_date->toDateString())->toBe($newDate);

    // Association with active daily plan removed
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task->id)->exists())->toBeFalse();

    // Task itself preserved
    expect($task->fresh()->title)->toBe('Important Thesis Milestone')
        ->and($task->fresh()->description)->toBe('Write methodology section')
        ->and($task->fresh()->priority)->toBe('high')
        ->and($task->fresh()->status)->toBe('in_progress');
});

it('preserves daily plan association when task is rescheduled to the same date', function () {
    $user = User::factory()->create();
    $planDate = '2026-11-14';

    $plan = DailyPlan::create(['user_id' => $user->id, 'date' => $planDate]);
    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Keep for Today',
        'priority' => 'medium',
        'due_date' => $planDate,
    ]);

    DailyPlanTask::create([
        'daily_plan_id' => $plan->id,
        'task_id' => $task->id,
        'is_priority' => false,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('tasks.reschedule', $task), [
        'due_date' => $planDate,
        'daily_plan_id' => $plan->id,
    ]);

    $response->assertSessionHas('success');

    // Association remains intact
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task->id)->exists())->toBeTrue();
});

it('does not detach other tasks from the active plan when one task is rescheduled', function () {
    $user = User::factory()->create();
    $planDate = '2026-11-14';

    $plan = DailyPlan::create(['user_id' => $user->id, 'date' => $planDate]);
    $task1 = Task::create(['user_id' => $user->id, 'title' => 'Task to Reschedule', 'due_date' => $planDate]);
    $task2 = Task::create(['user_id' => $user->id, 'title' => 'Task to Stay', 'due_date' => $planDate]);

    DailyPlanTask::create(['daily_plan_id' => $plan->id, 'task_id' => $task1->id, 'sort_order' => 1]);
    DailyPlanTask::create(['daily_plan_id' => $plan->id, 'task_id' => $task2->id, 'sort_order' => 2]);

    $this->actingAs($user)->post(route('tasks.reschedule', $task1), [
        'due_date' => '2026-11-16',
        'daily_plan_id' => $plan->id,
    ]);

    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task1->id)->exists())->toBeFalse();
    expect(DailyPlanTask::where('daily_plan_id', $plan->id)->where('task_id', $task2->id)->exists())->toBeTrue();
});

it('preserves associations between the task and other daily plans', function () {
    $user = User::factory()->create();
    $planToday = DailyPlan::create(['user_id' => $user->id, 'date' => '2026-11-14']);
    $planOther = DailyPlan::create(['user_id' => $user->id, 'date' => '2026-11-15']);

    $task = Task::create(['user_id' => $user->id, 'title' => 'Multi-plan Task', 'due_date' => '2026-11-14']);

    DailyPlanTask::create(['daily_plan_id' => $planToday->id, 'task_id' => $task->id]);
    DailyPlanTask::create(['daily_plan_id' => $planOther->id, 'task_id' => $task->id]);

    $this->actingAs($user)->post(route('tasks.reschedule', $task), [
        'due_date' => '2026-11-18',
        'daily_plan_id' => $planToday->id,
    ]);

    // Detached only from planToday
    expect(DailyPlanTask::where('daily_plan_id', $planToday->id)->where('task_id', $task->id)->exists())->toBeFalse();
    // Intact in planOther
    expect(DailyPlanTask::where('daily_plan_id', $planOther->id)->where('task_id', $task->id)->exists())->toBeTrue();
});

it('prevents unauthorized users from rescheduling another user task', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $task = Task::create(['user_id' => $owner->id, 'title' => 'Secret Task', 'due_date' => '2026-11-14']);

    $response = $this->actingAs($otherUser)->post(route('tasks.reschedule', $task), [
        'due_date' => '2026-11-20',
    ]);

    $response->assertForbidden();
    expect($task->fresh()->due_date->toDateString())->toBe('2026-11-14');
});

it('handles missing or already-detached associations safely and idempotently', function () {
    $user = User::factory()->create();
    $task = Task::create(['user_id' => $user->id, 'title' => 'Detached Task', 'due_date' => '2026-11-14']);

    // Task is not attached to any plan
    $response = $this->actingAs($user)->post(route('tasks.reschedule', $task), [
        'due_date' => '2026-11-20',
        'daily_plan_id' => 99999,
    ]);

    $response->assertSessionHas('success');
    expect($task->fresh()->due_date->toDateString())->toBe('2026-11-20');

    // Repeated call is idempotent
    $responseRepeat = $this->actingAs($user)->post(route('tasks.reschedule', $task), [
        'due_date' => '2026-11-20',
    ]);
    $responseRepeat->assertSessionHas('success');
});

it('passes phone usage log and target phone minutes props to My Day page', function () {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['phone_usage_target_minutes' => 45]);
    $today = Carbon::today()->toDateString();

    PhoneUsageLog::create([
        'user_id' => $user->id,
        'date' => $today,
        'minutes' => 30,
        'notes' => 'Evening podcast',
    ]);

    $response = $this->actingAs($user)->get(route('my-day.index', ['date' => $today]));
    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('MyDay/Index')
        ->has('phoneUsage')
        ->where('phoneUsage.minutes', 30)
        ->where('phoneUsage.notes', 'Evening podcast')
        ->where('targetPhoneMinutes', 45)
    );
});

it('handles null phone usage gracefully on My Day page when no log exists', function () {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['phone_usage_target_minutes' => 60]);
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->get(route('my-day.index', ['date' => $today]));
    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('MyDay/Index')
        ->where('phoneUsage', null)
        ->where('targetPhoneMinutes', 60)
    );
});
