<?php

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
});

test('daily plan policy prevents unauthorized updates and cross-user task manipulation', function (): void {
    $alicePlan = DailyPlan::create([
        'user_id' => $this->alice->id,
        'date' => Carbon::today()->toDateString(),
    ]);

    $aliceTask = Task::factory()->create([
        'user_id' => $this->alice->id,
        'title' => 'Alice Private Task',
    ]);

    $bobTask = Task::factory()->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob Private Task',
    ]);

    DailyPlanTask::create([
        'daily_plan_id' => $alicePlan->id,
        'task_id' => $aliceTask->id,
        'sort_order' => 1,
        'is_priority' => false,
    ]);

    // Bob cannot update Alice's plan details
    $this->actingAs($this->bob)
        ->put(route('my-day.update', $alicePlan), ['notes' => 'Hacked Notes'])
        ->assertForbidden();

    // Bob cannot add a task to Alice's plan
    $this->actingAs($this->bob)
        ->post(route('my-day.add-task', $alicePlan), ['task_id' => $bobTask->id])
        ->assertForbidden();

    // Bob cannot toggle priority in Alice's plan
    $this->actingAs($this->bob)
        ->post(route('my-day.toggle-priority', [$alicePlan, $aliceTask]))
        ->assertForbidden();

    // Alice cannot toggle priority using Bob's task ID on Alice's plan
    $this->actingAs($this->alice)
        ->post(route('my-day.toggle-priority', [$alicePlan, $bobTask]))
        ->assertForbidden();

    // Bob cannot remove task from Alice's plan
    $this->actingAs($this->bob)
        ->delete(route('my-day.remove-task', [$alicePlan, $aliceTask]))
        ->assertForbidden();

    // Bob cannot update time-block on Alice's plan
    $this->actingAs($this->bob)
        ->patch(route('my-day.time-block', [$alicePlan, $aliceTask]), [
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ])
        ->assertForbidden();

    // Alice cannot time-block Bob's task
    $this->actingAs($this->alice)
        ->patch(route('my-day.time-block', [$alicePlan, $bobTask]), [
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ])
        ->assertForbidden();

    // Bob cannot reschedule Alice's task
    $this->actingAs($this->bob)
        ->post(route('tasks.reschedule', $aliceTask), [
            'due_date' => Carbon::tomorrow()->toDateString(),
        ])
        ->assertForbidden();
});

test('weekly review policy prevents unauthorized review modifications', function (): void {
    $aliceReview = WeeklyReview::create([
        'user_id' => $this->alice->id,
        'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
        'week_end_date' => Carbon::today()->endOfWeek()->toDateString(),
        'responses' => ['prompt_1' => 'Original notes'],
    ]);

    // Bob cannot update Alice's weekly review
    $this->actingAs($this->bob)
        ->put(route('weekly-review.update', $aliceReview), [
            'responses' => ['prompt_1' => 'Tampered notes'],
            'notes' => 'Tampered summary',
        ])
        ->assertForbidden();

    expect($aliceReview->fresh()->responses['prompt_1'])->toBe('Original notes');
});

test('project milestone policy prevents unauthorized toggling and deletion', function (): void {
    $aliceProject = Project::factory()->create([
        'user_id' => $this->alice->id,
        'name' => 'Alice Classified Project',
    ]);

    $milestone = ProjectMilestone::create([
        'project_id' => $aliceProject->id,
        'title' => 'Critical Architecture Signoff',
        'is_completed' => false,
        'sort_order' => 1,
    ]);

    // Bob cannot toggle Alice's milestone
    $this->actingAs($this->bob)
        ->post(route('milestones.toggle', $milestone))
        ->assertForbidden();

    expect($milestone->fresh()->is_completed)->toBeFalse();

    // Bob cannot delete Alice's milestone
    $this->actingAs($this->bob)
        ->delete(route('milestones.destroy', $milestone))
        ->assertForbidden();

    expect(ProjectMilestone::where('id', $milestone->id)->exists())->toBeTrue();
});
