<?php

namespace App\Exports;

use App\Services\Admin\ReportDirectory;
use Illuminate\Support\LazyCollection;

class ReportsExport implements CsvExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private array $filters = []) {}

    public function collection(): LazyCollection
    {
        return app(ReportDirectory::class)->search($this->filters)->lazy();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha del reporte',
            'Título',
            'Tipo',
            'Eje',
            'Objetivo general',
            'Meta',
            'Autor',
            'Email',
            'Comentarios',
            'Me gusta',
            'Fecha de carga',
        ];
    }

    public function map(mixed $report): array
    {
        $objective = $report->goal?->objective;

        return [
            $report->id,
            $report->date?->format('d/m/Y') ?? '-',
            $report->title,
            $report->type_label,
            $objective?->category?->title ?? '-',
            $objective?->title ?? '-',
            $report->goal?->title ?? '-',
            $report->author?->fullname ?? '-',
            $report->author?->email ?? '-',
            $report->comments_count,
            $report->positive_testimonies_count,
            $report->created_at?->format('d/m/Y H:i:s') ?? '-',
        ];
    }
}
