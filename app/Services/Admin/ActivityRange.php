<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;

enum ActivityRange: string
{
    case LastSevenDays = '7d';
    case LastThirtyDays = '30d';
    case LastMonth = 'last_month';
    case LastThreeMonths = '3m';
    case LastSixMonths = '6m';
    case AllTime = 'all';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::LastThirtyDays;
    }

    public function label(): string
    {
        return match ($this) {
            self::LastSevenDays => 'Últimos 7 días',
            self::LastThirtyDays => 'Últimos 30 días',
            self::LastMonth => 'Último mes',
            self::LastThreeMonths => 'Últimos 3 meses',
            self::LastSixMonths => 'Últimos 6 meses',
            self::AllTime => 'Desde siempre',
        };
    }

    /**
     * "Último mes" is the previous calendar month; the other ranges are rolling windows ending now.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function bounds(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match ($this) {
            self::LastSevenDays => [$now->subDays(7), $now],
            self::LastThirtyDays => [$now->subDays(30), $now],
            self::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::LastThreeMonths => [$now->subMonthsNoOverflow(3), $now],
            self::LastSixMonths => [$now->subMonthsNoOverflow(6), $now],
            self::AllTime => [null, null],
        };
    }
}
