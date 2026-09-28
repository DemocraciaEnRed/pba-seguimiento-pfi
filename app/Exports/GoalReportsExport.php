<?php

namespace App\Exports;

use App\Goal;
use Illuminate\Support\Collection;

class GoalReportsExport implements CsvExport
{

    public function __construct(private int $id) {}

    public function collection(): Collection
    {
        return Goal::findorfail($this->id)->reports()->get();
    }

    public function headings(): array
    {
        return [
            'Titulo',
            'Autor',
            'Autor Email',
            'Tipo',
            'Fecha del reporte',
            "Tags",
            "Mapeado",
            "Comentarios",
            "Me gusta",
            "Estado de la meta previamente",
            "Nuevo estado de la meta",
            "Progreso previo",
            "Progreso declarado",
            "Período informado",
            "Valor medido",
            "Hito completado",
            "Fecha de completado del hito",
            "Fecha creado",
            "Fecha actualizado",
        ];
    }

    public function map($report): array
    {
        return [
            $report->title,
            $report->author->fullname,
            $report->author->email,
            $report->type_label,
            $report->date->format('d/m/Y'),
            implode(', ', $report->tags),
            $report->map_geometries ? 'Si' : 'No',
            (string) $report->comments->count(),
            (string) $report->positiveTestimonies,
            $report->previous_status_label ?? '-',
            $report->status_label ?? '-',
            $report->previous_progress ? (string) $report->previous_progress : '-',
            $report->progress ? (string) $report->progress : '-',
            $report->period ? $report->period->label().' ('.$report->period->rangeLabel().')' : '-',
            $report->measured_value !== null ? (string) $report->measured_value : '-',
            $report->milestone ? $report->milestone->title : '-',
            $report->milestone ? $report->milestone->completed->format('d/m/Y') : '-',
            $report->created_at->format('d/m/Y H:i:s'),
            $report->updated_at->format('d/m/Y H:i:s'),
        ];
    }

}
