<?php

namespace App\Services\Indicators;

enum Direction: string
{
    case HigherIsBetter = 'higher_is_better';
    case LowerIsBetter = 'lower_is_better';
    case TargetIsBetter = 'target_is_better';

    public function label(): string
    {
        return match ($this) {
            self::HigherIsBetter => 'Un valor mayor es mejor',
            self::LowerIsBetter => 'Un valor menor es mejor',
            self::TargetIsBetter => 'Lo mejor es acercarse al objetivo',
        };
    }
}
