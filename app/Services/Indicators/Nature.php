<?php

namespace App\Services\Indicators;

enum Nature: string
{
    case Extensive = 'extensive';
    case Intensive = 'intensive';

    public function label(): string
    {
        return match ($this) {
            self::Extensive => 'Los períodos se suman (cantidades, avance)',
            self::Intensive => 'Los períodos se promedian (plazos, tasas, porcentajes)',
        };
    }
}
