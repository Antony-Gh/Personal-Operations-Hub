<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->randomElement(['high', 'medium', 'low']),
            'status' => 'not_started',
            'due_date' => now()->addDays(2)->toDateString(),
            'estimated_duration' => 30,
            'is_recurring' => false,
            'recurrence_interval' => 1,
            'recurrence_end_type' => 'never',
        ];
    }
}
