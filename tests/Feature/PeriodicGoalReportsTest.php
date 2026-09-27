<?php

namespace Tests\Feature;

use App\Category;
use App\Exports\ObjectiveIndicatorsExport;
use App\Goal;
use App\GoalPeriod;
use App\Http\Controllers\GoalPanelController;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Indicators\MeasurementMode;
use App\Services\Indicators\PeriodState;
use App\StrategicObjective;
use App\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PeriodicGoalReportsTest extends TestCase
{
    use RefreshDatabase;

    private Objective $objective;

    private User $admin;

    private User $manager;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        // P1 and P2 are overdue, P3 is open and P4 is upcoming.
        $this->travelTo('2026-08-15 10:00:00');

        $this->admin = $this->createUser('admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#000000';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-1';
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Optimizar plazos';
        $this->objective->content = 'Descripción';
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->manager = $this->createUser('manager@example.com');
        $this->reporter = $this->createUser('reporter@example.com');
        $this->objective->members()->attach($this->manager, ['role' => 'manager']);
        $this->objective->members()->attach($this->reporter, ['role' => 'reporter']);
    }

    public function test_a_manager_creates_a_periodic_goal_and_its_periods_are_generated(): void
    {
        $response = $this->actingAs($this->manager)->post(route('objectives.manage.goals.add.form', ['objectiveId' => $this->objective->id]), [
            'title' => 'Certificados de capacidad técnico financiera',
            'status' => 'ongoing',
            'measurement_mode' => 'periodic',
            'indicator' => 'Días promedio de emisión',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => 4,
            'period_targets' => [1 => 27, 2 => 27, 3 => '', 4 => 21],
        ]);

        $goal = Goal::sole();
        $response->assertRedirect(route('objectives.manage.goals.index', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]));
        $this->assertSame(MeasurementMode::Periodic, $goal->measurement_mode);
        $this->assertNull($goal->indicator_goal);
        $this->assertSame(
            [[1, '2026-01-01', '2026-03-31', 27.0], [2, '2026-04-01', '2026-06-30', 27.0], [3, '2026-07-01', '2026-09-30', null], [4, '2026-10-01', '2026-12-31', 21.0]],
            $goal->periods->map(fn (GoalPeriod $period): array => [$period->number, $period->starts_on->toDateString(), $period->ends_on->toDateString(), $period->target_value])->all(),
        );
    }

    public function test_a_periodic_goal_needs_at_least_one_target(): void
    {
        $response = $this->actingAs($this->manager)->post(route('objectives.manage.goals.add.form', ['objectiveId' => $this->objective->id]), [
            'title' => 'Meta sin objetivos',
            'status' => 'ongoing',
            'measurement_mode' => 'periodic',
            'indicator' => 'Indicador',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => 2,
            'period_targets' => [1 => '', 2 => ''],
        ]);

        $response->assertSessionHasErrors('period_targets');
        $this->assertSame(0, Goal::count());
    }

    public function test_higher_is_better_rejects_zero_targets(): void
    {
        $response = $this->actingAs($this->manager)->post(route('objectives.manage.goals.add.form', ['objectiveId' => $this->objective->id]), [
            'title' => 'Personas capacitadas',
            'status' => 'ongoing',
            'measurement_mode' => 'periodic',
            'indicator' => 'Personas',
            'indicator_unit' => 'personas',
            'indicator_direction' => 'higher_is_better',
            'indicator_nature' => 'extensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => 1,
            'period_targets' => [1 => 0],
        ]);

        $response->assertSessionHasErrors('period_targets.1');
    }

    public function test_a_reporter_reports_a_period_and_a_second_report_for_the_same_period_is_rejected(): void
    {
        $goal = $this->createPeriodicGoal();
        $period = $goal->periods->firstWhere('number', 1);

        $this->postProgress($this->reporter, $goal, $period, 15.49)->assertSessionHasNoErrors();

        $this->postProgress($this->reporter, $goal, $period, 20)
            ->assertSessionHasErrors(['goal_period_id' => GoalPanelController::PERIOD_ALREADY_REPORTED_MESSAGE]);

        $this->assertSame(1, $goal->reports()->count());
        $this->assertSame(15.49, $period->fresh()->progressReport->measured_value);
    }

    public function test_the_database_rejects_a_second_live_progress_report_for_the_same_period(): void
    {
        $goal = $this->createPeriodicGoal();
        $period = $goal->periods->firstWhere('number', 1);
        $this->createProgressReport($goal, $period, 10);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->createProgressReport($goal, $period, 12);
    }

    public function test_deleting_a_progress_report_frees_its_period(): void
    {
        $goal = $this->createPeriodicGoal();
        $period = $goal->periods->firstWhere('number', 1);
        $this->createProgressReport($goal, $period, 10)->delete();

        $this->postProgress($this->reporter, $goal, $period, 12)->assertSessionHasNoErrors();

        $this->assertSame(12.0, $period->fresh()->progressReport->measured_value);
    }

    public function test_editing_a_periodic_progress_report_updates_the_value_but_not_the_period(): void
    {
        $goal = $this->createPeriodicGoal();
        $firstPeriod = $goal->periods->firstWhere('number', 1);
        $secondPeriod = $goal->periods->firstWhere('number', 2);
        $report = $this->createProgressReport($goal, $firstPeriod, 10);

        $response = $this->actingAs($this->reporter)->put(route('objectives.manage.goals.reports.edit.form', [
            'objectiveId' => $this->objective->id,
            'goalId' => $goal->id,
            'reportId' => $report->id,
        ]), [
            'title' => 'Avance corregido',
            'content' => 'Contenido',
            'date' => '2026-04-05',
            'measured_value' => 15.49,
            'goal_period_id' => $secondPeriod->id,
        ]);

        $response->assertSessionHasNoErrors();
        $report->refresh();
        $this->assertSame(15.49, $report->measured_value);
        $this->assertTrue($report->period->is($firstPeriod));
    }

    public function test_periods_that_are_upcoming_skipped_or_without_target_cannot_be_reported(): void
    {
        $goal = $this->createPeriodicGoal(targets: [1 => 27, 2 => null, 3 => 25, 4 => 21]);
        $skipped = $goal->periods->firstWhere('number', 1);
        $skipped->skipped_at = now();
        $skipped->skip_reason = 'Motivo';
        $skipped->save();

        foreach ([1, 2, 4] as $number) {
            $this->postProgress($this->reporter, $goal, $goal->periods->firstWhere('number', $number), 10)
                ->assertSessionHasErrors('goal_period_id');
        }

        $this->postProgress($this->reporter, $goal, $goal->periods->firstWhere('number', 3), 10)->assertSessionHasNoErrors();
    }

    public function test_a_period_of_another_goal_cannot_be_reported(): void
    {
        $goal = $this->createPeriodicGoal();
        $otherGoal = $this->createPeriodicGoal();

        $this->postProgress($this->reporter, $goal, $otherGoal->periods->first(), 10)->assertSessionHasErrors('goal_period_id');
    }

    public function test_a_goal_without_indicator_does_not_accept_progress_reports(): void
    {
        $goal = new Goal();
        $goal->title = 'Meta cualitativa';
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);
        $goal->save();

        $response = $this->actingAs($this->reporter)->post($this->newReportUrl($goal), [
            'title' => 'Avance',
            'type' => 'progress',
            'content' => 'Contenido',
            'date' => '2026-08-01',
            'progress' => 10,
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertSame(0, Report::count());
    }

    public function test_a_simple_goal_keeps_accumulating_progress(): void
    {
        $goal = new Goal();
        $goal->title = 'Meta acumulada';
        $goal->status = 'ongoing';
        $goal->measurement_mode = MeasurementMode::Simple;
        $goal->indicator = 'Personas';
        $goal->indicator_unit = 'personas';
        $goal->indicator_goal = 100;
        $goal->indicator_progress = 0;
        $goal->objective()->associate($this->objective);
        $goal->save();

        foreach ([30, 25.5] as $progress) {
            $this->actingAs($this->reporter)->post($this->newReportUrl($goal), [
                'title' => 'Avance',
                'type' => 'progress',
                'content' => 'Contenido',
                'date' => '2026-08-01',
                'progress' => $progress,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(55.5, $goal->fresh()->indicator_progress);
        $this->assertEquals(56, $goal->fresh()->progress_percentage);
    }

    public function test_only_an_admin_can_skip_and_unskip_a_period(): void
    {
        $goal = $this->createPeriodicGoal();
        $period = $goal->periods->firstWhere('number', 1);
        $skipUrl = route('objectives.manage.goals.periods.skip.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id, 'periodId' => $period->id]);
        $unskipUrl = route('objectives.manage.goals.periods.unskip.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id, 'periodId' => $period->id]);

        $this->actingAs($this->manager)->post($skipUrl, ['skip_reason' => 'Motivo'])->assertForbidden();
        $this->actingAs($this->reporter)->post($skipUrl, ['skip_reason' => 'Motivo'])->assertForbidden();
        $this->actingAs($this->admin)->post($skipUrl, [])->assertSessionHasErrors('skip_reason');

        $this->actingAs($this->admin)->post($skipUrl, ['skip_reason' => 'Paro administrativo'])->assertSessionHasNoErrors();
        $period->refresh();
        $this->assertTrue($period->isSkipped());
        $this->assertTrue($period->skippedBy->is($this->admin));
        $this->assertSame(PeriodState::Skipped, $period->state());

        $this->actingAs($this->manager)->delete($unskipUrl)->assertForbidden();
        $this->actingAs($this->admin)->delete($unskipUrl)->assertSessionHasNoErrors();
        $period->refresh();
        $this->assertFalse($period->isSkipped());
        $this->assertNull($period->skip_reason);
        $this->assertSame(PeriodState::Overdue, $period->state());
    }

    public function test_a_period_with_a_progress_report_cannot_be_skipped(): void
    {
        $goal = $this->createPeriodicGoal();
        $period = $goal->periods->firstWhere('number', 1);
        $this->createProgressReport($goal, $period, 10);

        $this->actingAs($this->admin)->post(route('objectives.manage.goals.periods.skip.form', [
            'objectiveId' => $this->objective->id,
            'goalId' => $goal->id,
            'periodId' => $period->id,
        ]), ['skip_reason' => 'Motivo'])->assertSessionHas('error');

        $this->assertFalse($period->fresh()->isSkipped());
    }

    public function test_structure_is_locked_once_progress_is_reported_but_targets_stay_editable(): void
    {
        $goal = $this->createPeriodicGoal();
        $this->createProgressReport($goal, $goal->periods->firstWhere('number', 1), 10);

        $response = $this->actingAs($this->manager)->put(route('objectives.manage.goals.edit.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]), [
            'title' => 'Meta editada',
            'status' => 'ongoing',
            'measurement_mode' => 'simple',
            'indicator' => 'Días promedio de emisión',
            'indicator_unit' => 'días',
            'period_type' => 'monthly',
            'period_count' => 12,
            'period_targets' => [1 => 30, 2 => 27, 3 => 25, 4 => 21],
        ]);

        $response->assertSessionHasNoErrors();
        $goal->refresh();
        $this->assertSame(MeasurementMode::Periodic, $goal->measurement_mode);
        $this->assertSame(4, $goal->periods()->count());
        $this->assertSame(30.0, $goal->periods()->where('number', 1)->first()->target_value);
    }

    public function test_changing_the_structure_before_any_report_regenerates_periods(): void
    {
        $goal = $this->createPeriodicGoal();

        $this->actingAs($this->manager)->put(route('objectives.manage.goals.edit.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]), [
            'title' => 'Meta editada',
            'status' => 'ongoing',
            'measurement_mode' => 'periodic',
            'indicator' => 'Días',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'four_monthly',
            'period_start' => '2026-04',
            'period_count' => 7,
            'period_targets' => [1 => 60, 7 => 40],
        ])->assertSessionHasNoErrors();

        $periods = $goal->fresh()->periods;
        $this->assertCount(7, $periods);
        $this->assertSame('2026-04-01', $periods->first()->starts_on->toDateString());
        $this->assertSame('2028-07-31', $periods->last()->ends_on->toDateString());
        $this->assertSame([60.0, null, null, null, null, null, 40.0], $periods->pluck('target_value')->all());
    }

    public function test_goal_pages_render_for_every_measurement_mode(): void
    {
        $this->withoutVite();
        $periodic = $this->createPeriodicGoal();
        $report = $this->createProgressReport($periodic, $periodic->periods->firstWhere('number', 1), 15.49);

        $simple = new Goal();
        $simple->title = 'Meta acumulada';
        $simple->status = 'ongoing';
        $simple->measurement_mode = MeasurementMode::Simple;
        $simple->indicator = 'Personas';
        $simple->indicator_unit = 'personas';
        $simple->indicator_goal = 200;
        $simple->indicator_progress = 50;
        $simple->objective()->associate($this->objective);
        $simple->save();

        $none = new Goal();
        $none->title = 'Meta cualitativa';
        $none->status = 'ongoing';
        $none->objective()->associate($this->objective);
        $none->save();

        $objectiveParameters = ['objectiveId' => $this->objective->id];
        $this->actingAs($this->admin)->get(route('objectives.manage.goals', $objectiveParameters))
            ->assertOk()->assertSee('25%')->assertSee('—');
        $this->actingAs($this->admin)->get(route('objectives.manage.goals.add', $objectiveParameters))
            ->assertOk()->assertSee('input-goal-indicator', false);
        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))->assertOk();

        foreach ([$periodic, $simple, $none] as $goal) {
            $goalParameters = $objectiveParameters + ['goalId' => $goal->id];
            $this->actingAs($this->admin)->get(route('objectives.manage.goals.index', $goalParameters))->assertOk();
            $this->actingAs($this->admin)->get(route('objectives.manage.goals.edit', $goalParameters))->assertOk();
            $this->actingAs($this->admin)->get(route('objectives.manage.goals.reports.add', $goalParameters))->assertOk();
            $this->get(route('goals.index', ['goalId' => $goal->id]))->assertOk();
        }

        $this->actingAs($this->admin)->get(route('objectives.manage.goals.index', $objectiveParameters + ['goalId' => $periodic->id]))
            ->assertOk()
            ->assertSee('174%')
            ->assertSee('Vencido sin informar')
            ->assertSee('Omitir');
        $this->actingAs($this->manager)->get(route('objectives.manage.goals.index', $objectiveParameters + ['goalId' => $periodic->id]))
            ->assertOk()
            ->assertDontSee('Omitir');
        $this->actingAs($this->reporter)->get(route('objectives.manage.goals.reports.edit', $objectiveParameters + ['goalId' => $periodic->id, 'reportId' => $report->id]))
            ->assertOk()
            ->assertSee('measured_value', false);

        $this->get(route('reports.index', ['reportId' => $report->id]))
            ->assertOk()
            ->assertSee('Medición del Período 1')
            ->assertSee('174%')
            ->assertSee('+74%')
            ->assertSee('-11,51 días', false);

        $simpleReport = new Report();
        $simpleReport->title = 'Avance acumulado';
        $simpleReport->type = 'progress';
        $simpleReport->content = 'Contenido';
        $simpleReport->date = '2026-08-01';
        $simpleReport->previous_progress = 50;
        $simpleReport->progress = 25.5;
        $simpleReport->author()->associate($this->reporter);
        $simpleReport->goal()->associate($simple);
        $simpleReport->save();

        $this->get(route('reports.index', ['reportId' => $simpleReport->id]))
            ->assertOk()
            ->assertSee('Progreso declarado')
            ->assertSee('pasó de 50 a 75,5');
    }

    public function test_the_indicators_export_reproduces_the_client_spreadsheet(): void
    {
        $goal = $this->createPeriodicGoal();
        $this->createProgressReport($goal, $goal->periods->firstWhere('number', 1), 15.49);
        $this->createProgressReport($goal, $goal->periods->firstWhere('number', 2), 25.81);

        $export = new ObjectiveIndicatorsExport($this->objective->id);
        $row = array_combine($export->headings(), $export->map($export->collection()->sole()));

        $this->assertSame([27.0, 27.0, 25.0, 21.0, 25.0], [$row['Meta P1'], $row['Meta P2'], $row['Meta P3'], $row['Meta P4'], $row['Meta ventana']]);
        $this->assertSame([15.49, '174%', '+74%'], [$row['Resultado P1'], $row['Cumplimiento P1'], $row['Desvío P1']]);
        $this->assertSame([25.81, '105%', '+5%'], [$row['Resultado P2'], $row['Cumplimiento P2'], $row['Desvío P2']]);
        $this->assertSame([null, null, null], [$row['Resultado P3'], $row['Cumplimiento P3'], $row['Desvío P3']]);
        $this->assertSame([20.65, '131%', '+31%'], [$row['Resultado a la fecha'], $row['Cumplimiento a la fecha'], $row['Desvío a la fecha']]);

        $response = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.indicators.download', ['objectiveId' => $this->objective->id]))
            ->assertDownload('20260815-indicadores-objetivo-'.$this->objective->id.'.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(",15.49,174%,+74%,25.81,105%,+5%,,,,,,,20.65,131%,+31%\r\n", $response->streamedContent());
    }

    public function test_the_summary_reproduces_the_client_spreadsheet(): void
    {
        $goal = $this->createPeriodicGoal();
        $this->createProgressReport($goal, $goal->periods->firstWhere('number', 1), 15.49);
        $this->createProgressReport($goal, $goal->periods->firstWhere('number', 2), 25.81);

        $summary = $goal->fresh()->indicatorSummary();

        $this->assertSame(174, (int) round($summary->period(1)->compliance * 100));
        $this->assertSame(105, (int) round($summary->period(2)->compliance * 100));
        $this->assertSame(PeriodState::Open, $summary->period(3)->state);
        $this->assertSame(131, (int) round($summary->toDateCompliance * 100));
    }

    /**
     * @param  array<int, ?float>  $targets
     */
    private function createPeriodicGoal(array $targets = [1 => 27, 2 => 27, 3 => 25, 4 => 21]): Goal
    {
        $goal = new Goal();
        $goal->title = 'Certificados';
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);

        app(GoalIndicatorConfigurator::class)->apply($goal, [
            'measurement_mode' => 'periodic',
            'indicator' => 'Días promedio de emisión',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => '2026-01',
            'period_count' => count($targets),
            'period_targets' => $targets,
        ]);

        return $goal->load('periods');
    }

    private function createProgressReport(Goal $goal, GoalPeriod $period, float $value): Report
    {
        $report = new Report();
        $report->title = 'Avance';
        $report->type = 'progress';
        $report->content = 'Contenido';
        $report->date = '2026-04-05';
        $report->measured_value = $value;
        $report->author()->associate($this->reporter);
        $report->goal()->associate($goal);
        $report->period()->associate($period);
        $report->save();

        return $report;
    }

    private function postProgress(User $user, Goal $goal, GoalPeriod $period, float $value): TestResponse
    {
        return $this->actingAs($user)->post($this->newReportUrl($goal), [
            'title' => 'Avance',
            'type' => 'progress',
            'content' => 'Contenido',
            'date' => '2026-08-01',
            'goal_period_id' => $period->id,
            'measured_value' => $value,
        ]);
    }

    private function newReportUrl(Goal $goal): string
    {
        return route('objectives.manage.goals.reports.add.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]);
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
