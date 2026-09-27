<?php

namespace App\Services\Indicators;

use Carbon\CarbonImmutable;

class PeriodInput
{
    public function __construct(
        public readonly int $number,
        public readonly CarbonImmutable $startsOn,
        public readonly CarbonImmutable $endsOn,
        public readonly ?float $target,
        public readonly ?float $measured = null,
        public readonly bool $skipped = false,
    ) {}
}
