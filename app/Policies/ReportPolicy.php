<?php

namespace App\Policies;

use App\Goal;
use App\Report;
use App\Services\Reports\ReviewStatus;
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
            || ($report->isValidated()
                && $report->goal->isVisibleTo($user)
                && ($user->hasRole('admin')
                    || $user->isResponsibleForGoal($report->goal_id)
                    || ($user->isReporterForGoal($report->goal_id)
                        && ($report->author_id === $user->id || $report->attendant_id === $user->id))))
            || ($report->isApproved()
                && $user->hasAssignedGoalInObjective($report->goal->objective_id)
                && $report->goal->isVisibleTo($user));
    }

    public function update(User $user, Report $report): bool
    {
        if ($user->hasRole('admin')) {
            return ! $report->isClosedInternally();
        }

        return ! $report->isApproved() && ! $report->isValidated()
            && ($user->isResponsibleForGoal($report->goal_id)
                || ($user->isReporterForGoal($report->goal_id)
                    && ($report->author_id === $user->id || $report->attendant_id === $user->id)));
    }

    public function delete(User $user, Report $report): bool
    {
        return ! $report->isClosedInternally()
            && ($user->hasRole('admin')
                || (! $report->isApproved() && ! $report->isValidated() && $report->author_id === $user->id && $this->update($user, $report)));
    }

    public function validate(User $user, Report $report): bool
    {
        return $report->review_status === ReviewStatus::Pending
            && ($user->hasRole('admin') || $user->isResponsibleForGoal($report->goal_id));
    }

    public function return(User $user, Report $report): bool
    {
        return $report->review_status === ReviewStatus::Pending
            && $user->isResponsibleForGoal($report->goal_id);
    }

    public function publish(User $user, Report $report): bool
    {
        return $user->hasRole('admin')
            && $report->isAwaitingAdministration()
            && $report->goal->visibility === 'public';
    }

    public function close(User $user, Report $report): bool
    {
        return $user->hasRole('admin')
            && $report->isAwaitingAdministration()
            && $report->goal->visibility === 'internal';
    }
}
