<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addDays(14)->toDateString(),
            'reminders_enabled' => true,
            'overdue_reminders_enabled' => true,
        ];
    }
}
