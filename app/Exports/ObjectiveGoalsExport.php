<?php

namespace App\Exports;

use App\Objective;
use Illuminate\Support\Collection;

class ObjectiveGoalsExport implements CsvExport
{

    public function __construct(private int $id) {}

    public function collection(): Collection
    {
        return Objective::findorfail($this->id)->goals()->get();
    }

    public function headings(): array
    {
        return [
            'Titulo',
            'Estado',
            'Modo de medición',
            'Indicador',
            "Fuente",
            "Frecuencia del indicador",
            "Unidad del indicador",
            "Valor a alcanzar",
            "Valor actual",
            "Progreso / cumplimiento a la fecha (%)",
            "Reportes",
            "Hitos",
            "Mapeado",
            "Fecha creado",
            "Fecha actualizado",
        ];
    }

    public function map($goal): array
    {
        return [
            $goal->title,
            $goal->status_label,
            $goal->measurement_mode->label(),
            $goal->indicator,
            $goal->source ?? '-',
            $goal->isPeriodic() ? $goal->period_type->label() : $goal->indicator_frequency,
            $goal->indicator_unit,
            (string) $goal->indicator_goal,
            (string) $goal->indicator_progress,
            (string) $goal->progress_percentage,
            (string) $goal->reports->count(),
            (string) $goal->milestones->count(),
            $goal->map_lat ? 'Si' : 'No',
            $goal->created_at->format('d/m/Y H:i:s'),
            $goal->updated_at->format('d/m/Y H:i:s'),
        ];
    }
}
