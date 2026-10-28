<?php

namespace Tests\Feature;

use App\Category;
use App\Community;
use App\File;
use App\Goal;
use App\Milestone;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObjectivePanelListsTest extends TestCase
{
    use RefreshDatabase;

    private Objective $objective;

    private User $manager;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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

        $this->manager = $this->createUser('Ana', 'Coordinadora', 'manager@example.com');
        $this->reporter = $this->createUser('Beto', 'Reportero', 'reporter@example.com');

        $this->objective = new Objective();
        $this->objective->title = 'Objetivo general';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->manager);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->objective->members()->attach($this->manager, ['role' => 'manager']);
        $this->objective->members()->attach($this->reporter, ['role' => 'reporter']);
    }

    public function test_goals_list_shows_counts_and_filters_by_title_status_and_measurement(): void
    {
        $reached = $this->createGoal('Meta de vivienda', 'reached');
        $this->createGoal('Meta de transporte', 'ongoing');
        $this->createReport($reached, 'Primer reporte');

        $this->actingAs($this->manager)->get($this->goalsUrl())
            ->assertOk()
            ->assertSee('2 resultado(s)')
            ->assertViewHas('goals', fn (Collection $goals): bool => $goals->firstWhere('id', $reached->id)->reports_count === 1);

        $this->assertSame(['Meta de vivienda'], $this->goalTitles(['q' => 'vivienda']));
        $this->assertSame(['Meta de transporte'], $this->goalTitles(['estado' => 'ongoing']));
        $this->assertSame([], $this->goalTitles(['tipo' => 'periodic']));
        $this->assertSame(['Meta de vivienda', 'Meta de transporte'], $this->goalTitles(['estado' => 'desconocido', 'tipo' => 'otro']));
    }

    public function test_goals_list_distinguishes_no_goals_from_no_matches(): void
    {
        $this->actingAs($this->manager)->get($this->goalsUrl())->assertOk()->assertSee('Crear la primera meta');

        $this->createGoal('Meta de vivienda', 'ongoing');
        $this->actingAs($this->manager)->get($this->goalsUrl(['q' => 'inexistente']))
            ->assertOk()
            ->assertSee('No hay metas que coincidan con los filtros.');
    }

    public function test_team_list_filters_by_role(): void
    {
        $url = route('objectives.manage.team', ['objectiveId' => $this->objective->id]);

        $this->actingAs($this->manager)->get($url)->assertOk()->assertSee('Coordinadora, Ana')->assertSee('Reportero, Beto');
        $this->actingAs($this->manager)->get($url.'?rol=reporter')->assertOk()->assertDontSee('Coordinadora, Ana')->assertSee('Reportero, Beto');
        $this->actingAs($this->manager)->get($url.'?rol=manager')->assertOk()->assertSee('Coordinadora, Ana')->assertDontSee('Reportero, Beto');
        $this->actingAs($this->manager)->get($url.'?rol=otro')->assertOk()->assertSee('Reportero, Beto');
    }

    public function test_goal_reports_list_filters_by_title_and_type(): void
    {
        $goal = $this->createGoal('Meta', 'ongoing');
        $this->createReport($goal, 'Novedad de obra', 'post');
        $this->createReport($goal, 'Avance trimestral', 'progress');
        $url = route('objectives.manage.goals.reports', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]);

        $this->actingAs($this->manager)->get($url)->assertOk()->assertSee('2 resultado(s)');
        $this->actingAs($this->manager)->get($url.'?q=obra')->assertOk()->assertSee('Novedad de obra')->assertDontSee('Avance trimestral');
        $this->actingAs($this->manager)->get($url.'?tipo=progress')->assertOk()->assertSee('Avance trimestral')->assertDontSee('Novedad de obra');
        $this->actingAs($this->manager)->get($url.'?q=nada')->assertOk()->assertSee('No hay reportes que coincidan con los filtros.');
    }

    public function test_summaries_render_unified_stats(): void
    {
        $goal = $this->createGoal('Meta', 'reached');
        $report = $this->createReport($goal, 'Reporte');
        $milestone = new Milestone();
        $milestone->order = 1;
        $milestone->title = 'Hito inicial';
        $milestone->goal()->associate($goal);
        $milestone->save();

        $this->actingAs($this->manager)->get(route('objectives.manage.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('1 alcanzadas')
            ->assertSee('Estado declarado')
            ->assertSee('Último reporte')
            ->assertDontSee('portal-objective-stats', false);

        $goalParameters = ['objectiveId' => $this->objective->id, 'goalId' => $goal->id];
        $this->actingAs($this->manager)->get(route('objectives.manage.goals.index', $goalParameters))
            ->assertOk()
            ->assertSee('0 / 1')
            ->assertSee('Alcanzada');

        $this->actingAs($this->manager)->get(route('objectives.manage.goals.reports.index', $goalParameters + ['reportId' => $report->id]))
            ->assertOk()
            ->assertSee('Me gusta')
            ->assertSee('Archivos')
            ->assertDontSee('Mapa');
    }

    public function test_secondary_lists_render_compactly(): void
    {
        $goal = $this->createGoal('Meta', 'ongoing');
        $report = $this->createReport($goal, 'Reporte');
        $this->objective->subscribers()->attach($this->reporter);
        $this->objective->files()->save((new File())->forceFill(['name' => 'informe.pdf', 'size' => 10, 'mime' => 'application/pdf', 'path' => 'storage/informe.pdf']));
        $report->files()->save((new File())->forceFill(['name' => 'planilla.xlsx', 'size' => 10, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'path' => 'storage/planilla.xlsx']));
        $community = new Community();
        $community->label = 'Grupo vecinal';
        $community->icon = 'fas fa-users';
        $community->color = '#123456';
        $community->url = 'https://example.com/grupo';
        $community->objective()->associate($this->objective);
        $community->save();

        $objectiveParameters = ['objectiveId' => $this->objective->id];
        $this->actingAs($this->manager)->get(route('objectives.manage.subscribers', $objectiveParameters))
            ->assertOk()->assertSee('1 resultado(s)')->assertSee('reporter@example.com');
        $this->actingAs($this->manager)->get(route('objectives.manage.files', $objectiveParameters))
            ->assertOk()->assertSee('informe.pdf')->assertSee('fa-file-pdf', false);
        $this->actingAs($this->manager)->get(route('objectives.manage.goals.reports.files', $objectiveParameters + ['goalId' => $goal->id, 'reportId' => $report->id]))
            ->assertOk()->assertSee('planilla.xlsx')->assertSee('fa-file-excel', false);
        $this->actingAs($this->manager)->get(route('objectives.manage.communities', $objectiveParameters))
            ->assertOk()->assertSee('Grupo vecinal')->assertSee('https://example.com/grupo');
        $this->actingAs($this->manager)->get(route('objectives.manage.goals.milestones', $objectiveParameters + ['goalId' => $goal->id]))
            ->assertOk()->assertSee('La meta no tiene hitos.');
    }

    /**
     * @param  array<string, string>  $query
     * @return list<string>
     */
    private function goalTitles(array $query): array
    {
        return $this->actingAs($this->manager)->get($this->goalsUrl($query))
            ->assertOk()
            ->viewData('goals')
            ->pluck('title')
            ->all();
    }

    /**
     * @param  array<string, string>  $query
     */
    private function goalsUrl(array $query = []): string
    {
        return route('objectives.manage.goals', ['objectiveId' => $this->objective->id] + $query);
    }

    private function createGoal(string $title, string $status): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = $status;
        $goal->objective()->associate($this->objective);
        $goal->save();

        return $goal;
    }

    private function createReport(Goal $goal, string $title, string $type = 'post'): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = $type;
        $report->content = 'Contenido';
        $report->date = now()->toDateString();
        $report->author()->associate($this->reporter);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }

    private function createUser(string $name, string $surname, string $email): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
