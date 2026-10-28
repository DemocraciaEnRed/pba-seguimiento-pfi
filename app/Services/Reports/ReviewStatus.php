<?php

namespace App\Services\Reports;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de revisión',
            self::Approved => 'Aprobado',
            self::Returned => 'Devuelto',
        };
    }
}
