<?php

namespace Database\Factories;

use App\Models\BadHabit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadHabit>
 */
class BadHabitFactory extends Factory
{
    protected $model = BadHabit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category' => 'Digital Discipline',
            'trigger_cue' => 'Fatigue after late study session',
            'replacement_action' => 'Drink water and read 5 pages offline',
            'linked_habit_id' => null,
            'minutes_saved_per_day' => 0,
            'money_saved_per_day' => 0,
            'has_daily_limit' => false,
            'daily_limit_value' => null,
            'daily_limit_unit' => 'times',
            'target_clean_days_per_week' => 7,
            'sort_order' => 1,
            'is_archived' => false,
        ];
    }
}
