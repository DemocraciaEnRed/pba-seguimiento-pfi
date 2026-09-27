<?php

namespace App\Services\Indicators;

enum PeriodType: string
{
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Quarterly = 'quarterly';
    case FourMonthly = 'four_monthly';
    case Biannual = 'biannual';
    case Annual = 'annual';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Bimonthly => 2,
            self::Quarterly => 3,
            self::FourMonthly => 4,
            self::Biannual => 6,
            self::Annual => 12,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Bimonthly => 'Bimestral',
            self::Quarterly => 'Trimestral',
            self::FourMonthly => 'Cuatrimestral',
            self::Biannual => 'Semestral',
            self::Annual => 'Anual',
        };
    }
}
