<?php

namespace App\Services\Indicators;

class IndicatorSummary
{
    /**
     * @param  list<PeriodResult>  $periods
     * @param  ?float  $plannedTarget  Aggregated target of every planned period in the window.
     */
    public function __construct(
        public readonly array $periods,
        public readonly ?float $toDateTarget,
        public readonly ?float $toDateMeasured,
        public readonly ?float $toDateCompliance,
        public readonly ?float $windowTarget,
        public readonly ?float $windowProgress,
        public readonly int $overdueCount,
        public readonly ?float $plannedTarget = null,
    ) {}

    public function toDateRelativeDeviation(): ?float
    {
        return $this->toDateCompliance === null ? null : $this->toDateCompliance - 1;
    }

    public function period(int $number): ?PeriodResult
    {
        foreach ($this->periods as $period) {
            if ($period->number === $number) {
                return $period;
            }
        }

        return null;
    }
}
