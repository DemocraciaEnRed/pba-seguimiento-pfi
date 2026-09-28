<?php

namespace App\Exports;

use App\Report;
use Illuminate\Support\Collection;

class ReportTestimoniesExport implements CsvExport
{

    public function __construct(private int $id) {}

    public function collection(): Collection
    {
        return Report::findorfail($this->id)->testimonies()->get();
    }

    public function headings(): array
    {
        return [
            'Usuario',
            'Usuario Email',
            "Me gusta",
        ];
    }

    public function map($testimony): array
    {
        return  [
          $testimony->user->fullname,
          $testimony->user->email,
          $testimony->value ? 'Sí' : 'No'
        ];
    }
}
