<?php

namespace App\Imports\Structure;

enum ImportAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Unchanged = 'unchanged';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Crear',
            self::Update => 'Actualizar',
            self::Unchanged => 'Sin cambios',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Create => 'badge-success',
            self::Update => 'badge-warning',
            self::Unchanged => 'badge-light',
        };
    }
}
