<?php

namespace App\Policies;

use App\Models\BadHabit;
use App\Models\User;

class BadHabitPolicy
{
    public function view(User $user, BadHabit $badHabit): bool
    {
        return $badHabit->user_id === $user->id;
    }

    public function update(User $user, BadHabit $badHabit): bool
    {
        return $badHabit->user_id === $user->id;
    }

    public function delete(User $user, BadHabit $badHabit): bool
    {
        return $badHabit->user_id === $user->id;
    }
}
