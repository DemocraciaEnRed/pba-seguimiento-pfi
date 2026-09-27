<?php

namespace App\Services\Indicators;

enum PeriodState: string
{
    case NoTarget = 'no_target';
    case Skipped = 'skipped';
    case Reported = 'reported';
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::NoTarget => 'Sin objetivo',
            self::Skipped => 'Omitido',
            self::Reported => 'Informado',
            self::Upcoming => 'Próximo',
            self::Open => 'En curso',
            self::Overdue => 'Vencido sin informar',
        };
    }

    public function isEvaluated(): bool
    {
        return $this === self::Reported || $this === self::Overdue;
    }

    public function isReportable(): bool
    {
        return $this === self::Open || $this === self::Overdue;
    }
}
