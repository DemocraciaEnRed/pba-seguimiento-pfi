<?php

namespace App\Providers;

use App\Goal;
use App\Objective;
use App\Policies\GoalPolicy;
use App\Policies\ObjectivePolicy;
use App\Policies\ReportPolicy;
use App\Report;
use App\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Objective::class => ObjectivePolicy::class,
        Goal::class => GoalPolicy::class,
        Report::class => ReportPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('isAdmin', fn (User $user): bool => $user->hasRole('admin'));
        Gate::define('accessWorkDesk', fn (User $user): bool => $user->hasRole('admin') || $user->isPfiMember());
        Gate::define('searchPfiCandidates', fn (User $user, ?Goal $goal = null): bool => $user->hasRole('admin')
            || ($goal !== null && $user->can('manageReporters', $goal)));
    }
}
