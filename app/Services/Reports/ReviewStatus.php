<?php

namespace App\Services\Reports;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de revisión',
            self::Approved => 'Publicado',
            self::Returned => 'Devuelto',
            self::Validated => 'Validado; pendiente de Administración',
        };
    }
}
