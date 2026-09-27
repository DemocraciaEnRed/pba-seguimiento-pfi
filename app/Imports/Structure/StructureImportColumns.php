<?php

namespace App\Imports\Structure;

use App\Services\Csv\CsvReader;
use App\Services\Indicators\Direction;
use App\Services\Indicators\MeasurementMode;
use App\Services\Indicators\Nature;
use App\Services\Indicators\PeriodType;
use App\Services\Indicators\TargetSemantics;

/**
 * Single source of truth for the structure spreadsheet: headers, accepted values and the help shown to admins.
 */
final class StructureImportColumns
{
    public const string CATEGORY = 'Eje';

    public const string STRATEGIC_OBJECTIVE_CODE = 'Código OE';

    public const string STRATEGIC_OBJECTIVE = 'Objetivo estratégico';

    public const string OBJECTIVE_CODE = 'Código objetivo';

    public const string OBJECTIVE = 'Objetivo';

    public const string OBJECTIVE_DESCRIPTION = 'Descripción del objetivo';

    public const string GOAL_ID = 'ID meta';

    public const string GOAL = 'Meta';

    public const string STATUS = 'Estado';

    public const string SOURCE = 'Fuente';

    public const string MODE = 'Tipo de medición';

    public const string INDICATOR = 'Indicador';

    public const string UNIT = 'Unidad';

    public const string GOAL_VALUE = 'Valor a alcanzar';

    public const string PROGRESS_VALUE = 'Valor actual';

    public const string FREQUENCY = 'Frecuencia';

    public const string FORMULA = 'Fórmula';

    public const string DIRECTION = 'Dirección';

    public const string NATURE = 'Naturaleza';

    public const string SEMANTICS = 'Semántica';

    public const string PERIOD_TYPE = 'Periodicidad';

    public const string START_YEAR = 'Año de inicio';

    public const string START_MONTH = 'Mes de inicio';

    public const string PERIOD_COUNT = 'Cantidad de períodos';

    public const int MIN_PERIOD_COLUMNS = 12;

    public const int MAX_PERIOD_COLUMNS = 60;

    /**
     * Hierarchy levels, top to bottom. Blank cells inherit the value above, as Excel leaves them after vertical merges.
     */
    public const array HIERARCHY = [
        [self::CATEGORY],
        [self::STRATEGIC_OBJECTIVE_CODE, self::STRATEGIC_OBJECTIVE],
        [self::OBJECTIVE_CODE, self::OBJECTIVE, self::OBJECTIVE_DESCRIPTION],
    ];

    private const array MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return [self::CATEGORY, self::STRATEGIC_OBJECTIVE, self::OBJECTIVE, self::GOAL];
    }

    /**
     * @return list<string>
     */
    public static function headings(int $periodColumns = self::MIN_PERIOD_COLUMNS): array
    {
        return [
            self::CATEGORY, self::STRATEGIC_OBJECTIVE_CODE, self::STRATEGIC_OBJECTIVE,
            self::OBJECTIVE_CODE, self::OBJECTIVE, self::OBJECTIVE_DESCRIPTION,
            self::GOAL_ID, self::GOAL, self::STATUS, self::SOURCE, self::MODE, self::INDICATOR, self::UNIT,
            self::GOAL_VALUE, self::PROGRESS_VALUE, self::FREQUENCY,
            self::FORMULA, self::DIRECTION, self::NATURE, self::SEMANTICS, self::PERIOD_TYPE,
            self::START_YEAR, self::START_MONTH, self::PERIOD_COUNT,
            ...array_map(self::periodTarget(...), range(1, $periodColumns)),
        ];
    }

    public static function periodTarget(int $number): string
    {
        return "Meta P{$number}";
    }

    /**
     * @return array<string, list<string>>
     */
    public static function statuses(): array
    {
        return [
            'ongoing' => ['En progreso', 'En curso'],
            'delayed' => ['No cumplida', 'Demorada'],
            'inactive' => ['Inactiva'],
            'reached' => ['Alcanzada', 'Cumplida'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function modes(): array
    {
        return self::withEnumLabels(MeasurementMode::cases(), [], [
            'none' => ['Sin indicador'],
            'simple' => ['Acumulado'],
            'periodic' => ['Periódico', 'Períodos'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function directions(): array
    {
        return self::withEnumLabels(Direction::cases(), [
            'higher_is_better' => 'Mayor es mejor',
            'lower_is_better' => 'Menor es mejor',
            'target_is_better' => 'Acercarse al objetivo',
        ], [
            'higher_is_better' => ['Mayor'],
            'lower_is_better' => ['Menor'],
            'target_is_better' => ['Acercarse'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function natures(): array
    {
        return self::withEnumLabels(Nature::cases(), [
            'extensive' => 'Suma',
            'intensive' => 'Promedio',
        ], [
            'extensive' => ['Se suman'],
            'intensive' => ['Se promedian'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function semantics(): array
    {
        return self::withEnumLabels(TargetSemantics::cases(), [
            'incremental' => 'Incremental',
            'cumulative_level' => 'Nivel acumulado',
        ], [
            'cumulative_level' => ['Nivel'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function periodTypes(): array
    {
        return self::withEnumLabels(PeriodType::cases(), []);
    }

    /**
     * Matches a cell against the accepted labels, ignoring case, accents and punctuation.
     *
     * @param  array<string, list<string>>  $options
     */
    public static function choice(string $value, array $options): ?string
    {
        $normalized = CsvReader::normalize($value);

        foreach ($options as $optionValue => $labels) {
            foreach ([$optionValue, ...$labels] as $label) {
                if (CsvReader::normalize($label) === $normalized) {
                    return $optionValue;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $options
     */
    public static function label(?string $value, array $options): ?string
    {
        return $value === null ? null : ($options[$value][0] ?? $value);
    }

    /**
     * Accepts "1234.5", "1234,5" and "1.234,5"; invalid input is returned untouched so validation reports it.
     */
    public static function number(string $value): string
    {
        $number = str_replace([' ', "\u{00A0}"], '', rtrim($value, " %"));
        $lastComma = strrpos($number, ',');
        $lastDot = strrpos($number, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $number = str_replace(['.', ','], ['', '.'], $number);
        } elseif ($lastComma !== false) {
            $number = str_replace(',', '', $number);
        }

        return is_numeric($number) ? $number : $value;
    }

    public static function month(string $value): ?int
    {
        if (ctype_digit($value)) {
            $month = (int) $value;

            return $month >= 1 && $month <= 12 ? $month : null;
        }

        $normalized = CsvReader::normalize($value);

        foreach (self::MONTHS as $index => $month) {
            if ($normalized !== '' && ($normalized === $month || $normalized === substr($month, 0, 3))) {
                return $index + 1;
            }
        }

        return null;
    }

    public static function monthName(int $month): string
    {
        return ucfirst(self::MONTHS[$month - 1]);
    }

    /**
     * @return list<array{header: string, required: string, values: string, description: string}>
     */
    public static function documentation(): array
    {
        $accepted = fn (array $options): string => implode(' · ', array_map(fn (array $labels): string => $labels[0], $options));

        return [
            ['header' => self::CATEGORY, 'required' => 'Sí', 'values' => 'Nombre o número de un eje existente', 'description' => 'Los ejes no se crean desde la planilla: tienen que existir en Administración > Ejes.'],
            ['header' => self::STRATEGIC_OBJECTIVE_CODE, 'required' => 'No', 'values' => 'Ej: OE12', 'description' => 'Si está vacío se busca el objetivo estratégico por nombre dentro del eje; si no existe se crea con el próximo código libre.'],
            ['header' => self::STRATEGIC_OBJECTIVE, 'required' => 'Sí', 'values' => 'Texto (hasta 550 caracteres)', 'description' => 'Con código, cambiar el nombre lo actualiza. Sin código, un nombre distinto crea uno nuevo.'],
            ['header' => self::OBJECTIVE_CODE, 'required' => 'No', 'values' => 'Ej: OG45', 'description' => 'Si está vacío se busca el objetivo por nombre dentro del objetivo estratégico; si no existe se crea.'],
            ['header' => self::OBJECTIVE, 'required' => 'Sí', 'values' => 'Texto (hasta 550 caracteres)', 'description' => 'Los objetivos nuevos se crean ocultos: publicalos desde su configuración cuando estén listos.'],
            ['header' => self::OBJECTIVE_DESCRIPTION, 'required' => 'Para objetivos nuevos', 'values' => 'Texto (hasta 2000 caracteres)', 'description' => 'En objetivos existentes, dejala vacía para no modificarla.'],
            ['header' => self::GOAL_ID, 'required' => 'No', 'values' => 'Número', 'description' => 'Viene completo en la planilla precargada. Vacío: se busca la meta por nombre dentro del objetivo; si no existe se crea.'],
            ['header' => self::GOAL, 'required' => 'Para cargar una meta', 'values' => 'Texto (hasta 550 caracteres)', 'description' => 'Una fila por meta. Una fila sin meta solo crea o actualiza el objetivo estratégico y el objetivo.'],
            ['header' => self::STATUS, 'required' => 'Sí (con meta)', 'values' => $accepted(self::statuses()), 'description' => '"Alcanzada" solo se puede usar en metas existentes.'],
            ['header' => self::SOURCE, 'required' => 'No', 'values' => 'Texto', 'description' => 'Fuente de los datos del indicador.'],
            ['header' => self::MODE, 'required' => 'Sí (con meta)', 'values' => $accepted(self::modes()), 'description' => 'Define qué otras columnas hay que completar.'],
            ['header' => self::INDICATOR, 'required' => 'Si hay indicador', 'values' => 'Texto', 'description' => 'Qué se mide. Ej: Días promedio de emisión.'],
            ['header' => self::UNIT, 'required' => 'Si hay indicador', 'values' => 'Texto', 'description' => 'Ej: días, personas, %, expedientes.'],
            ['header' => self::GOAL_VALUE, 'required' => 'Valor acumulado', 'values' => 'Número mayor a 0', 'description' => 'Valor final a alcanzar.'],
            ['header' => self::PROGRESS_VALUE, 'required' => 'No', 'values' => 'Número', 'description' => 'Valor acumulado actual. Vacío: 0 en metas nuevas, sin cambios en las existentes.'],
            ['header' => self::FREQUENCY, 'required' => 'No', 'values' => 'Texto', 'description' => 'Solo valor acumulado. Ej: Mensual.'],
            ['header' => self::FORMULA, 'required' => 'No', 'values' => 'Texto', 'description' => 'Solo por períodos. Cómo se calcula el indicador.'],
            ['header' => self::DIRECTION, 'required' => 'Por períodos', 'values' => $accepted(self::directions()), 'description' => 'Qué resultado indica un mejor desempeño.'],
            ['header' => self::NATURE, 'required' => 'Por períodos', 'values' => $accepted(self::natures()), 'description' => 'Suma para cantidades o avance; Promedio para plazos, tasas o porcentajes.'],
            ['header' => self::SEMANTICS, 'required' => 'No', 'values' => $accepted(self::semantics()), 'description' => 'Vacío: Incremental (el objetivo de cada período es lo que se agrega en ese período).'],
            ['header' => self::PERIOD_TYPE, 'required' => 'Por períodos', 'values' => $accepted(self::periodTypes()), 'description' => 'Duración de cada período.'],
            ['header' => self::START_YEAR, 'required' => 'Por períodos', 'values' => 'Ej: 2027', 'description' => 'Año en que empieza el primer período.'],
            ['header' => self::START_MONTH, 'required' => 'Por períodos', 'values' => '1 a 12 o nombre del mes', 'description' => 'Mes en que empieza el primer período.'],
            ['header' => self::PERIOD_COUNT, 'required' => 'Por períodos', 'values' => '1 a '.self::MAX_PERIOD_COLUMNS, 'description' => 'Cantidad de períodos a medir.'],
            ['header' => 'Meta P1, Meta P2, …', 'required' => 'Por períodos (al menos una)', 'values' => 'Número', 'description' => 'Valor objetivo de cada período. Vacío si ese período no tiene objetivo. Se pueden agregar columnas hasta Meta P'.self::MAX_PERIOD_COLUMNS.'.'],
        ];
    }

    /**
     * The first label of each option is the one shown in help, downloads and previews.
     *
     * @param  list<MeasurementMode|Direction|Nature|TargetSemantics|PeriodType>  $cases
     * @param  array<string, string>  $shortLabels
     * @param  array<string, list<string>>  $aliases
     * @return array<string, list<string>>
     */
    private static function withEnumLabels(array $cases, array $shortLabels, array $aliases = []): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = array_values(array_unique([
                $shortLabels[$case->value] ?? $case->label(),
                $case->label(),
                ...($aliases[$case->value] ?? []),
            ]));
        }

        return $options;
    }
}
