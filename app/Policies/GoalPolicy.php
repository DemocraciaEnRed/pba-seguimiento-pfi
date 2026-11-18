<?php

namespace App\Policies;

use App\Goal;
use App\Objective;
use App\User;

class GoalPolicy
{
    public function view(User $user, Goal $goal): bool
    {
        return $goal->isVisibleTo($user);
    }

    public function manageAssignments(User $user, Goal $goal): bool
    {
        return $user->hasRole('admin');
    }

    public function manageReporters(User $user, Goal $goal): bool
    {
        return $user->hasRole('admin')
            || $user->isResponsibleForGoal($goal->getKey());
    }

    public function create(User $user, Objective $objective): bool
    {
        return $user->can('update', $objective);
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->can('update', $goal->objective);
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->update($user, $goal);
    }
}
