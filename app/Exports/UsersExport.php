<?php

namespace App\Exports;

use App\Services\Admin\UserDirectory;
use Illuminate\Support\LazyCollection;

class UsersExport implements CsvExport
{
    /**
     * @param  array{q?: ?string, verificado?: ?string, rol?: ?string, equipo?: ?string, pfi?: ?string, categoria_pfi?: ?string, orden?: ?string}  $filters
     */
    public function __construct(private array $filters = []) {}

    public function collection(): LazyCollection
    {
        return app(UserDirectory::class)->search($this->filters)->lazy();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Apellido',
            'Nombre',
            'Email',
            'Organización',
            'Verificado',
            'Administrador',
            'Objetivos (equipo)',
            'Suscripciones',
            'Comentarios',
            'Reportes',
            'Fecha de alta',
            'Integrante PFI',
            'Categorías PFI',
        ];
    }

    public function map(mixed $user): array
    {
        return [
            $user->id,
            $user->surname,
            $user->name,
            $user->email,
            $user->organization ?? '-',
            $user->hasVerifiedEmail() ? 'Sí' : 'No',
            $user->roles->contains('name', 'admin') ? 'Sí' : 'No',
            $user->objectives_count,
            $user->subscriptions_count,
            $user->comments_count,
            $user->reports_count,
            $user->created_at?->format('d/m/Y H:i:s') ?? '-',
            $user->isPfiMember() ? 'Sí' : 'No',
            $user->pfiCategories->pluck('name')->join(', ') ?: '-',
        ];
    }
}
