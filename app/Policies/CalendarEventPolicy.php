<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function view(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id;
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id;
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id;
    }
}
