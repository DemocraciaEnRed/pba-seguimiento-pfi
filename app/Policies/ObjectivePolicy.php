<?php

namespace App\Policies;

use App\Objective;
use App\User;

class ObjectivePolicy
{
    public function accessManagement(User $user, Objective $objective): bool
    {
        return $user->hasRole('admin') || $user->hasAssignedGoalInObjective($objective->id);
    }

    public function update(User $user, Objective $objective): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Objective $objective): bool
    {
        return $this->update($user, $objective);
    }

    public function changeVisibility(User $user, Objective $objective): bool
    {
        return $user->hasRole('admin');
    }
}
