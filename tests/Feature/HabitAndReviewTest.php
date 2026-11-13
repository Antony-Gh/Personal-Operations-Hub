<?php

use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;

it('records habit daily check-in with partial or completed status', function () {
    $user = User::factory()->create();
    $habit = Habit::create([
        'user_id' => $user->id,
        'name' => 'Prayer',
        'category' => 'Spiritual Life',
        'frequency' => 'daily',
        'target_days_per_week' => 7,
    ]);

    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post(route('habits.check-in', $habit->id), [
        'date' => $today,
        'status' => 'completed',
        'notes' => 'Quiet morning prayer',
    ]);

    $response->assertSessionHas('success');
    $checkIn = HabitCheckIn::where('habit_id', $habit->id)->whereDate('date', $today)->first();
    expect($checkIn)->not->toBeNull();
    expect($checkIn->status)->toBe('completed');
    expect($checkIn->notes)->toBe('Quiet morning prayer');
});

it('saves weekly review reflections and answers', function () {
    $user = User::factory()->create();
    $today = Carbon::today();
    $review = WeeklyReview::create([
        'user_id' => $user->id,
        'week_start_date' => $today->copy()->startOfWeek()->toDateString(),
        'week_end_date' => $today->copy()->endOfWeek()->toDateString(),
        'responses' => [],
        'is_completed' => false,
    ]);

    $response = $this->actingAs($user)->put(route('weekly-review.update', $review->id), [
        'responses' => [
            'What are the three most meaningful things I accomplished?' => 'Delivered firmware PR, aced test',
            'What helped me recover energy?' => 'Taking an evening walk without phone',
        ],
        'notes' => 'Solid week overall',
        'is_completed' => true,
    ]);

    $response->assertSessionHas('success');
    expect($review->fresh()->is_completed)->toBeTrue();
    expect($review->fresh()->responses['What helped me recover energy?'])->toBe('Taking an evening walk without phone');
});

it('logs leisure phone usage minutes', function () {
    $user = User::factory()->create();
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post(route('phone-usage.store'), [
        'date' => $today,
        'minutes' => 40,
        'notes' => 'Messaging friends and checking docs',
    ]);

    $response->assertSessionHas('success');
    $log = PhoneUsageLog::where('user_id', $user->id)->whereDate('date', $today)->first();
    expect($log)->not->toBeNull();
    expect($log->minutes)->toBe(40);
});

it('excludes current week final day and includes strictly next calendar week in upcoming tasks', function () {
    Carbon::setTestNow('2026-11-11 12:00:00'); // Wednesday

    $user = User::factory()->create();
    $user->ensureSetting()->update(['week_start' => 1]); // Monday start
    $otherUser = User::factory()->create();

    // Current week: 2026-11-09 (Mon) to 2026-11-15 (Sun)
    // Next week:    2026-11-16 (Mon) to 2026-11-22 (Sun)

    $taskCurrentMid = Task::create(['user_id' => $user->id, 'title' => 'Mid Current Week', 'due_date' => '2026-11-11']);
    $taskCurrentEnd = Task::create(['user_id' => $user->id, 'title' => 'Final Day Current Week', 'due_date' => '2026-11-15']);
    $taskNextStart = Task::create(['user_id' => $user->id, 'title' => 'Start Day Next Week', 'due_date' => '2026-11-16']);
    $taskNextEnd = Task::create(['user_id' => $user->id, 'title' => 'Final Day Next Week', 'due_date' => '2026-11-22']);
    $taskFarFuture = Task::create(['user_id' => $user->id, 'title' => 'Far Future Task', 'due_date' => '2026-11-23']);
    $taskNoDueDate = Task::create(['user_id' => $user->id, 'title' => 'No Due Date Task', 'due_date' => null]);
    $taskOtherUser = Task::create(['user_id' => $otherUser->id, 'title' => 'Other User Task', 'due_date' => '2026-11-18']);

    $response = $this->actingAs($user)->get(route('weekly-review.index'));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) use ($taskCurrentEnd, $taskCurrentMid, $taskNextStart, $taskNextEnd, $taskFarFuture, $taskOtherUser) {
        $page->component('WeeklyReview/Index')
            ->has('upcomingTasks', 2)
            ->where('upcomingTasks.0.id', $taskNextStart->id)
            ->where('upcomingTasks.1.id', $taskNextEnd->id);

        $upcomingIds = collect($page->toArray()['props']['upcomingTasks'])->pluck('id')->all();
        expect($upcomingIds)->not->toContain($taskCurrentEnd->id)
            ->and($upcomingIds)->not->toContain($taskCurrentMid->id)
            ->and($upcomingIds)->not->toContain($taskFarFuture->id)
            ->and($upcomingIds)->not->toContain($taskOtherUser->id);
    });

    Carbon::setTestNow();
});

it('correctly handles month and year boundary transitions for upcoming tasks in weekly review', function () {
    Carbon::setTestNow('2026-12-30 12:00:00'); // Wednesday

    $user = User::factory()->create();
    $user->ensureSetting()->update(['week_start' => 1]);

    // Current week: 2026-12-28 (Mon) to 2027-01-03 (Sun)
    // Next week:    2027-01-04 (Mon) to 2027-01-10 (Sun)

    $taskCurrentEnd = Task::create(['user_id' => $user->id, 'title' => 'Sunday End of Year Review', 'due_date' => '2027-01-03']);
    $taskNextStart = Task::create(['user_id' => $user->id, 'title' => 'Monday New Year Start', 'due_date' => '2027-01-04']);
    $taskNextEnd = Task::create(['user_id' => $user->id, 'title' => 'Sunday New Year End', 'due_date' => '2027-01-10']);
    $taskPastNext = Task::create(['user_id' => $user->id, 'title' => 'Beyond Next Week', 'due_date' => '2027-01-11']);

    $response = $this->actingAs($user)->get(route('weekly-review.index'));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) use ($taskCurrentEnd, $taskNextStart, $taskNextEnd, $taskPastNext) {
        $upcomingIds = collect($page->toArray()['props']['upcomingTasks'])->pluck('id')->all();

        expect($upcomingIds)->toContain($taskNextStart->id)
            ->and($upcomingIds)->toContain($taskNextEnd->id)
            ->and($upcomingIds)->not->toContain($taskCurrentEnd->id)
            ->and($upcomingIds)->not->toContain($taskPastNext->id);
    });

    Carbon::setTestNow();
});

it('respects sunday-start week setting when computing upcoming task boundaries', function () {
    Carbon::setTestNow('2026-11-11 12:00:00'); // Wednesday

    $user = User::factory()->create();
    $user->ensureSetting()->update(['week_start' => 0]); // Sunday start

    // Current week: 2026-11-08 (Sun) to 2026-11-14 (Sat)
    // Next week:    2026-11-15 (Sun) to 2026-11-21 (Sat)

    $taskSatCurrent = Task::create(['user_id' => $user->id, 'title' => 'Sat Current Week', 'due_date' => '2026-11-14']);
    $taskSunNext = Task::create(['user_id' => $user->id, 'title' => 'Sun Next Week', 'due_date' => '2026-11-15']);
    $taskSatNext = Task::create(['user_id' => $user->id, 'title' => 'Sat Next Week', 'due_date' => '2026-11-21']);
    $taskSunAfter = Task::create(['user_id' => $user->id, 'title' => 'Sun Week After', 'due_date' => '2026-11-22']);

    $response = $this->actingAs($user)->get(route('weekly-review.index'));
    $response->assertOk();

    $response->assertInertia(function (AssertableInertia $page) use ($taskSatCurrent, $taskSunNext, $taskSatNext, $taskSunAfter) {
        $upcomingIds = collect($page->toArray()['props']['upcomingTasks'])->pluck('id')->all();

        expect($upcomingIds)->toContain($taskSunNext->id)
            ->and($upcomingIds)->toContain($taskSatNext->id)
            ->and($upcomingIds)->not->toContain($taskSatCurrent->id)
            ->and($upcomingIds)->not->toContain($taskSunAfter->id);
    });

    Carbon::setTestNow();
});
