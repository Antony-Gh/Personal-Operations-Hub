<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyReview;

class WeeklyReviewPolicy
{
    public function view(User $user, WeeklyReview $weeklyReview): bool
    {
        return $weeklyReview->user_id === $user->id;
    }

    public function update(User $user, WeeklyReview $weeklyReview): bool
    {
        return $weeklyReview->user_id === $user->id;
    }

    public function delete(User $user, WeeklyReview $weeklyReview): bool
    {
        return $weeklyReview->user_id === $user->id;
    }
}

