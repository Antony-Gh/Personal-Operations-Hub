<?php

use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\Carbon;

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

