<?php

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated user can create update archive and restore a bad habit', function (): void {
    $user = User::factory()->create();

    $createResponse = $this->actingAs($user)->post(route('bad-habits.store'), [
        'name' => 'Late-Night Doomscrolling',
        'description' => 'Scrolling social feeds after 11 PM',
        'category' => 'Digital Discipline',
        'trigger_cue' => 'Boredom in bed with phone nearby',
        'replacement_action' => 'Charge phone in desk drawer and read 5 pages',
        'has_daily_limit' => false,
        'target_clean_days_per_week' => 7,
    ]);

    $createResponse->assertRedirect();

    $badHabit = BadHabit::where('user_id', $user->id)->firstOrFail();
    expect($badHabit->name)->toBe('Late-Night Doomscrolling')
        ->and($badHabit->trigger_cue)->toBe('Boredom in bed with phone nearby')
        ->and($badHabit->replacement_action)->toBe('Charge phone in desk drawer and read 5 pages')
        ->and($badHabit->is_archived)->toBeFalse();

    $this->actingAs($user)->put(route('bad-habits.update', $badHabit), [
        'name' => 'Nighttime Social Scrolling',
        'description' => 'Updated description',
        'category' => 'Sleep & Focus',
        'trigger_cue' => 'Post-study fatigue',
        'replacement_action' => 'Herbal tea and paper book',
        'has_daily_limit' => true,
        'daily_limit_value' => 15,
        'daily_limit_unit' => 'minutes',
        'target_clean_days_per_week' => 6,
    ])->assertRedirect();

    $badHabit->refresh();
    expect($badHabit->name)->toBe('Nighttime Social Scrolling')
        ->and($badHabit->has_daily_limit)->toBeTrue()
        ->and($badHabit->daily_limit_value)->toBe(15)
        ->and($badHabit->daily_limit_unit)->toBe('minutes')
        ->and($badHabit->target_clean_days_per_week)->toBe(6);

    $this->actingAs($user)->delete(route('bad-habits.destroy', $badHabit))->assertRedirect();
    expect($badHabit->fresh()->is_archived)->toBeTrue();

    $this->actingAs($user)->post(route('bad-habits.restore', $badHabit))->assertRedirect();
    expect($badHabit->fresh()->is_archived)->toBeFalse();
});

test('bad habit creation validates daily limit value when daily limit mode is enabled', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('bad-habits.store'), [
        'name' => 'Sugary Energy Drinks',
        'has_daily_limit' => true,
        'daily_limit_value' => null,
        'daily_limit_unit' => 'times',
        'target_clean_days_per_week' => 7,
    ])->assertSessionHasErrors('daily_limit_value');
});

test('clean day streaks increment on clean hold steady on minor slip and reset on slipped while preserving best streak and reflections', function (): void {
    $user = User::factory()->create();
    $today = $user->localToday();

    $badHabit = BadHabit::factory()->create([
        'user_id' => $user->id,
        'name' => 'Impulse Snacking',
        'target_clean_days_per_week' => 7,
        'created_at' => $today->copy()->subDays(4)->startOfDay(),
    ]);

    // Day -4, -3, -2: clean (3-day streak)
    foreach ([4, 3, 2] as $daysAgo) {
        $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
            'date' => $today->copy()->subDays($daysAgo)->toDateString(),
            'status' => 'clean',
        ])->assertRedirect();
    }

    // Day -1: minor_slip (holds streak at 3)
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $today->copy()->subDays(1)->toDateString(),
        'status' => 'minor_slip',
        'trigger_note' => 'Afternoon stress',
        'notes' => 'Paused after one small snack and switched to water.',
    ])->assertRedirect();

    $this->actingAs($user)->get(route('bad-habits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BadHabits/Index')
            ->where('badHabits.0.current_clean_streak', 3)
            ->where('badHabits.0.best_clean_streak', 3)
        );

    // Today: full slip resets current_clean_streak to 0 while best_clean_streak stays 3
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $today->toDateString(),
        'status' => 'slipped',
        'trigger_note' => 'Skipped lunch',
        'notes' => 'Prep lunch earlier tomorrow.',
    ])->assertRedirect();

    $this->actingAs($user)->get(route('bad-habits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BadHabits/Index')
            ->where('badHabits.0.current_clean_streak', 0)
            ->where('badHabits.0.best_clean_streak', 3)
            ->where('badHabits.0.slips_count', 1)
            ->where('badHabits.0.minor_slips_count', 1)
            ->has('badHabits.0.recent_reflections', 2)
        );
});

test('daily limit cap mode automatically evaluates observed value into clean or slipped status', function (): void {
    $user = User::factory()->create();
    $todayStr = $user->localTodayDate();

    $badHabit = BadHabit::factory()->create([
        'user_id' => $user->id,
        'name' => 'Coffee Cups',
        'has_daily_limit' => true,
        'daily_limit_value' => 2,
        'daily_limit_unit' => 'times',
    ]);

    // Within cap (2 <= 2) -> clean
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $todayStr,
        'observed_value' => 2,
    ])->assertRedirect();

    $checkIn = BadHabitCheckIn::where('bad_habit_id', $badHabit->id)->where('date', $todayStr)->firstOrFail();
    expect($checkIn->status)->toBe('clean')
        ->and($checkIn->observed_value)->toBe(2);

    // Exceeds cap (3 > 2) -> slipped
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $todayStr,
        'observed_value' => 3,
    ])->assertRedirect();

    $checkIn->refresh();
    expect($checkIn->status)->toBe('slipped')
        ->and($checkIn->observed_value)->toBe(3);
});

test('bad habits integrate with dashboard briefings command palette and json export import', function (): void {
    $user = User::factory()->create();
    $today = $user->localToday();

    $badHabit = BadHabit::factory()->create([
        'user_id' => $user->id,
        'name' => 'Procrastinating on YouTube',
        'trigger_cue' => 'Ambiguous task instructions',
        'replacement_action' => 'Write a 2-minute micro first step',
        'created_at' => $today->copy()->subDays(2)->startOfDay(),
    ]);

    foreach ([2, 1, 0] as $daysAgo) {
        BadHabitCheckIn::create([
            'user_id' => $user->id,
            'bad_habit_id' => $badHabit->id,
            'date' => $today->copy()->subDays($daysAgo)->toDateString(),
            'status' => 'clean',
        ]);
    }

    // Dashboard includes badHabits summary with 3-day clean streak
    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('badHabits', 1)
            ->where('badHabits.0.name', 'Procrastinating on YouTube')
            ->where('badHabits.0.current_clean_streak', 3)
        );

    // Briefings include bad_habits on daily briefing and bad_habit_summary + achievement on weekly digest
    $this->actingAs($user)->get(route('briefings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Briefings/Index')
            ->where('dailyBriefing.bad_habits.clean_count', 1)
            ->where('dailyBriefing.bad_habits.total_count', 1)
            ->has('weeklyDigest.bad_habit_summary.items', 1)
        );

    // Command palette searches bad habits by name and replacement action
    $this->actingAs($user)->getJson(route('command-palette.search', ['q' => 'micro first step']))
        ->assertOk()
        ->assertJsonCount(1, 'bad_habits')
        ->assertJsonPath('bad_habits.0.name', 'Procrastinating on YouTube');

    // JSON export includes bad_habits
    $exportResponse = $this->actingAs($user)->get(route('settings.export.json'));
    $exportResponse->assertOk();
    $exportedJson = json_decode($exportResponse->streamedContent(), true);
    expect($exportedJson['bad_habits'])->toHaveCount(1)
        ->and($exportedJson['bad_habits'][0]['name'])->toBe('Procrastinating on YouTube');
});

test('users cannot modify check in archive or restore another users bad habit', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $badHabit = BadHabit::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Private Habit to Break',
    ]);

    $this->actingAs($intruder)->put(route('bad-habits.update', $badHabit), [
        'name' => 'Hijacked',
        'target_clean_days_per_week' => 7,
    ])->assertForbidden();

    $this->actingAs($intruder)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $intruder->localTodayDate(),
        'status' => 'slipped',
    ])->assertForbidden();

    $this->actingAs($intruder)->delete(route('bad-habits.destroy', $badHabit))->assertForbidden();
    $this->actingAs($intruder)->post(route('bad-habits.restore', $badHabit))->assertForbidden();

    expect($badHabit->fresh()->name)->toBe('Private Habit to Break');
});
