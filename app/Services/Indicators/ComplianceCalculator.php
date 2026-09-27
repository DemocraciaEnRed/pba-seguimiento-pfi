<?php

namespace App\Services\Indicators;

use Carbon\CarbonInterface;

class ComplianceCalculator
{
    /**
     * Oriented compliance: a higher value is always better, regardless of direction.
     * Returns null when the ratio is undefined (division by zero).
     */
    public function compliance(Direction $direction, float $target, float $measured): ?float
    {
        return match ($direction) {
            Direction::HigherIsBetter => $target > 0 ? $measured / $target : null,
            Direction::LowerIsBetter => match (true) {
                $target == 0.0 => $measured == 0.0 ? 1.0 : 0.0,
                $measured == 0.0 => null,
                default => $target / $measured,
            },
            Direction::TargetIsBetter => $target == 0.0
                ? ($measured == 0.0 ? 1.0 : 0.0)
                : max(0.0, 1 - abs($measured - $target) / $target),
        };
    }

    /**
     * @param  list<PeriodInput>  $periods
     */
    public function summarize(
        Direction $direction,
        Nature $nature,
        TargetSemantics $semantics,
        array $periods,
        CarbonInterface $today,
    ): IndicatorSummary {
        usort($periods, fn (PeriodInput $a, PeriodInput $b): int => $a->number <=> $b->number);

        $results = array_map(fn (PeriodInput $period): PeriodResult => $this->evaluatePeriod($direction, $period, $today), $periods);

        $reported = array_values(array_filter($results, fn (PeriodResult $result): bool => $result->state === PeriodState::Reported));
        $overdueCount = count(array_filter($results, fn (PeriodResult $result): bool => $result->state === PeriodState::Overdue));
        $evaluatedCount = count($reported) + $overdueCount;

        $toDateTarget = null;
        $toDateMeasured = null;
        $toDateCompliance = null;

        if ($reported !== []) {
            $toDateTarget = $this->aggregate($nature, $semantics, array_map(fn (PeriodResult $result): float => $result->target, $reported));
            $toDateMeasured = $this->aggregate($nature, $semantics, array_map(fn (PeriodResult $result): float => $result->measured, $reported));
            $reportedCompliance = $this->compliance($direction, $toDateTarget, $toDateMeasured);
            // Overdue periods weigh as 0% so that not reporting never improves the result.
            $toDateCompliance = $reportedCompliance === null ? null : $reportedCompliance * count($reported) / $evaluatedCount;
        } elseif ($overdueCount > 0) {
            $toDateCompliance = 0.0;
        }

        [$windowTarget, $windowProgress] = $this->windowProgress($direction, $nature, $semantics, $results, $reported);

        $plannedTargets = array_values(array_map(
            fn (PeriodResult $result): float => $result->target,
            array_filter($results, fn (PeriodResult $result): bool => $result->state !== PeriodState::NoTarget && $result->state !== PeriodState::Skipped),
        ));

        return new IndicatorSummary(
            periods: $results,
            toDateTarget: $toDateTarget,
            toDateMeasured: $toDateMeasured,
            toDateCompliance: $toDateCompliance,
            windowTarget: $windowTarget,
            windowProgress: $windowProgress,
            overdueCount: $overdueCount,
            plannedTarget: $plannedTargets === [] ? null : $this->aggregate($nature, $semantics, $plannedTargets),
        );
    }

    public function stateOf(PeriodInput $period, CarbonInterface $today): PeriodState
    {
        $todayDate = $today->toDateString();

        return match (true) {
            $period->target === null => PeriodState::NoTarget,
            $period->skipped => PeriodState::Skipped,
            $period->measured !== null => PeriodState::Reported,
            $todayDate > $period->endsOn->toDateString() => PeriodState::Overdue,
            $todayDate >= $period->startsOn->toDateString() => PeriodState::Open,
            default => PeriodState::Upcoming,
        };
    }

    private function evaluatePeriod(Direction $direction, PeriodInput $period, CarbonInterface $today): PeriodResult
    {
        $state = $this->stateOf($period, $today);

        $compliance = match ($state) {
            PeriodState::Reported => $this->compliance($direction, $period->target, $period->measured),
            // Forced 0%: evaluating the formula with a missing value would reward it under LowerIsBetter.
            PeriodState::Overdue => 0.0,
            default => null,
        };

        return new PeriodResult(
            number: $period->number,
            state: $state,
            target: $period->target,
            measured: $state === PeriodState::Reported ? $period->measured : null,
            compliance: $compliance,
            relativeDeviation: $compliance === null ? null : $compliance - 1,
            absoluteDeviation: $state === PeriodState::Reported ? $period->measured - $period->target : null,
        );
    }

    /**
     * @param  list<float>  $values
     */
    private function aggregate(Nature $nature, TargetSemantics $semantics, array $values): float
    {
        return match (true) {
            $nature === Nature::Intensive => array_sum($values) / count($values),
            $semantics === TargetSemantics::CumulativeLevel => $values[array_key_last($values)],
            default => array_sum($values),
        };
    }

    /**
     * Progress against the whole monitoring window. Only meaningful for accumulable quantities that should grow.
     *
     * @param  list<PeriodResult>  $results
     * @param  list<PeriodResult>  $reported
     * @return array{0: ?float, 1: ?float}
     */
    private function windowProgress(Direction $direction, Nature $nature, TargetSemantics $semantics, array $results, array $reported): array
    {
        if ($nature !== Nature::Extensive || $direction !== Direction::HigherIsBetter) {
            return [null, null];
        }

        $planned = array_values(array_filter(
            $results,
            fn (PeriodResult $result): bool => $result->state !== PeriodState::NoTarget && $result->state !== PeriodState::Skipped,
        ));

        if ($planned === []) {
            return [null, null];
        }

        $windowTarget = $semantics === TargetSemantics::CumulativeLevel
            ? $planned[array_key_last($planned)]->target
            : array_sum(array_map(fn (PeriodResult $result): float => $result->target, $planned));

        if ($windowTarget <= 0) {
            return [$windowTarget, null];
        }

        $achieved = match (true) {
            $reported === [] => 0.0,
            $semantics === TargetSemantics::CumulativeLevel => $reported[array_key_last($reported)]->measured,
            default => array_sum(array_map(fn (PeriodResult $result): float => $result->measured, $reported)),
        };

        return [$windowTarget, $achieved / $windowTarget];
    }
}
