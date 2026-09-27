<?php

namespace App\Services\Indicators;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PeriodGenerator
{
    /**
     * Periods always start on the first day of a month, so month arithmetic never overflows.
     *
     * @return list<array{number: int, starts_on: CarbonImmutable, ends_on: CarbonImmutable}>
     */
    public function generate(PeriodType $type, CarbonInterface $start, int $count): array
    {
        $firstDay = CarbonImmutable::instance($start)->startOfMonth();
        $months = $type->months();
        $periods = [];

        for ($index = 0; $index < $count; $index++) {
            $periods[] = [
                'number' => $index + 1,
                'starts_on' => $firstDay->addMonths($index * $months),
                'ends_on' => $firstDay->addMonths(($index + 1) * $months)->subDay(),
            ];
        }

        return $periods;
    }
}
