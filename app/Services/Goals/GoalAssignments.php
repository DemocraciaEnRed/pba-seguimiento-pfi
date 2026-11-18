<?php

namespace App\Services\Goals;

use App\Goal;
use App\Objective;
use App\Services\Reports\ReportContinuity;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GoalAssignments
{
    public function assignResponsible(Goal $goal, User $member, User $actor): bool
    {
        Gate::forUser($actor)->authorize('manageAssignments', $goal);

        return $this->assign($goal, $member, $actor, 'responsible');
    }

    /** @param array<int, int> $destinations */
    public function removeResponsible(Goal $goal, User $member, User $actor, array $destinations = []): bool
    {
        Gate::forUser($actor)->authorize('manageAssignments', $goal);

        return $this->remove($goal, $member, $actor, 'responsible', $destinations);
    }

    public function assignReporter(Goal $goal, User $member, User $actor): bool
    {
        Gate::forUser($actor)->authorize('manageReporters', $goal);

        return $this->assign($goal, $member, $actor, 'reporter');
    }

    /** @param array<int, int> $destinations */
    public function removeReporter(Goal $goal, User $member, User $actor, array $destinations = []): bool
    {
        Gate::forUser($actor)->authorize('manageReporters', $goal);

        return $this->remove($goal, $member, $actor, 'reporter', $destinations);
    }

    public function assignReporterToObjective(Objective $objective, User $member, User $actor): int
    {
        Gate::forUser($actor)->authorize('isAdmin');
        $this->ensurePfiMember($member);

        $assignedGoalIds = DB::transaction(function () use ($objective, $member, $actor): array {
            User::query()->whereKey([$member->id, $actor->id])->orderBy('id')->lockForUpdate()->get();
            $actor->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize('isAdmin');
            $this->ensurePfiMember($member);
            $goals = $objective->goals()->orderBy('id')->lockForUpdate()->get(['goals.id']);
            $assignedGoalIds = [];

            foreach ($goals as $goal) {
                if ($goal->reporters()->whereKey($member->getKey())->exists()) {
                    continue;
                }

                $goal->reporters()->attach($member->getKey(), ['assigned_by' => $actor->getKey()]);
                $assignedGoalIds[] = $goal->getKey();
            }

            return $assignedGoalIds;
        });
        $member->forgetAuthorizationContext();
        $actor->forgetAuthorizationContext();

        if ($assignedGoalIds !== []) {
            $this->log(
                $actor,
                $member,
                "ha asignado a [{$member->fullname}] como Reportero en {$this->countLabel(count($assignedGoalIds))} del objetivo [{$objective->title}]",
                [
                    'objective_id' => $objective->getKey(),
                    'objective_title' => $objective->title,
                    'goal_ids' => $assignedGoalIds,
                    'role' => 'reporter',
                ],
            );
        }

        return count($assignedGoalIds);
    }

    private function assign(Goal $goal, User $member, User $actor, string $role): bool
    {
        $this->ensurePfiMember($member);
        $relation = $role === 'responsible' ? 'responsibles' : 'reporters';

        $assigned = DB::transaction(function () use ($goal, $member, $actor, $relation, $role): bool {
            User::query()->whereKey([$member->id, $actor->id])->orderBy('id')->lockForUpdate()->get();
            $lockedGoal = Goal::query()->lockForUpdate()->findOrFail($goal->getKey());
            $actor->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize($role === 'responsible' ? 'manageAssignments' : 'manageReporters', $lockedGoal);
            $this->ensurePfiMember($member);
            $assignedUsers = $lockedGoal->{$relation}();

            if ($assignedUsers->whereKey($member->getKey())->exists()) {
                return false;
            }

            $assignedUsers->attach($member->getKey(), ['assigned_by' => $actor->getKey()]);

            return true;
        });
        $member->forgetAuthorizationContext();
        $actor->forgetAuthorizationContext();

        if ($assigned) {
            $roleLabel = $role === 'responsible' ? 'Responsable' : 'Reportero';
            $this->log($actor, $member, "ha asignado a [{$member->fullname}] como {$roleLabel} a la meta [{$goal->title}]", [
                'goal_id' => $goal->getKey(),
                'goal_title' => $goal->title,
                'objective_id' => $goal->objective_id,
                'role' => $role,
            ]);
        }

        return $assigned;
    }

    /** @param array<int, int> $destinations */
    private function remove(Goal $goal, User $member, User $actor, string $role, array $destinations): bool
    {
        $relation = $role === 'responsible' ? 'responsibles' : 'reporters';

        $removed = DB::transaction(function () use ($goal, $member, $actor, $relation, $role, $destinations): bool {
            User::query()->whereKey([$member->id, $actor->id, ...array_values($destinations)])
                ->orderBy('id')->lockForUpdate()->get();
            $lockedGoal = Goal::query()->lockForUpdate()->findOrFail($goal->getKey());
            $actor->forgetAuthorizationContext();
            $member->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize($role === 'responsible' ? 'manageAssignments' : 'manageReporters', $lockedGoal);
            $assignedUsers = $lockedGoal->{$relation}();

            if (! $assignedUsers->whereKey($member->getKey())->exists()) {
                return false;
            }

            app(ReportContinuity::class)->transferForRemoval($member, $actor, $destinations, $lockedGoal, $role);
            $assignedUsers->detach($member->getKey());

            return true;
        });
        $member->forgetAuthorizationContext();
        $actor->forgetAuthorizationContext();

        if ($removed) {
            $roleLabel = $role === 'responsible' ? 'Responsable' : 'Reportero';
            $this->log($actor, $member, "ha quitado a [{$member->fullname}] como {$roleLabel} de la meta [{$goal->title}]", [
                'goal_id' => $goal->getKey(),
                'goal_title' => $goal->title,
                'objective_id' => $goal->objective_id,
                'role' => $role,
            ]);
        }

        return $removed;
    }

    private function ensurePfiMember(User $member): void
    {
        if (! $member->pfiMembership()->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'Solo se puede asignar a integrantes PFI.',
            ]);
        }
    }

    private function countLabel(int $count): string
    {
        return $count === 1 ? '1 meta existente' : "{$count} metas existentes";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(User $actor, User $member, string $message, array $context): void
    {
        Log::channel('mysql')->info("[{$actor->fullname}] {$message}", $context + [
            'member_id' => $member->getKey(),
            'member_fullname' => $member->fullname,
            'member_email' => $member->email,
            'user_id' => $actor->getKey(),
            'user_fullname' => $actor->fullname,
            'user_email' => $actor->email,
        ]);
    }
}
