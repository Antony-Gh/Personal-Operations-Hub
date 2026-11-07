<?php

namespace App\Policies;

use App\Models\FocusSession;
use App\Models\User;

class FocusSessionPolicy
{
    public function view(User $user, FocusSession $focusSession): bool
    {
        return $focusSession->user_id === $user->id;
    }

    public function update(User $user, FocusSession $focusSession): bool
    {
        return $focusSession->user_id === $user->id;
    }

    public function delete(User $user, FocusSession $focusSession): bool
    {
        return $focusSession->user_id === $user->id;
    }
}
