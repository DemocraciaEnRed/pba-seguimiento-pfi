<?php

namespace App\Services\Pfi;

use App\PfiCategory;
use App\Goal;
use App\Notifications\GoalAssignmentNotification;
use App\Services\Reports\ReportContinuity;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Manages the universe of PFI team members. Membership and categories never grant permissions.
 */
class PfiMemberships
{
    /**
     * Registers the person as a PFI member. Returns false when they already were one.
     *
     * @param  list<int>  $categoryIds
     */
    public function admit(User $member, array $categoryIds, User $actor): bool
    {
        $admitted = DB::transaction(function () use ($member, $categoryIds, $actor): bool {
            User::query()->whereKey([$member->id, $actor->id])->orderBy('id')->lockForUpdate()->get();

            if ($member->pfiMembership()->exists()) {
                return false;
            }

            $member->pfiMembership()->create(['added_by' => $actor->id]);
            $member->pfiCategories()->sync($categoryIds);

            return true;
        });

        if ($admitted) {
            $member->unsetRelation('pfiMembership')->unsetRelation('pfiCategories');
            $this->log($actor, $member, "ha dado de alta a [{$member->fullname}] como integrante PFI", [
                'pfi_categories' => $this->categoryNames($categoryIds),
            ]);
        }

        return $admitted;
    }

    /**
     * Replaces the categories of a PFI member. Returns false when nothing changed.
     *
     * @param  list<int>  $categoryIds
     */
    public function classify(User $member, array $categoryIds, User $actor): bool
    {
        $changes = DB::transaction(function () use ($member, $categoryIds): array {
            User::query()->lockForUpdate()->findOrFail($member->id);
            if (! $member->pfiMembership()->exists()) {
                throw ValidationException::withMessages(['categories' => 'La persona ya no es integrante PFI.']);
            }

            return $member->pfiCategories()->sync($categoryIds);
        });

        if ($changes['attached'] === [] && $changes['detached'] === []) {
            return false;
        }

        $member->unsetRelation('pfiCategories');
        $this->log($actor, $member, "ha modificado las categorías PFI de [{$member->fullname}]", [
            'pfi_categories_added' => $this->categoryNames($changes['attached']),
            'pfi_categories_removed' => $this->categoryNames($changes['detached']),
            'pfi_categories' => $this->categoryNames($categoryIds),
        ]);

        return true;
    }

    /** @param array<int, int> $destinations */
    public function remove(User $member, User $actor, array $destinations): bool
    {
        Gate::forUser($actor)->authorize('isAdmin');
        $assignmentNotices = [];

        $removed = DB::transaction(function () use ($member, $actor, $destinations, &$assignmentNotices): bool {
            User::query()->whereKey([$member->id, $actor->id, ...array_values($destinations)])
                ->orderBy('id')->lockForUpdate()->get();
            $actor->forgetAuthorizationContext();
            $member->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize('isAdmin');
            if (! $member->pfiMembership()->exists()) {
                return false;
            }

            $continuity = app(ReportContinuity::class);
            $roleAssignments = DB::table('goal_user')
                ->where('user_id', $member->id)
                ->orderBy('goal_id')
                ->orderBy('role')
                ->get(['goal_id', 'role'])
                ->groupBy('goal_id');
            $goalIds = $roleAssignments->keys()
                ->merge($continuity->pendingForRemoval($member)->pluck('goal_id'))->unique();
            $goals = Goal::query()->with('objective')->whereKey($goalIds)->orderBy('id')->lockForUpdate()->get();
            $assignmentNotices = $goals
                ->filter(fn (Goal $goal): bool => $roleAssignments->has($goal->getKey()))
                ->map(fn (Goal $goal): array => [
                    $goal,
                    $roleAssignments->get($goal->getKey())->pluck('role')->all(),
                ])
                ->values()
                ->all();
            $continuity->transferForRemoval($member, $actor, $destinations);
            $member->assignedGoals()->detach();
            $member->pfiCategories()->detach();
            $member->pfiMembership()->delete();
            $this->log($actor, $member, "ha dado de baja a [{$member->fullname}] de los equipos PFI", ['goal_ids' => $goalIds->values()->all()]);

            return true;
        });

        $member->forgetAuthorizationContext();
        $actor->forgetAuthorizationContext();
        $member->unsetRelation('pfiMembership')->unsetRelation('pfiCategories');

        if ($removed && $member->getKey() !== $actor->getKey()) {
            foreach ($assignmentNotices as [$goal, $roles]) {
                Notification::send(
                    $member,
                    (new GoalAssignmentNotification($goal, $roles, 'removed', $actor))->afterCommit(),
                );
            }
        }

        return $removed;
    }

    /**
     * Active categories plus the ones the member already has, so deactivating a category keeps existing associations.
     *
     * @return Collection<int, PfiCategory>
     */
    public function selectableCategories(?User $member = null): Collection
    {
        return PfiCategory::query()
            ->where(fn (Builder $query) => $query
                ->active()
                ->when($member?->exists, fn (Builder $query) => $query->orWhereHas(
                    'users',
                    fn (Builder $users) => $users->whereKey($member->id)
                )))
            ->ordered()
            ->get();
    }

    /**
     * PFI members matching a name, surname or email, to be offered for assignments.
     */
    public function candidates(string $search): Builder
    {
        return User::query()
            ->pfiMembers()
            ->matchingNameOrEmail($search)
            ->with('avatar')
            ->orderBy('surname')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  array<int, int|string>  $categoryIds
     * @return list<string>
     */
    private function categoryNames(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return PfiCategory::query()->whereKey($categoryIds)->ordered()->pluck('name')->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(User $actor, User $member, string $message, array $context = []): void
    {
        Log::channel('mysql')->info("[{$actor->fullname}] {$message}", $context + [
            'member_id' => $member->id,
            'member_fullname' => $member->fullname,
            'member_email' => $member->email,
            'user_id' => $actor->id,
            'user_fullname' => $actor->fullname,
            'user_email' => $actor->email,
        ]);
    }
}
