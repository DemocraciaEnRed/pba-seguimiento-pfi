<?php

namespace Database\Seeders;

use App\Goal;
use App\Milestone;
use App\Objective;
use App\Report;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Indicators\MeasurementMode;
use Carbon\Carbon;
use Faker\Generator;

trait SeedsDemoGoals
{
    /**
     * @param  list<int>  $team
     */
    private function seedDemoGoals(Objective $objective, array $team, Generator $faker, int $objectiveIndex): void
    {
        $modes = [
            MeasurementMode::Periodic,
            MeasurementMode::Simple,
            MeasurementMode::None,
            MeasurementMode::Periodic,
            MeasurementMode::Simple,
            MeasurementMode::Simple,
            MeasurementMode::None,
        ];
        $catalog = $this->periodicDemoCatalog();
        $periodicPosition = 0;

        foreach ($modes as $mode) {
            $goal = new Goal();
            $goal->title = $faker->sentence;
            $goal->status = 'ongoing';
            $goal->source = $faker->sentence;
            $goal->objective()->associate($objective);

            $definition = null;
            if ($mode === MeasurementMode::Periodic) {
                $definition = $catalog[($objectiveIndex * 2 + $periodicPosition++) % count($catalog)];
                $goal->title = $definition['title'];
                app(GoalIndicatorConfigurator::class)->apply($goal, $definition['configuration']);
            } elseif ($mode === MeasurementMode::Simple) {
                $goal->measurement_mode = MeasurementMode::Simple;
                $goal->indicator = $faker->sentence;
                $goal->indicator_goal = $faker->numberBetween(600, 1000);
                $goal->indicator_progress = $faker->numberBetween(0, 200);
                $goal->indicator_unit = $faker->word;
                $goal->indicator_frequency = $faker->word;
                $goal->save();
            } else {
                $goal->save();
            }

            $milestones = [];
            for ($order = 1; $order <= 5; $order++) {
                $milestone = new Milestone();
                $milestone->order = $order;
                $milestone->title = $faker->sentence;
                $milestone->goal()->associate($goal);
                $milestone->save();
                $milestones[] = $milestone;
            }

            $this->seedDemoNarrativeReports($goal, $milestones, $team, $faker);

            if ($definition !== null) {
                $this->seedDemoPeriodReports($goal, $definition['values'], $team, $faker);
            }

            if ($goal->isSimple() && $faker->boolean(30)) {
                $this->seedDemoCompletedProgress($goal, $team, $faker);
            }
        }
    }

    /**
     * @param  list<Milestone>  $milestones
     * @param  list<int>  $team
     */
    private function seedDemoNarrativeReports(Goal $goal, array $milestones, array $team, Generator $faker): void
    {
        $types = $goal->isSimple() ? ['post', 'progress', 'milestone'] : ['post', 'milestone'];
        $howManyReports = rand(1, 9);

        for ($index = 0; $index < $howManyReports; $index++) {
            $fromDate = Carbon::now()->subWeeks($howManyReports - $index)->toDateTimeString();
            $toDate = Carbon::now()->subWeeks($howManyReports - ($index + 1))->toDateTimeString();
            $reportDate = $faker->dateTimeBetween($fromDate, $toDate);

            $report = new Report();
            $report->type = $faker->randomElement($types);
            $report->tags = $faker->words(3);
            $report->title = $faker->sentence();
            $report->content = $faker->realText(450);
            $newStatus = $faker->randomElement(['ongoing', 'delayed', 'inactive']);
            $report->previous_status = $goal->status;
            $report->status = $newStatus;
            $goal->status = $newStatus;
            $goal->save();
            $report->date = $reportDate;

            switch ($report->type) {
                case 'progress':
                    $reportProgress = $faker->numberBetween(0, (int) ($goal->indicator_goal - $goal->indicator_progress));
                    $report->previous_progress = $goal->indicator_progress;
                    $report->progress = $reportProgress;
                    $goal->indicator_progress += $reportProgress;
                    $goal->save();
                    break;
                case 'milestone':
                    $milestone = array_shift($milestones);
                    if (! is_null($milestone)) {
                        $report->milestone()->associate($milestone);
                        $milestone->completed = $reportDate;
                        $milestone->save();
                    }
                    break;
            }

            if ($faker->boolean(60)) {
                $lat = $faker->latitude;
                $long = $faker->longitude;
                $report->map_lat = $lat;
                $report->map_long = $long;
                $report->map_zoom = 1;
                $report->map_center = "{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Point\",\"coordinates\":[{$long},{$lat}]}}";
                $report->map_geometries = "{\"type\":\"FeatureCollection\",\"features\":[{\"id\":\"2792417c82752ae1060a24bceac6c3bc\",\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"coordinates\":[{$long},{$lat}],\"type\":\"Point\"}}]}";
            }

            $report->created_at = $reportDate;
            $report->updated_at = $reportDate;
            $report->author()->associate($faker->randomElement($team));
            $report->goal()->associate($goal);
            $report->save();
        }
    }

    /**
     * @param  array<int, float>  $values
     * @param  list<int>  $team
     */
    private function seedDemoPeriodReports(Goal $goal, array $values, array $team, Generator $faker): void
    {
        foreach ($goal->periods as $period) {
            if (! array_key_exists($period->number, $values)) {
                continue;
            }

            $reportDate = $period->ends_on->addDays(5);

            $report = new Report();
            $report->type = 'progress';
            $report->tags = $faker->words(3);
            $report->title = "Medición del {$period->label()}";
            $report->content = $faker->realText(300);
            $report->date = $reportDate;
            $report->measured_value = $values[$period->number];
            $report->created_at = $reportDate;
            $report->updated_at = $reportDate;
            $report->author()->associate($faker->randomElement($team));
            $report->goal()->associate($goal);
            $report->period()->associate($period);
            $report->save();
        }
    }

    /**
     * @param  list<int>  $team
     */
    private function seedDemoCompletedProgress(Goal $goal, array $team, Generator $faker): void
    {
        $reportDate = $faker->dateTimeBetween(Carbon::now()->subWeeks(1)->toDateTimeString(), Carbon::now()->toDateTimeString());

        $report = new Report();
        $report->type = 'progress';
        $report->tags = $faker->words(3);
        $report->title = $faker->sentence();
        $report->content = $faker->realText(450);
        $report->previous_status = $goal->status;
        $report->status = 'reached';
        $report->previous_progress = $goal->indicator_progress;
        $report->progress = $goal->indicator_goal - $goal->indicator_progress;
        $report->date = $reportDate;
        $goal->status = 'reached';
        $goal->indicator_progress = $goal->indicator_goal;
        $goal->save();
        $report->created_at = $reportDate;
        $report->updated_at = $reportDate;
        $report->author()->associate($faker->randomElement($team));
        $report->goal()->associate($goal);
        $report->save();
    }

    /**
     * Windows are relative to today so the demo always has closed, open and upcoming periods.
     *
     * @return list<array{title: string, configuration: array<string, mixed>, values: array<int, float>}>
     */
    private function periodicDemoCatalog(): array
    {
        $twoQuartersAgo = Carbon::now()->firstOfQuarter()->subQuarters(2)->format('Y-m');
        $fiveMonthsAgo = Carbon::now()->startOfMonth()->subMonths(5)->format('Y-m');
        $fourMonthsAgo = Carbon::now()->startOfMonth()->subMonths(4)->format('Y-m');

        return [
            [
                'title' => '25 días de plazo promedio para emisión de certificados de capacidad técnico financiera',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Cantidad de días para la emisión de certificados de capacidad técnico financiera',
                    'indicator_unit' => 'días',
                    'indicator_formula' => '∑ (Fecha estado APROBADO en RegLic − Fecha estado ENTREGADO por el constructor) / Cantidad de certificados APROBADOS en el período',
                    'indicator_direction' => 'lower_is_better',
                    'indicator_nature' => 'intensive',
                    'period_type' => 'quarterly',
                    'period_start' => $twoQuartersAgo,
                    'period_count' => 4,
                    'period_targets' => [1 => 27, 2 => 27, 3 => 25, 4 => 21],
                ],
                'values' => [1 => 15.49, 2 => 25.81],
            ],
            [
                'title' => '160 días de plazo de contrataciones de obra pública',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Plazo promedio en días desde el ingreso del expediente hasta la firma del contrato',
                    'indicator_unit' => 'días',
                    'indicator_direction' => 'lower_is_better',
                    'indicator_nature' => 'intensive',
                    'period_type' => 'quarterly',
                    'period_start' => $twoQuartersAgo,
                    'period_count' => 4,
                    'period_targets' => [1 => 167, 2 => 165, 3 => 163, 4 => 160],
                ],
                'values' => [1 => 309.4, 2 => 292.1],
            ],
            [
                'title' => '60 días de plazo promedio de tramitación de certificados de obra',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Cantidad de días desde el ingreso del certificado hasta la emisión de la OP',
                    'indicator_unit' => 'días',
                    'indicator_formula' => '∑ (Fecha de emisión de OP − Fecha de solicitud de pago del contratista) / ∑ OP emitidas en el período',
                    'indicator_direction' => 'lower_is_better',
                    'indicator_nature' => 'intensive',
                    'period_type' => 'quarterly',
                    'period_start' => $twoQuartersAgo,
                    'period_count' => 4,
                    'period_targets' => [1 => null, 2 => null, 3 => 60, 4 => 60],
                ],
                'values' => [],
            ],
            [
                'title' => 'Capacitar agentes en el nuevo sistema de expedientes',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Personas capacitadas',
                    'indicator_unit' => 'personas',
                    'indicator_direction' => 'higher_is_better',
                    'indicator_nature' => 'extensive',
                    'target_semantics' => 'incremental',
                    'period_type' => 'quarterly',
                    'period_start' => $twoQuartersAgo,
                    'period_count' => 4,
                    'period_targets' => [1 => 100, 2 => 150, 3 => 150, 4 => 100],
                ],
                'values' => [1 => 90, 2 => 160],
            ],
            [
                'title' => 'Implementar el sistema de gestión documental',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Grado de avance de la implementación',
                    'indicator_unit' => '%',
                    'indicator_direction' => 'higher_is_better',
                    'indicator_nature' => 'extensive',
                    'target_semantics' => 'cumulative_level',
                    'period_type' => 'quarterly',
                    'period_start' => $twoQuartersAgo,
                    'period_count' => 4,
                    'period_targets' => [1 => 25, 2 => 50, 3 => 75, 4 => 100],
                ],
                'values' => [1 => 20, 2 => 45],
            ],
            [
                'title' => 'Sostener la dotación de inspectores de obra',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Inspectores activos',
                    'indicator_unit' => 'inspectores',
                    'indicator_direction' => 'target_is_better',
                    'indicator_nature' => 'intensive',
                    'period_type' => 'monthly',
                    'period_start' => $fiveMonthsAgo,
                    'period_count' => 6,
                    'period_targets' => [1 => 50, 2 => 50, 3 => 50, 4 => 50, 5 => 50, 6 => 50],
                ],
                'values' => [1 => 55, 2 => 48, 3 => 50, 5 => 47],
            ],
            [
                'title' => 'Reducir los expedientes vencidos',
                'configuration' => [
                    'measurement_mode' => 'periodic',
                    'indicator' => 'Expedientes vencidos en el período',
                    'indicator_unit' => 'expedientes',
                    'indicator_direction' => 'lower_is_better',
                    'indicator_nature' => 'extensive',
                    'target_semantics' => 'incremental',
                    'period_type' => 'four_monthly',
                    'period_start' => $fourMonthsAgo,
                    'period_count' => 3,
                    'period_targets' => [1 => 10, 2 => 8, 3 => 5],
                ],
                'values' => [1 => 12],
            ],
        ];
    }
}
