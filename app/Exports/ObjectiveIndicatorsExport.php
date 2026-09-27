<?php

namespace App\Exports;

use App\Goal;
use App\Objective;
use App\Services\Indicators\MeasurementMode;
use Illuminate\Support\Collection;

/**
 * Mirrors the client's monitoring spreadsheet: one row per periodic goal, targets and results per period.
 */
class ObjectiveIndicatorsExport implements CsvExport
{
    private ?Collection $goals = null;

    public function __construct(private int $objectiveId) {}

    public function collection(): Collection
    {
        return $this->goals ??= Objective::with('strategicObjective.category')
            ->findOrFail($this->objectiveId)
            ->goals()
            ->where('measurement_mode', MeasurementMode::Periodic->value)
            ->with('periods.progressReport')
            ->get();
    }

    public function headings(): array
    {
        $periodNumbers = range(1, max(1, $this->maxPeriodCount()));

        return array_merge(
            ['Eje', 'Objetivo estratégico', 'Objetivo', 'Producto / Meta', 'Indicador', 'Fórmula', 'Unidad'],
            array_map(fn (int $number): string => "Meta P{$number}", $periodNumbers),
            ['Meta ventana'],
            array_merge(...array_map(fn (int $number): array => ["Resultado P{$number}", "Cumplimiento P{$number}", "Desvío P{$number}"], $periodNumbers)),
            ['Resultado a la fecha', 'Cumplimiento a la fecha', 'Desvío a la fecha'],
        );
    }

    /**
     * @param  Goal  $goal
     */
    public function map($goal): array
    {
        $objective = $goal->objective;
        $summary = $goal->indicatorSummary();
        $periodNumbers = range(1, max(1, $this->maxPeriodCount()));

        return array_merge(
            [
                $objective->strategicObjective?->category?->title,
                $objective->strategicObjective?->title,
                $objective->title,
                $goal->title,
                $goal->indicator,
                $goal->indicator_formula,
                $goal->indicator_unit,
            ],
            array_map(fn (int $number): ?float => $summary->period($number)?->target, $periodNumbers),
            [$summary->plannedTarget],
            array_merge(...array_map(function (int $number) use ($summary): array {
                $period = $summary->period($number);

                return [
                    $period?->measured,
                    $period?->compliance === null ? null : indicator_percentage($period->compliance),
                    $period?->relativeDeviation === null ? null : indicator_percentage($period->relativeDeviation, signed: true),
                ];
            }, $periodNumbers)),
            [
                $summary->toDateMeasured === null ? null : round($summary->toDateMeasured, 2),
                $summary->toDateCompliance === null ? null : indicator_percentage($summary->toDateCompliance),
                $summary->toDateCompliance === null ? null : indicator_percentage($summary->toDateRelativeDeviation(), signed: true),
            ],
        );
    }

    private function maxPeriodCount(): int
    {
        return (int) $this->collection()->max('period_count');
    }
}
