<?php

namespace Database\Factories;

use App\Models\FocusSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FocusSession>
 */
class FocusSessionFactory extends Factory
{
    protected $model = FocusSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'task_id' => null,
            'mode' => 'focus',
            'planned_minutes' => 25,
            'elapsed_seconds' => 0,
            'logged_minutes' => 0,
            'status' => 'running',
            'started_at' => now(),
            'last_resumed_at' => now(),
        ];
    }
}
