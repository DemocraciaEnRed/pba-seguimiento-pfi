<?php

namespace App\Services\Indicators;

use App\Goal;
use App\Objective;
use Carbon\CarbonImmutable;

class ObjectiveStats
{
    /**
     * Traffic lights only consider active (non inactive) goals.
     *
     * @return array{
     *     goals_total: int,
     *     goals_reached: int,
     *     goals_ongoing: int,
     *     goals_delayed: int,
     *     goals_inactive: int,
     *     reports_total: int,
     *     last_report_date: ?string,
     *     traffic_lights: array{green: int, yellow: int, red: int, measured: int, unmeasured: int}
     * }
     */
    public function compute(Objective $objective): array
    {
        $today = CarbonImmutable::today();

        $goals = $objective->goals()
            ->with('periods.progressReport')
            ->get()
            ->map(function (Goal $goal) use ($today): array {
                $summary = $goal->status === 'inactive' ? null : $goal->indicatorSummary($today);

                return [
                    'status' => $goal->status,
                    'light' => TrafficLight::fromCompliance($summary?->toDateCompliance),
                ];
            });

        $statusCounts = $goals->countBy('status');
        $lightCounts = $goals->pluck('light')->filter()->countBy(fn (TrafficLight $light): string => $light->value);
        $measured = $lightCounts->sum();
        $lastReportDate = $objective->reports()->max('reports.date');

        return [
            'goals_total' => $goals->count(),
            'goals_reached' => $statusCounts->get('reached', 0),
            'goals_ongoing' => $statusCounts->get('ongoing', 0),
            'goals_delayed' => $statusCounts->get('delayed', 0),
            'goals_inactive' => $statusCounts->get('inactive', 0),
            'reports_total' => $objective->reports()->count(),
            'last_report_date' => $lastReportDate ? CarbonImmutable::parse($lastReportDate)->toDateString() : null,
            'traffic_lights' => [
                'green' => $lightCounts->get(TrafficLight::Green->value, 0),
                'yellow' => $lightCounts->get(TrafficLight::Yellow->value, 0),
                'red' => $lightCounts->get(TrafficLight::Red->value, 0),
                'measured' => $measured,
                'unmeasured' => $goals->count() - $statusCounts->get('inactive', 0) - $measured,
            ],
        ];
    }
}
