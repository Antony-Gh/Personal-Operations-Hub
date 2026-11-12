<?php

namespace App\Policies;

use App\Models\DailyPlan;
use App\Models\User;

class DailyPlanPolicy
{
    public function view(User $user, DailyPlan $dailyPlan): bool
    {
        return $dailyPlan->user_id === $user->id;
    }

    public function update(User $user, DailyPlan $dailyPlan): bool
    {
        return $dailyPlan->user_id === $user->id;
    }

    public function delete(User $user, DailyPlan $dailyPlan): bool
    {
        return $dailyPlan->user_id === $user->id;
    }
}
