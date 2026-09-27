<?php

namespace App\Exports;

use App\Category;
use App\Imports\Structure\StructureImportColumns as Column;
use Illuminate\Support\Collection;

/**
 * Fictitious rows showing each measurement mode and the blank cells Excel leaves after merging vertically.
 */
class StructureImportExampleExport implements CsvExport
{
    public function collection(): Collection
    {
        $category = Category::orderBy('order')->value('title') ?? 'Nombre de un eje existente';
        $strategicObjective = [$category, '', 'EJEMPLO - Fortalecer la transparencia de la gestión'];
        $blankHierarchy = array_fill(0, 6, '');

        return collect([
            [...$strategicObjective, '', 'EJEMPLO - Publicar información con datos abiertos', 'Descripción del objetivo de ejemplo.',
                '', 'Datasets publicados en el portal', 'En progreso', 'Portal de datos abiertos', 'Valor acumulado', 'Datasets publicados', 'datasets', 50, 0, 'Mensual'],
            [...$blankHierarchy,
                '', 'Plazo de respuesta a solicitudes de información', 'En progreso', 'Sistema de expedientes', 'Por períodos', 'Días promedio de respuesta', 'días', '', '', '',
                'Suma de días de respuesta / solicitudes respondidas', 'Menor es mejor', 'Promedio', 'Incremental', 'Trimestral', 2027, 1, 4, 15, 15, 12, 10],
            ['', '', '', '', 'EJEMPLO - Simplificar y normalizar los procesos', 'Descripción del segundo objetivo de ejemplo.',
                '', 'Manual de procedimientos aprobado', 'En progreso', '', 'Sin indicador numérico'],
            [...$blankHierarchy,
                '', 'Personas capacitadas en los nuevos procesos', 'En progreso', 'Registro de capacitaciones', 'Por períodos', 'Personas capacitadas', 'personas', '', '', '',
                '', 'Mayor es mejor', 'Suma', 'Incremental', 'Semestral', 2027, 'Enero', 2, 100, 150],
        ]);
    }

    public function headings(): array
    {
        return Column::headings();
    }

    /**
     * @param  list<mixed>  $row
     */
    public function map(mixed $row): array
    {
        return array_pad($row, count($this->headings()), '');
    }
}
