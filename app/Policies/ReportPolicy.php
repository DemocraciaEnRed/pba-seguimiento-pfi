<?php

namespace App\Policies;

use App\Goal;
use App\Report;
use App\User;

class ReportPolicy
{
    public function create(User $user, Goal $goal): bool
    {
        return $user->hasRole('admin') || $user->isAssignedToGoal($goal->id);
    }

    public function viewInManagement(User $user, Report $report): bool
    {
        return $this->update($user, $report)
            || ($report->isApproved()
                && $user->hasAssignedGoalInObjective($report->goal->objective_id)
                && $report->goal->isVisibleTo($user));
    }

    public function update(User $user, Report $report): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return ! $report->isApproved()
            && ($user->isResponsibleForGoal($report->goal_id)
                || ($user->isReporterForGoal($report->goal_id)
                    && ($report->author_id === $user->id || $report->attendant_id === $user->id)));
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->hasRole('admin')
            || (! $report->isApproved() && $report->author_id === $user->id && $this->update($user, $report));
    }
}
