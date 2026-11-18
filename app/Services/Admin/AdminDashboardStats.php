<?php

namespace App\Services\Admin;

use App\Category;
use App\Comment;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\Testimony;
use App\User;
use App\Services\Indicators\HomeStats;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardStats
{
    public function __construct(private HomeStats $homeStats) {}

    /**
     * @return array{
     *     structure: array{categories: int, strategic_objectives: int, objectives_published: int, objectives_hidden: int, goals: int},
     *     traffic_lights: array{green: int, yellow: int, red: int, measured: int, unmeasured: int},
     *     activity: array{reports: int, comments: int, users: int, likes: int},
     *     users: array{total: int, verified: int, unverified: int, administrators: int}
     * }
     */
    public function compute(ActivityRange $range): array
    {
        $usersTotal = User::count();
        $usersVerified = User::whereNotNull('email_verified_at')->count();

        return [
            'structure' => [
                'categories' => Category::count(),
                'strategic_objectives' => StrategicObjective::count(),
                'objectives_published' => Objective::where('hidden', false)->count(),
                'objectives_hidden' => Objective::where('hidden', true)->count(),
                'goals' => Goal::count(),
            ],
            'traffic_lights' => $this->homeStats->computeFor(Goal::query())['traffic_lights'],
            'activity' => [
                'reports' => $this->inRange(Report::query(), $range)->count(),
                'comments' => $this->inRange(Comment::query(), $range)->count(),
                'users' => $this->inRange(User::query(), $range)->count(),
                'likes' => $this->inRange(Testimony::where('value', true), $range)->count(),
            ],
            'users' => [
                'total' => $usersTotal,
                'verified' => $usersVerified,
                'unverified' => $usersTotal - $usersVerified,
                'administrators' => User::whereHas('roles', fn (Builder $query) => $query->where('name', 'admin'))->count(),
            ],
        ];
    }

    private function inRange(Builder $query, ActivityRange $range): Builder
    {
        [$from, $to] = $range->bounds();

        return $query
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to));
    }
}
