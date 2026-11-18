<?php

namespace App\Exports;

use App\Objective;
use App\Services\Goals\GoalAudience;
use App\User;
use Illuminate\Support\Collection;

class ObjectiveGoalsExport implements CsvExport
{

    public function __construct(private int $id, private ?User $user = null) {}

    public function collection(): Collection
    {
        $goalAudience = $this->user === null
            ? app(GoalAudience::class)->publicQuery()
            : app(GoalAudience::class)->managementQuery($this->user);
        $reportScope = $this->user === null
            ? fn ($reports) => $reports->approved()->visibleIn($goalAudience)
            : fn ($reports) => $reports->manageableBy($this->user);

        return Objective::findOrFail($this->id)->goals()
            ->visibleIn($goalAudience)
            ->with(['reports' => $reportScope, 'milestones'])
            ->get();
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
