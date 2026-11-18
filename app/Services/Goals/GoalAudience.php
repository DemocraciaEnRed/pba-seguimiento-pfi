<?php

namespace App\Services\Goals;

use App\Goal;
use App\User;
use Illuminate\Database\Eloquent\Builder;

class GoalAudience
{
    public function publicQuery(): Builder
    {
        return Goal::query()
            ->where('visibility', 'public')
            ->whereHas('objective', fn (Builder $objectives) => $objectives->where('hidden', false));
    }

    public function managementQuery(User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return Goal::query();
        }

        return Goal::query()->where(function (Builder $goals) use ($user): void {
            $goals
                ->where(function (Builder $publicGoals): void {
                    $publicGoals
                        ->where('visibility', 'public')
                        ->whereHas('objective', fn (Builder $objectives) => $objectives->where('hidden', false));
                })
                ->orWhereHas('responsibles', fn (Builder $responsibles) => $responsibles->whereKey($user->getKey()))
                ->orWhereHas('reporters', fn (Builder $reporters) => $reporters->whereKey($user->getKey()));
        });
    }

    public function workspaceQuery(User $user): Builder
    {
        return Goal::query()->where(function (Builder $goals) use ($user): void {
            $goals
                ->whereHas('responsibles', fn (Builder $responsibles) => $responsibles->whereKey($user->getKey()))
                ->orWhereHas('reporters', fn (Builder $reporters) => $reporters->whereKey($user->getKey()));
        });
    }
}
