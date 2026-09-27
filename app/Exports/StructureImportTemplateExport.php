<?php

namespace App\Exports;

use App\Category;
use App\Goal;
use App\Imports\Structure\StructureImportColumns as Column;
use Illuminate\Support\Collection;

/**
 * Current strategic objectives, objectives and goals in the import format, ready to edit and upload again.
 */
class StructureImportTemplateExport implements CsvExport
{
    private ?Collection $rows = null;

    private int $periodColumns = Column::MIN_PERIOD_COLUMNS;

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $this->periodColumns = max(Column::MIN_PERIOD_COLUMNS, (int) Goal::max('period_count'));
        $categories = Category::orderBy('order')->with([
            'strategicObjectives' => fn ($query) => $query->orderBy('id'),
            'strategicObjectives.objectives' => fn ($query) => $query->orderBy('id'),
            'strategicObjectives.objectives.goals' => fn ($query) => $query->orderBy('id'),
            'strategicObjectives.objectives.goals.periods',
        ])->get();

        $rows = [];

        foreach ($categories as $category) {
            foreach ($category->strategicObjectives as $strategicObjective) {
                $strategicObjectiveCells = [$category->title, $strategicObjective->codigo, $strategicObjective->title];

                if ($strategicObjective->objectives->isEmpty()) {
                    $rows[] = $strategicObjectiveCells;
                }

                foreach ($strategicObjective->objectives as $objective) {
                    $objectiveCells = [...$strategicObjectiveCells, $objective->codigo, $objective->title, $objective->content];

                    if ($objective->goals->isEmpty()) {
                        $rows[] = $objectiveCells;
                    }

                    foreach ($objective->goals as $goal) {
                        $rows[] = [...$objectiveCells, ...$this->goalCells($goal)];
                    }
                }
            }
        }

        return $this->rows = collect($rows);
    }

    public function headings(): array
    {
        $this->collection();

        return Column::headings($this->periodColumns);
    }

    /**
     * @param  list<mixed>  $row
     */
    public function map(mixed $row): array
    {
        return array_pad($row, count($this->headings()), null);
    }

    /**
     * @return list<mixed>
     */
    private function goalCells(Goal $goal): array
    {
        $targets = $goal->periods->pluck('target_value', 'number');

        return [
            $goal->id,
            $goal->title,
            Column::label($goal->status, Column::statuses()),
            $goal->source,
            Column::label($goal->measurement_mode->value, Column::modes()),
            $goal->indicator,
            $goal->indicator_unit,
            $goal->isSimple() ? $goal->indicator_goal : null,
            $goal->isSimple() ? $goal->indicator_progress : null,
            $goal->isSimple() ? $goal->indicator_frequency : null,
            $goal->isPeriodic() ? $goal->indicator_formula : null,
            Column::label($goal->indicator_direction?->value, Column::directions()),
            Column::label($goal->indicator_nature?->value, Column::natures()),
            Column::label($goal->target_semantics?->value, Column::semantics()),
            Column::label($goal->period_type?->value, Column::periodTypes()),
            $goal->period_start?->year,
            $goal->period_start?->month,
            $goal->period_count,
            ...array_map(fn (int $number): ?float => $targets->get($number), range(1, $this->periodColumns)),
        ];
    }
}
