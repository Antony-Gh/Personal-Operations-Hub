<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

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
