<?php

namespace App\Services\Indicators;

use App\Goal;
use App\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoalIndicatorConfigurator
{
    public function __construct(private PeriodGenerator $periodGenerator) {}

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function formOptions(): array
    {
        $options = fn (array $cases): array => array_map(fn ($case): array => ['value' => $case->value, 'label' => $case->label()], $cases);

        return [
            'modes' => $options(MeasurementMode::cases()),
            'directions' => $options(Direction::cases()),
            'natures' => $options(Nature::cases()),
            'semantics' => $options(TargetSemantics::cases()),
            'periodTypes' => array_map(
                fn (PeriodType $type): array => ['value' => $type->value, 'label' => $type->label(), 'months' => $type->months()],
                PeriodType::cases(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(?Goal $goal): array
    {
        return [
            'measurement_mode' => $goal?->measurement_mode->value ?? MeasurementMode::Periodic->value,
            'indicator' => $goal?->indicator,
            'indicator_unit' => $goal?->indicator_unit,
            'indicator_frequency' => $goal?->indicator_frequency,
            'indicator_goal' => $goal?->indicator_goal,
            'indicator_progress' => $goal?->indicator_progress ?? 0,
            'indicator_formula' => $goal?->indicator_formula,
            'indicator_direction' => $goal?->indicator_direction?->value,
            'indicator_nature' => $goal?->indicator_nature?->value,
            'target_semantics' => $goal?->target_semantics?->value ?? TargetSemantics::Incremental->value,
            'period_type' => $goal?->period_type?->value ?? PeriodType::Quarterly->value,
            'period_start' => $goal?->period_start?->format('Y-m') ?? CarbonImmutable::today()->startOfYear()->format('Y-m'),
            'period_count' => $goal?->period_count ?? 4,
            'period_targets' => $goal?->periods->pluck('target_value', 'number')->all() ?? [],
            'locked' => $goal?->hasProgressReports() ?? false,
        ];
    }

    /**
     * Once a goal has progress reports, its mode and period structure are locked and not validated.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function rules(array $input, ?Goal $goal = null): array
    {
        $isLocked = $goal?->hasProgressReports() ?? false;
        $mode = $isLocked ? $goal->measurement_mode : MeasurementMode::tryFrom((string) ($input['measurement_mode'] ?? ''));
        $isSimple = $mode === MeasurementMode::Simple;
        $isPeriodic = $mode === MeasurementMode::Periodic;
        $direction = $isLocked ? $goal->indicator_direction : Direction::tryFrom((string) ($input['indicator_direction'] ?? ''));

        $rules = [
            'indicator' => [Rule::requiredIf($mode !== MeasurementMode::None), 'nullable', 'string', 'max:550'],
            'indicator_unit' => [Rule::requiredIf($mode !== MeasurementMode::None), 'nullable', 'string', 'max:550'],
            'indicator_frequency' => ['nullable', 'string', 'max:550'],
            'indicator_goal' => [Rule::requiredIf($isSimple), 'nullable', 'numeric', 'gt:0'],
            'indicator_progress' => ['nullable', 'numeric', 'min:0'],
            'indicator_formula' => ['nullable', 'string', 'max:2000'],
            'period_targets' => [Rule::requiredIf($isPeriodic), 'nullable', 'array'],
            'period_targets.*' => array_merge(
                ['nullable', 'numeric', 'min:0'],
                $direction === Direction::HigherIsBetter ? ['gt:0'] : [],
            ),
        ];

        if ($isLocked) {
            return $rules;
        }

        return array_merge($rules, [
            'measurement_mode' => ['required', Rule::enum(MeasurementMode::class)],
            'indicator_direction' => [Rule::requiredIf($isPeriodic), 'nullable', Rule::enum(Direction::class)],
            'indicator_nature' => [Rule::requiredIf($isPeriodic), 'nullable', Rule::enum(Nature::class)],
            'target_semantics' => ['nullable', Rule::enum(TargetSemantics::class)],
            'period_type' => [Rule::requiredIf($isPeriodic), 'nullable', Rule::enum(PeriodType::class)],
            'period_start' => [Rule::requiredIf($isPeriodic), 'nullable', 'date_format:Y-m'],
            'period_count' => [Rule::requiredIf($isPeriodic), 'nullable', 'integer', 'min:1', 'max:60'],
        ]);
    }

    /**
     * Applies validated indicator settings, saves the goal and keeps its periods in sync.
     *
     * @param  array<string, mixed>  $validated
     */
    public function apply(Goal $goal, array $validated): void
    {
        $isLocked = $goal->exists && $goal->hasProgressReports();

        if (! $isLocked) {
            $this->applyStructure($goal, $validated);
        }

        $targets = $goal->isPeriodic() ? $this->targetsFor($goal, $validated['period_targets'] ?? []) : [];

        if ($goal->isPeriodic() && array_filter($targets, fn (?float $target): bool => $target !== null) === []) {
            throw ValidationException::withMessages([
                'period_targets' => 'Definí el valor objetivo de al menos un período.',
            ]);
        }

        $goal->indicator = $goal->measurement_mode === MeasurementMode::None ? null : ($validated['indicator'] ?? null);
        $goal->indicator_unit = $goal->measurement_mode === MeasurementMode::None ? null : ($validated['indicator_unit'] ?? null);
        $goal->indicator_frequency = $goal->isSimple() ? ($validated['indicator_frequency'] ?? null) : null;
        $goal->indicator_goal = $goal->isSimple() ? $validated['indicator_goal'] : null;
        $goal->indicator_progress = $goal->isSimple() ? ($validated['indicator_progress'] ?? $goal->indicator_progress ?? 0) : null;
        $goal->indicator_formula = $goal->isPeriodic() ? ($validated['indicator_formula'] ?? null) : null;

        DB::transaction(function () use ($goal, $targets, $isLocked): void {
            $structureChanged = $goal->isDirty(['measurement_mode', 'period_type', 'period_start', 'period_count']);
            $goal->save();

            if (! $isLocked && ($structureChanged || ! $goal->isPeriodic())) {
                $this->deletePeriods($goal);
            }

            if ($goal->isPeriodic()) {
                $this->syncPeriods($goal, $targets);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyStructure(Goal $goal, array $validated): void
    {
        $goal->measurement_mode = MeasurementMode::from($validated['measurement_mode']);

        if (! $goal->isPeriodic()) {
            $goal->indicator_direction = null;
            $goal->indicator_nature = null;
            $goal->target_semantics = null;
            $goal->period_type = null;
            $goal->period_start = null;
            $goal->period_count = null;

            return;
        }

        $goal->indicator_direction = Direction::from($validated['indicator_direction']);
        $goal->indicator_nature = Nature::from($validated['indicator_nature']);
        $goal->target_semantics = TargetSemantics::tryFrom((string) ($validated['target_semantics'] ?? '')) ?? TargetSemantics::Incremental;
        $goal->period_type = PeriodType::from($validated['period_type']);
        $goal->period_start = CarbonImmutable::createFromFormat('!Y-m', $validated['period_start']);
        $goal->period_count = (int) $validated['period_count'];
    }

    /**
     * @param  array<int|string, mixed>  $input
     * @return array<int, ?float>
     */
    private function targetsFor(Goal $goal, array $input): array
    {
        $targets = [];

        for ($number = 1; $number <= $goal->period_count; $number++) {
            $value = $input[$number] ?? null;
            $targets[$number] = $value === null || $value === '' ? null : (float) $value;
        }

        return $targets;
    }

    /**
     * @param  array<int, ?float>  $targets
     */
    private function syncPeriods(Goal $goal, array $targets): void
    {
        $existing = $goal->periods()->get()->keyBy('number');

        foreach ($this->periodGenerator->generate($goal->period_type, $goal->period_start, $goal->period_count) as $period) {
            $goalPeriod = $existing->get($period['number']) ?? $goal->periods()->make([
                'number' => $period['number'],
                'starts_on' => $period['starts_on'],
                'ends_on' => $period['ends_on'],
            ]);
            $goalPeriod->target_value = $targets[$period['number']];
            $goalPeriod->save();
        }
    }

    private function deletePeriods(Goal $goal): void
    {
        $periodIds = $goal->periods()->pluck('id');

        if ($periodIds->isEmpty()) {
            return;
        }

        // Soft-deleted reports still hold the RESTRICT foreign key.
        Report::onlyTrashed()->whereIn('goal_period_id', $periodIds)->update(['goal_period_id' => null]);
        $goal->periods()->delete();
    }
}
