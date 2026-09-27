<?php

namespace App\Services\Indicators;

class PeriodResult
{
    /**
     * Ratios are fractions: 1.0 means 100% compliance.
     */
    public function __construct(
        public readonly int $number,
        public readonly PeriodState $state,
        public readonly ?float $target,
        public readonly ?float $measured,
        public readonly ?float $compliance,
        public readonly ?float $relativeDeviation,
        public readonly ?float $absoluteDeviation,
    ) {}
}
