<?php

namespace App\Exports;

use App\Objective;
use Illuminate\Support\Collection;

class ObjectiveSubscribersExport implements CsvExport
{

    public function __construct(private int $id) {}

    public function collection(): Collection
    {
        return Objective::findorfail($this->id)->subscribers()->get();
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Apellido',
            'Email',
            "Fecha Subscripción"
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->surname,
            $user->email,
            $user->pivot->created_at->format('d/m/Y'),
        ];
    }

}
