<?php

namespace App\Imports\Structure;

use App\Category;
use App\Goal;
use App\Imports\Structure\StructureImportColumns as Column;
use App\Objective;
use App\Services\Csv\CsvReader;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Indicators\MeasurementMode;
use App\Services\Indicators\TargetSemantics;
use App\StrategicObjective;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Resolves spreadsheet rows against the database and validates them, without saving anything.
 */
class StructureImportPlanner
{
    /**
     * Fields that cannot change once a goal has progress reports, as in the edit form.
     */
    private const array LOCKED_FIELDS = [
        'measurement_mode' => Column::MODE,
        'indicator_direction' => Column::DIRECTION,
        'indicator_nature' => Column::NATURE,
        'target_semantics' => Column::SEMANTICS,
        'period_type' => Column::PERIOD_TYPE,
        'period_start' => 'Año y mes de inicio',
        'period_count' => Column::PERIOD_COUNT,
    ];

    private const array FIELD_COLUMNS = [
        'title' => Column::GOAL,
        'status' => Column::STATUS,
        'source' => Column::SOURCE,
        'measurement_mode' => Column::MODE,
        'indicator' => Column::INDICATOR,
        'indicator_unit' => Column::UNIT,
        'indicator_goal' => Column::GOAL_VALUE,
        'indicator_progress' => Column::PROGRESS_VALUE,
        'indicator_frequency' => Column::FREQUENCY,
        'indicator_formula' => Column::FORMULA,
        'indicator_direction' => Column::DIRECTION,
        'indicator_nature' => Column::NATURE,
        'target_semantics' => Column::SEMANTICS,
        'period_type' => Column::PERIOD_TYPE,
        'period_start' => 'Año y mes de inicio',
        'period_count' => Column::PERIOD_COUNT,
    ];

    private StructureImportPlan $plan;

    /**
     * @var Collection<int, Category>
     */
    private Collection $categories;

    /**
     * @var array<string, PlannedItem>
     */
    private array $strategicObjectives = [];

    /**
     * @var array<string, PlannedItem>
     */
    private array $objectives = [];

    /**
     * @var array<string, int>
     */
    private array $goalLines = [];

    /**
     * @var array<string, int>
     */
    private array $nextCodes = [];

    /**
     * @var array<string, true>
     */
    private array $usedCodes = [];

    public function __construct(private GoalIndicatorConfigurator $indicatorConfigurator) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     */
    public function plan(array $rows): StructureImportPlan
    {
        $this->plan = new StructureImportPlan();
        $this->strategicObjectives = $this->objectives = $this->goalLines = $this->nextCodes = $this->usedCodes = [];
        $this->categories = Category::with('strategicObjectives.objectives.goals.periods')->get();

        if ($rows === []) {
            $this->plan->addError(1, null, 'El archivo no tiene filas debajo de los encabezados.');
        }

        $context = [];

        foreach ($rows as $row) {
            $this->planRow($row['line'], $this->fillDown($row['values'], $context));
        }

        return $this->plan;
    }

    /**
     * A blank hierarchy cell inherits the value above only while every level above it is blank too,
     * so a new strategic objective never inherits the previous one's objective.
     *
     * @param  array<string, string>  $values
     * @param  array<int, array<string, string>>  $context
     * @return array<string, string>
     */
    private function fillDown(array $values, array &$context): array
    {
        $firstFilledLevel = count(Column::HIERARCHY);

        foreach (Column::HIERARCHY as $level => $headers) {
            foreach ($headers as $header) {
                if ($this->cell($values, $header) !== '') {
                    $firstFilledLevel = $level;

                    break 2;
                }
            }
        }

        foreach (Column::HIERARCHY as $level => $headers) {
            foreach ($headers as $header) {
                $key = CsvReader::normalize($header);

                if ($level < $firstFilledLevel) {
                    $values[$key] = $context[$level][$key] ?? '';
                } else {
                    $context[$level][$key] = $values[$key] ?? '';
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function planRow(int $line, array $values): void
    {
        $category = $this->category($line, $this->cell($values, Column::CATEGORY));

        if ($category === null) {
            return;
        }

        if ($this->cell($values, Column::STRATEGIC_OBJECTIVE) === '') {
            $this->plan->addError($line, Column::STRATEGIC_OBJECTIVE, 'Falta el objetivo estratégico.');

            return;
        }

        $strategicObjective = $this->strategicObjective(
            $line,
            $category,
            $this->cell($values, Column::STRATEGIC_OBJECTIVE_CODE),
            $this->cell($values, Column::STRATEGIC_OBJECTIVE),
        );

        $hierarchyKeys = array_map(CsvReader::normalize(...), array_merge(...Column::HIERARCHY));
        $goalKeys = array_diff(array_map(CsvReader::normalize(...), Column::headings(Column::MAX_PERIOD_COLUMNS)), $hierarchyKeys);
        $hasGoal = array_filter(array_intersect_key($values, array_flip($goalKeys)), fn (string $value): bool => $value !== '') !== [];
        $hasObjective = $this->cell($values, Column::OBJECTIVE) !== '' || $this->cell($values, Column::OBJECTIVE_CODE) !== '';

        if ($strategicObjective === null) {
            return;
        }

        if (! $hasObjective) {
            if ($hasGoal) {
                $this->plan->addError($line, Column::OBJECTIVE, 'Falta el objetivo al que pertenece la meta.');
            }

            return;
        }

        if ($this->cell($values, Column::OBJECTIVE) === '') {
            $this->plan->addError($line, Column::OBJECTIVE, 'Falta el nombre del objetivo.');

            return;
        }

        $objective = $this->objective(
            $line,
            $strategicObjective,
            $this->cell($values, Column::OBJECTIVE_CODE),
            $this->cell($values, Column::OBJECTIVE),
            $this->cell($values, Column::OBJECTIVE_DESCRIPTION),
        );

        if ($objective === null || ! $hasGoal) {
            return;
        }

        if ($this->cell($values, Column::GOAL) === '') {
            $this->plan->addError($line, Column::GOAL, 'Falta el nombre de la meta.');

            return;
        }

        $this->goal($line, $objective, $values);
    }

    private function category(int $line, string $value): ?Category
    {
        if ($value === '') {
            $this->plan->addError($line, Column::CATEGORY, 'Falta el eje.');

            return null;
        }

        $category = ctype_digit($value)
            ? $this->categories->firstWhere('order', (int) $value)
            : $this->categories->first(fn (Category $category): bool => CsvReader::normalize($category->title) === CsvReader::normalize($value));

        if ($category === null) {
            $this->plan->addError($line, Column::CATEGORY, "No existe el eje «{$value}». Los ejes se crean desde Administración › Ejes.");
        }

        return $category;
    }

    private function strategicObjective(int $line, Category $category, string $code, string $title): ?PlannedItem
    {
        $code = mb_strtoupper($code);
        $titleKey = "title:{$category->id}|".CsvReader::normalize($title);
        $planned = $this->strategicObjectives[$code !== '' ? "code:{$code}" : $titleKey] ?? null;

        if ($planned !== null) {
            return $this->isConsistent($line, $planned, $title, Column::STRATEGIC_OBJECTIVE, (int) $planned->model->category_id === (int) $category->id) ? $planned : null;
        }

        if ($code !== '') {
            $model = $category->strategicObjectives->first(fn (StrategicObjective $model): bool => mb_strtoupper((string) $model->codigo) === $code);
            $other = $model === null ? StrategicObjective::withTrashed()->with('category')->where('codigo', $code)->first() : null;

            if ($other !== null) {
                $this->plan->addError($line, Column::STRATEGIC_OBJECTIVE_CODE, $other->trashed()
                    ? "El código {$code} pertenece a un objetivo estratégico eliminado."
                    : "El código {$code} pertenece a un objetivo estratégico del eje «{$other->category?->title}».");

                return null;
            }
        } else {
            $matches = $category->strategicObjectives->filter(fn (StrategicObjective $model): bool => CsvReader::normalize($model->title) === CsvReader::normalize($title));

            if ($matches->count() > 1) {
                $this->plan->addError($line, Column::STRATEGIC_OBJECTIVE, "Hay {$matches->count()} objetivos estratégicos con este nombre en el eje: completá el Código OE.");

                return null;
            }

            $model = $matches->first();
        }

        if (mb_strlen($title) > 550) {
            $this->plan->addError($line, Column::STRATEGIC_OBJECTIVE, 'El nombre no puede superar los 550 caracteres.');

            return null;
        }

        if ($model === null) {
            $model = new StrategicObjective();
            $model->category()->associate($category);
            $model->codigo = $code !== '' ? $code : $this->nextCode('OE', StrategicObjective::class);
        }

        $model->title = $title;
        $item = $this->plannedItem($model, $line, ['title' => Column::STRATEGIC_OBJECTIVE]);

        $this->plan->strategicObjectives[] = $item;
        $this->strategicObjectives['code:'.mb_strtoupper($model->codigo)] = $item;
        $this->strategicObjectives[$titleKey] = $item;
        $this->usedCodes[mb_strtoupper($model->codigo)] = true;

        return $item;
    }

    private function objective(int $line, PlannedItem $parent, string $code, string $title, string $description): ?PlannedItem
    {
        $code = mb_strtoupper($code);
        $titleKey = 'title:'.spl_object_id($parent).'|'.CsvReader::normalize($title);
        $planned = $this->objectives[$code !== '' ? "code:{$code}" : $titleKey] ?? null;

        if ($planned !== null) {
            return $this->isConsistent($line, $planned, $title, Column::OBJECTIVE, in_array($planned, $parent->children, true)) ? $planned : null;
        }

        /** @var StrategicObjective $strategicObjective */
        $strategicObjective = $parent->model;
        $siblings = $strategicObjective->exists ? $strategicObjective->objectives : new Collection();

        if ($code !== '') {
            $model = $siblings->first(fn (Objective $model): bool => mb_strtoupper((string) $model->codigo) === $code);
            $other = $model === null ? Objective::withTrashed()->with('strategicObjective')->where('codigo', $code)->first() : null;

            if ($other !== null) {
                $this->plan->addError($line, Column::OBJECTIVE_CODE, $other->trashed()
                    ? "El código {$code} pertenece a un objetivo eliminado."
                    : "El código {$code} pertenece a un objetivo de otro objetivo estratégico ({$other->strategicObjective?->codigo}).");

                return null;
            }
        } else {
            $matches = $siblings->filter(fn (Objective $model): bool => CsvReader::normalize($model->title) === CsvReader::normalize($title));

            if ($matches->count() > 1) {
                $this->plan->addError($line, Column::OBJECTIVE, "Hay {$matches->count()} objetivos con este nombre en el objetivo estratégico: completá el Código objetivo.");

                return null;
            }

            $model = $matches->first();
        }

        $errorCount = count($this->plan->errors);

        if (mb_strlen($title) > 550) {
            $this->plan->addError($line, Column::OBJECTIVE, 'El nombre no puede superar los 550 caracteres.');
        }

        if (mb_strlen($description) > 2000) {
            $this->plan->addError($line, Column::OBJECTIVE_DESCRIPTION, 'La descripción no puede superar los 2000 caracteres.');
        }

        if ($model === null && $description === '') {
            $this->plan->addError($line, Column::OBJECTIVE_DESCRIPTION, 'Los objetivos nuevos necesitan una descripción.');
        }

        if (count($this->plan->errors) > $errorCount) {
            return null;
        }

        if ($model === null) {
            $model = new Objective();
            $model->hidden = true;
            $model->codigo = $code !== '' ? $code : $this->nextCode('OG', Objective::class);
        }

        $model->title = $title;

        if ($description !== '') {
            $model->content = $description;
        }

        $item = $this->plannedItem($model, $line, ['title' => Column::OBJECTIVE, 'content' => Column::OBJECTIVE_DESCRIPTION]);

        $parent->children[] = $item;
        $this->objectives['code:'.mb_strtoupper($model->codigo)] = $item;
        $this->objectives[$titleKey] = $item;
        $this->usedCodes[mb_strtoupper($model->codigo)] = true;

        return $item;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function goal(int $line, PlannedItem $parent, array $values): void
    {
        /** @var Objective $objective */
        $objective = $parent->model;
        $goalId = $this->cell($values, Column::GOAL_ID);
        $title = $this->cell($values, Column::GOAL);

        if ($goalId !== '') {
            if (! ctype_digit($goalId)) {
                $this->plan->addError($line, Column::GOAL_ID, 'El ID de meta tiene que ser un número.');

                return;
            }

            $goal = $objective->exists ? $objective->goals->firstWhere('id', (int) $goalId) : null;

            if ($goal === null) {
                $this->plan->addError($line, Column::GOAL_ID, "La meta con ID {$goalId} no pertenece a este objetivo. Para crear una meta nueva dejá el ID vacío.");

                return;
            }
        } else {
            $matches = $objective->exists
                ? $objective->goals->filter(fn (Goal $goal): bool => CsvReader::normalize($goal->title) === CsvReader::normalize($title))
                : new Collection();

            if ($matches->count() > 1) {
                $this->plan->addError($line, Column::GOAL, "Hay {$matches->count()} metas con este nombre en el objetivo: completá el ID meta.");

                return;
            }

            $goal = $matches->first();
        }

        $key = $goal !== null ? "id:{$goal->id}" : 'title:'.spl_object_id($parent).'|'.CsvReader::normalize($title);

        if (isset($this->goalLines[$key])) {
            $this->plan->addError($line, Column::GOAL, "La meta ya aparece en la fila {$this->goalLines[$key]}.");

            return;
        }

        $this->goalLines[$key] = $line;
        $input = $this->goalInput($line, $values);

        if ($input === null) {
            return;
        }

        if ($goal === null && $input['status'] === 'reached') {
            $this->plan->addError($line, Column::STATUS, '"Alcanzada" solo se puede usar en metas existentes.');

            return;
        }

        $validator = Validator::make($input, array_merge([
            'title' => ['required', 'string', 'max:550'],
            'status' => ['required', Rule::in(array_keys(Column::statuses()))],
            'source' => ['nullable', 'string', 'max:550'],
        ], $this->indicatorConfigurator->rules($input, $goal)), [], $this->attributeNames($input));

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->plan->addError($line, $this->columnFor($field), $message);
                }
            }

            return;
        }

        $isLocked = $goal !== null && $goal->hasProgressReports();

        if (! $this->hasValidStructure($line, $input, $goal, $isLocked)) {
            return;
        }

        if ($goal === null) {
            $parent->children[] = new PlannedItem(new Goal(), $line, ImportAction::Create, [], $validator->validated());

            return;
        }

        $comparable = $input;
        $comparable['indicator_progress'] ??= $goal->indicator_progress;
        $before = $this->goalState($this->modelInput($goal));
        $after = $this->goalState($comparable);
        $changes = [];

        foreach (array_keys($before + $after) as $column) {
            if (($before[$column] ?? '') !== ($after[$column] ?? '')) {
                $changes[$column] = [$before[$column] ?? '', $after[$column] ?? ''];
            }
        }

        $parent->children[] = new PlannedItem($goal, $line, $changes === [] ? ImportAction::Unchanged : ImportAction::Update, $changes, $validator->validated());
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>|null
     */
    private function goalInput(int $line, array $values): ?array
    {
        $errorCount = count($this->plan->errors);
        $text = fn (string $column): ?string => $this->cell($values, $column) === '' ? null : $this->cell($values, $column);
        $number = fn (string $column): ?string => $this->cell($values, $column) === '' ? null : Column::number($this->cell($values, $column));

        $mode = $this->choice($line, $values, Column::MODE, Column::modes());
        $semantics = $this->choice($line, $values, Column::SEMANTICS, Column::semantics());

        $input = [
            'title' => $this->cell($values, Column::GOAL),
            'status' => $this->choice($line, $values, Column::STATUS, Column::statuses()),
            'source' => $text(Column::SOURCE),
            'measurement_mode' => $mode,
            'indicator' => $text(Column::INDICATOR),
            'indicator_unit' => $text(Column::UNIT),
            'indicator_frequency' => $text(Column::FREQUENCY),
            'indicator_goal' => $number(Column::GOAL_VALUE),
            'indicator_progress' => $number(Column::PROGRESS_VALUE),
            'indicator_formula' => $text(Column::FORMULA),
            'indicator_direction' => $this->choice($line, $values, Column::DIRECTION, Column::directions()),
            'indicator_nature' => $this->choice($line, $values, Column::NATURE, Column::natures()),
            'target_semantics' => $semantics ?? ($mode === MeasurementMode::Periodic->value ? TargetSemantics::Incremental->value : null),
            'period_type' => $this->choice($line, $values, Column::PERIOD_TYPE, Column::periodTypes()),
            'period_start' => $this->periodStart($line, $values),
            'period_count' => $number(Column::PERIOD_COUNT),
            'period_targets' => $this->periodTargets($line, $values),
        ];

        return count($this->plan->errors) > $errorCount ? null : $input;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasValidStructure(int $line, array $input, ?Goal $goal, bool $isLocked): bool
    {
        $errorCount = count($this->plan->errors);

        if ($isLocked) {
            $current = $this->modelInput($goal);

            foreach (self::LOCKED_FIELDS as $field => $column) {
                if ((string) ($current[$field] ?? '') !== (string) ($input[$field] ?? '')) {
                    $this->plan->addError($line, $column, "La meta ya tiene avances cargados: no se puede cambiar {$column}.");
                }
            }
        }

        $isPeriodic = ($isLocked ? $goal->measurement_mode->value : $input['measurement_mode']) === MeasurementMode::Periodic->value;

        if ($isPeriodic && count($this->plan->errors) === $errorCount) {
            $periodCount = $isLocked ? $goal->period_count : (int) $input['period_count'];
            $hasTarget = false;

            foreach ($input['period_targets'] as $number => $target) {
                if ($target === null) {
                    continue;
                }

                if ($number > $periodCount) {
                    $this->plan->addError($line, Column::periodTarget($number), "La meta tiene {$periodCount} períodos: dejá vacía esta columna o aumentá la cantidad de períodos.");
                }

                $hasTarget = true;
            }

            if (! $hasTarget) {
                $this->plan->addError($line, Column::periodTarget(1), 'Definí el valor objetivo de al menos un período.');
            }
        }

        return count($this->plan->errors) === $errorCount;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, list<string>>  $options
     */
    private function choice(int $line, array $values, string $column, array $options): ?string
    {
        $value = $this->cell($values, $column);

        if ($value === '') {
            return null;
        }

        $choice = Column::choice($value, $options);

        if ($choice === null) {
            $this->plan->addError($line, $column, "«{$value}» no es un valor válido. Opciones: ".implode(' · ', array_column($options, 0)).'.');
        }

        return $choice;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function periodStart(int $line, array $values): ?string
    {
        $year = $this->cell($values, Column::START_YEAR);
        $month = $this->cell($values, Column::START_MONTH);

        if ($year === '' && $month === '') {
            return null;
        }

        if ($year === '' || $month === '') {
            $this->plan->addError($line, $year === '' ? Column::START_YEAR : Column::START_MONTH, 'Completá el año y el mes de inicio.');

            return null;
        }

        $monthNumber = Column::month($month);

        if (preg_match('/^\d{4}$/', $year) !== 1) {
            $this->plan->addError($line, Column::START_YEAR, "«{$year}» no es un año válido. Ej: 2027.");
        }

        if ($monthNumber === null) {
            $this->plan->addError($line, Column::START_MONTH, "«{$month}» no es un mes válido: usá un número del 1 al 12 o el nombre del mes.");
        }

        return $monthNumber === null ? null : sprintf('%s-%02d', $year, $monthNumber);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<int, ?string>
     */
    private function periodTargets(int $line, array $values): array
    {
        $targets = [];

        foreach ($values as $header => $value) {
            if (preg_match('/^meta p(\d+)$/', $header, $matches) !== 1) {
                continue;
            }

            $number = (int) $matches[1];

            if ($number < 1 || $number > Column::MAX_PERIOD_COLUMNS) {
                if ($value !== '') {
                    $this->plan->addError($line, Column::periodTarget($number), 'Se admiten columnas de Meta P1 a Meta P'.Column::MAX_PERIOD_COLUMNS.'.');
                }

                continue;
            }

            $targets[$number] = $value === '' ? null : Column::number($value);
        }

        ksort($targets);

        return $targets;
    }

    /**
     * Current goal values shaped like the import input, so both sides compare alike.
     *
     * @return array<string, mixed>
     */
    private function modelInput(Goal $goal): array
    {
        return [
            'title' => $goal->title,
            'status' => $goal->status,
            'source' => $goal->source,
            'measurement_mode' => $goal->measurement_mode->value,
            'indicator' => $goal->indicator,
            'indicator_unit' => $goal->indicator_unit,
            'indicator_frequency' => $goal->indicator_frequency,
            'indicator_goal' => $goal->indicator_goal,
            'indicator_progress' => $goal->indicator_progress,
            'indicator_formula' => $goal->indicator_formula,
            'indicator_direction' => $goal->indicator_direction?->value,
            'indicator_nature' => $goal->indicator_nature?->value,
            'target_semantics' => $goal->target_semantics?->value ?? ($goal->isPeriodic() ? TargetSemantics::Incremental->value : null),
            'period_type' => $goal->period_type?->value,
            'period_start' => $goal->period_start?->format('Y-m'),
            'period_count' => $goal->period_count,
            'period_targets' => $goal->periods->pluck('target_value', 'number')->all(),
        ];
    }

    /**
     * Human-readable values of the fields that apply to the goal's mode, keyed by column header.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function goalState(array $data): array
    {
        $mode = MeasurementMode::from($data['measurement_mode']);
        $number = fn (mixed $value): string => $value === null || $value === '' ? '' : indicator_number((float) $value);
        $isSimple = $mode === MeasurementMode::Simple;

        $state = [
            Column::GOAL => (string) $data['title'],
            Column::STATUS => (string) Column::label($data['status'], Column::statuses()),
            Column::SOURCE => (string) $data['source'],
            Column::MODE => (string) Column::label($mode->value, Column::modes()),
            Column::INDICATOR => $mode === MeasurementMode::None ? '' : (string) $data['indicator'],
            Column::UNIT => $mode === MeasurementMode::None ? '' : (string) $data['indicator_unit'],
            Column::GOAL_VALUE => $isSimple ? $number($data['indicator_goal']) : '',
            Column::PROGRESS_VALUE => $isSimple ? $number($data['indicator_progress'] ?? 0) : '',
            Column::FREQUENCY => $isSimple ? (string) $data['indicator_frequency'] : '',
        ];

        if ($mode !== MeasurementMode::Periodic) {
            return $state;
        }

        $state += [
            Column::FORMULA => (string) $data['indicator_formula'],
            Column::DIRECTION => (string) Column::label($data['indicator_direction'], Column::directions()),
            Column::NATURE => (string) Column::label($data['indicator_nature'], Column::natures()),
            Column::SEMANTICS => (string) Column::label($data['target_semantics'], Column::semantics()),
            Column::PERIOD_TYPE => (string) Column::label($data['period_type'], Column::periodTypes()),
            'Inicio' => (string) $data['period_start'],
            Column::PERIOD_COUNT => (string) (int) $data['period_count'],
        ];

        for ($period = 1; $period <= (int) $data['period_count']; $period++) {
            $state[Column::periodTarget($period)] = $number($data['period_targets'][$period] ?? null);
        }

        return $state;
    }

    /**
     * @param  array<string, string>  $columns  Attribute => column header.
     */
    private function plannedItem(Model $model, int $line, array $columns): PlannedItem
    {
        if (! $model->exists) {
            return new PlannedItem($model, $line, ImportAction::Create);
        }

        $changes = [];

        foreach ($columns as $attribute => $column) {
            if ($model->isDirty($attribute)) {
                $changes[$column] = [(string) $model->getOriginal($attribute), (string) $model->getAttribute($attribute)];
            }
        }

        return new PlannedItem($model, $line, $changes === [] ? ImportAction::Unchanged : ImportAction::Update, $changes);
    }

    private function isConsistent(int $line, PlannedItem $planned, string $title, string $column, bool $hasSameParent): bool
    {
        if (CsvReader::normalize($planned->model->title) !== CsvReader::normalize($title)) {
            $this->plan->addError($line, $column, "Este código ya se usó con otro nombre en la fila {$planned->line}.");

            return false;
        }

        if (! $hasSameParent) {
            $this->plan->addError($line, $column, "Ya aparece en la fila {$planned->line} dentro de otro nivel superior.");

            return false;
        }

        return true;
    }

    /**
     * @param  class-string<StrategicObjective|Objective>  $modelClass
     */
    private function nextCode(string $prefix, string $modelClass): string
    {
        $this->nextCodes[$prefix] ??= 1 + (int) $modelClass::withTrashed()->pluck('codigo')
            ->map(fn (?string $code): int => preg_match("/^{$prefix}(\d+)$/i", (string) $code, $matches) === 1 ? (int) $matches[1] : 0)
            ->max();

        do {
            $code = $prefix.$this->nextCodes[$prefix]++;
        } while (isset($this->usedCodes[$code]));

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function attributeNames(array $input): array
    {
        $names = self::FIELD_COLUMNS + ['period_targets' => Column::periodTarget(1)];

        foreach (array_keys($input['period_targets']) as $number) {
            $names["period_targets.{$number}"] = Column::periodTarget($number);
        }

        return $names;
    }

    private function columnFor(string $field): ?string
    {
        if (str_starts_with($field, 'period_targets.')) {
            return Column::periodTarget((int) substr($field, strlen('period_targets.')));
        }

        return $field === 'period_targets' ? Column::periodTarget(1) : (self::FIELD_COLUMNS[$field] ?? null);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function cell(array $values, string $header): string
    {
        return $values[CsvReader::normalize($header)] ?? '';
    }
}
