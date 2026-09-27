<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalHierarchyContextTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private StrategicObjective $strategicObjective;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->category = new Category();
        $this->category->title = 'Eje Integridad';
        $this->category->icon = 'observatorio-integridad';
        $this->category->color = '#123456';
        $this->category->order = 3;
        $this->category->save();

        $this->strategicObjective = new StrategicObjective();
        $this->strategicObjective->codigo = 'OE7';
        $this->strategicObjective->title = 'Fortalecer la transparencia';
        $this->strategicObjective->category()->associate($this->category);
        $this->strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Publicar datos abiertos';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($author);
        $this->objective->strategicObjective()->associate($this->strategicObjective);
        $this->objective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Portal de datos publicado';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();

        $this->report = new Report();
        $this->report->title = 'Primer avance';
        $this->report->type = 'post';
        $this->report->content = 'Contenido';
        $this->report->date = '2026-08-01';
        $this->report->author()->associate($author);
        $this->report->goal()->associate($this->goal);
        $this->report->save();
    }

    public function test_objectives_api_includes_the_strategic_objective_and_axis_order(): void
    {
        $this->getJson(route('apiService.objectives', ['with' => 'objective_strategic_objective']))
            ->assertOk()
            ->assertJsonPath('data.0.strategic_objective', [
                'id' => $this->strategicObjective->id,
                'codigo' => 'OE7',
                'title' => 'Fortalecer la transparencia',
            ])
            ->assertJsonPath('data.0.category.order', 3);
    }

    public function test_reports_api_includes_the_full_hierarchy_when_requested(): void
    {
        $this->getJson(route('apiService.reports', ['with' => 'report_goal,report_hierarchy']))
            ->assertOk()
            ->assertJsonPath('data.0.goal.title', 'Portal de datos publicado')
            ->assertJsonPath('data.0.hierarchy.category.title', 'Eje Integridad')
            ->assertJsonPath('data.0.hierarchy.strategic_objective.title', 'Fortalecer la transparencia')
            ->assertJsonPath('data.0.hierarchy.objective.title', 'Publicar datos abiertos')
            ->assertJsonPath('data.0.hierarchy.objective.url', route('objectives.index', ['objectiveId' => $this->objective->id]));
    }

    public function test_reports_api_omits_the_hierarchy_by_default(): void
    {
        $this->getJson(route('apiService.reports'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.hierarchy');
    }

    public function test_report_page_lists_every_level_as_rows(): void
    {
        $this->get(route('reports.index', ['reportId' => $this->report->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Eje', 'Eje Integridad',
                'Objetivo estratégico', 'Fortalecer la transparencia',
                'Objetivo específico', 'Publicar datos abiertos',
                'Meta', 'Portal de datos publicado',
            ])
            ->assertSee(route('catalog').'#eje-'.$this->category->id, false);
    }

    public function test_goal_page_shows_the_axis_and_objectives_it_belongs_to(): void
    {
        $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Eje Integridad',
                'Fortalecer la transparencia',
                'Publicar datos abiertos',
                'Portal de datos publicado',
            ]);
    }

    public function test_objective_page_shows_its_strategic_objective(): void
    {
        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSeeInOrder(['Eje Integridad', 'Fortalecer la transparencia', 'Publicar datos abiertos']);
    }
}
