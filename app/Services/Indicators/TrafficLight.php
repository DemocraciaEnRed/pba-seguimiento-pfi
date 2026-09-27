<?php

namespace App\Services\Indicators;

enum TrafficLight: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public const TOLERATED_DEVIATION = -0.10;

    public static function fromCompliance(?float $compliance): ?self
    {
        if ($compliance === null) {
            return null;
        }

        $relativeDeviation = $compliance - 1;

        return match (true) {
            $relativeDeviation >= 0 => self::Green,
            $relativeDeviation >= self::TOLERATED_DEVIATION => self::Yellow,
            default => self::Red,
        };
    }

    public function bootstrapColor(): string
    {
        return match ($this) {
            self::Green => 'success',
            self::Yellow => 'warning',
            self::Red => 'danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Cumplido',
            self::Yellow => 'Levemente por debajo',
            self::Red => 'Incumplido',
        };
    }
}
