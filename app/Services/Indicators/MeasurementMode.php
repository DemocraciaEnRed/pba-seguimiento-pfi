<?php

namespace App\Services\Indicators;

enum MeasurementMode: string
{
    case None = 'none';
    case Simple = 'simple';
    case Periodic = 'periodic';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sin indicador numérico',
            self::Simple => 'Valor acumulado',
            self::Periodic => 'Por períodos',
        };
    }
}
