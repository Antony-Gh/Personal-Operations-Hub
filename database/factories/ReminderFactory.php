<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'remindable_type' => Task::class,
            'remindable_id' => Task::factory(),
            'reminder_key' => 'preset:15m:'.fake()->unique()->numerify('########'),
            'category' => 'task_reminders',
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(6),
            'offset_label' => '15 minutes before',
            'is_critical' => false,
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'attempts' => 0,
        ];
    }
}
