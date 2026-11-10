<?php

namespace App\Policies;

use App\Models\PhoneUsageLog;
use App\Models\User;

class PhoneUsageLogPolicy
{
    public function view(User $user, PhoneUsageLog $log): bool
    {
        return $log->user_id === $user->id;
    }

    public function update(User $user, PhoneUsageLog $log): bool
    {
        return $log->user_id === $user->id;
    }

    public function delete(User $user, PhoneUsageLog $log): bool
    {
        return $log->user_id === $user->id;
    }
}

