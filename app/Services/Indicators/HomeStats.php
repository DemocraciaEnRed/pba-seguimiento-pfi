<?php

namespace App\Services\Indicators;

use App\Category;
use App\Goal;
use App\Objective;
use App\StrategicObjective;
use Carbon\CarbonImmutable;

class HomeStats
{
    /**
     * Traffic lights only consider active (non inactive) goals of published objectives.
     *
     * @return array{
     *     categories_total: int,
     *     strategic_objectives_total: int,
     *     objectives_total: int,
     *     goals_total: int,
     *     goals_reached: int,
     *     goals_ongoing: int,
     *     goals_delayed: int,
     *     goals_inactive: int,
     *     traffic_lights: array{green: int, yellow: int, red: int, measured: int, unmeasured: int},
     *     categories: list<array{id: int, title: string, icon: ?string, icon_url: ?string, color: ?string, order: ?int, strategic_objectives_count: int, objectives_count: int, goals_total: int, goals_reached: int, measured: int, green: int}>
     * }
     */
    public function compute(): array
    {
        $today = CarbonImmutable::today();

        $goals = Goal::query()
            ->whereHas('objective', fn ($query) => $query->where('hidden', false))
            ->with(['objective:id,strategic_objective_id', 'objective.strategicObjective:id,category_id', 'periods.progressReport'])
            ->get()
            ->map(function (Goal $goal) use ($today): array {
                $summary = $goal->status === 'inactive' ? null : $goal->indicatorSummary($today);

                return [
                    'status' => $goal->status,
                    'category_id' => $goal->objective->strategicObjective?->category_id,
                    'light' => TrafficLight::fromCompliance($summary?->toDateCompliance),
                ];
            });

        $categories = Category::with([
            'strategicObjectives' => fn ($query) => $query->withCount(['objectives' => fn ($query) => $query->where('hidden', false)]),
        ])->orderBy('order')->get();
        $statusCounts = $goals->countBy('status');
        $lightCounts = $goals->pluck('light')->filter()->countBy(fn (TrafficLight $light): string => $light->value);
        $measured = $lightCounts->sum();
        $goalsByCategory = $goals->groupBy('category_id');

        return [
            'categories_total' => $categories->count(),
            'strategic_objectives_total' => StrategicObjective::count(),
            'objectives_total' => Objective::where('hidden', false)->count(),
            'goals_total' => $goals->count(),
            'goals_reached' => $statusCounts->get('reached', 0),
            'goals_ongoing' => $statusCounts->get('ongoing', 0),
            'goals_delayed' => $statusCounts->get('delayed', 0),
            'goals_inactive' => $statusCounts->get('inactive', 0),
            'traffic_lights' => [
                'green' => $lightCounts->get(TrafficLight::Green->value, 0),
                'yellow' => $lightCounts->get(TrafficLight::Yellow->value, 0),
                'red' => $lightCounts->get(TrafficLight::Red->value, 0),
                'measured' => $measured,
                'unmeasured' => $goals->count() - $statusCounts->get('inactive', 0) - $measured,
            ],
            'categories' => $categories->map(function (Category $category) use ($goalsByCategory): array {
                $categoryGoals = $goalsByCategory->get($category->id, collect());

                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'icon' => $category->icon,
                    'icon_url' => $category->icon_url,
                    'color' => $category->color,
                    'order' => $category->order,
                    'strategic_objectives_count' => $category->strategicObjectives->count(),
                    'objectives_count' => (int) $category->strategicObjectives->sum('objectives_count'),
                    'goals_total' => $categoryGoals->count(),
                    'goals_reached' => $categoryGoals->where('status', 'reached')->count(),
                    'measured' => $categoryGoals->whereNotNull('light')->count(),
                    'green' => $categoryGoals->where('light', TrafficLight::Green)->count(),
                ];
            })->all(),
        ];
    }
}
