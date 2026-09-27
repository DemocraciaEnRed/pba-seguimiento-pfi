<?php

namespace App\Imports\Structure;

final class StructureImportPlan
{
    /**
     * Strategic objectives in file order, each with its objectives and their goals as children.
     *
     * @var list<PlannedItem>
     */
    public array $strategicObjectives = [];

    /**
     * @var list<array{line: int, column: ?string, message: string}>
     */
    public array $errors = [];

    public function addError(int $line, ?string $column, string $message): void
    {
        $this->errors[] = ['line' => $line, 'column' => $column, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasChanges(): bool
    {
        foreach ($this->summary() as $counts) {
            if ($counts[ImportAction::Create->value] + $counts[ImportAction::Update->value] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, int>> Entity label => action => count.
     */
    public function summary(): array
    {
        $empty = array_fill_keys(array_map(fn (ImportAction $action): string => $action->value, ImportAction::cases()), 0);
        $summary = ['Objetivos estratégicos' => $empty, 'Objetivos' => $empty, 'Metas' => $empty];

        foreach ($this->strategicObjectives as $strategicObjective) {
            $summary['Objetivos estratégicos'][$strategicObjective->action->value]++;

            foreach ($strategicObjective->children as $objective) {
                $summary['Objetivos'][$objective->action->value]++;

                foreach ($objective->children as $goal) {
                    $summary['Metas'][$goal->action->value]++;
                }
            }
        }

        return $summary;
    }
}
