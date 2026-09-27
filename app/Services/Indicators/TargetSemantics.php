<?php

namespace App\Services\Indicators;

enum TargetSemantics: string
{
    case Incremental = 'incremental';
    case CumulativeLevel = 'cumulative_level';

    public function label(): string
    {
        return match ($this) {
            self::Incremental => 'El objetivo de cada período es lo que se agrega en ese período',
            self::CumulativeLevel => 'El objetivo de cada período es el nivel a alcanzar al cierre',
        };
    }
}
