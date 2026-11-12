<?php

namespace App\Policies;

use App\Models\ProjectMilestone;
use App\Models\User;

class ProjectMilestonePolicy
{
    public function view(User $user, ProjectMilestone $milestone): bool
    {
        return $milestone->project?->user_id === $user->id;
    }

    public function update(User $user, ProjectMilestone $milestone): bool
    {
        return $milestone->project?->user_id === $user->id;
    }

    public function delete(User $user, ProjectMilestone $milestone): bool
    {
        return $milestone->project?->user_id === $user->id;
    }
}
