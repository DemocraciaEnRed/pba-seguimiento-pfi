<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminObjectivesListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $mobility;

    private Category $health;

    private StrategicObjective $roads;

    private StrategicObjective $transit;

    private StrategicObjective $hospitals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::create([
            'name' => 'Ana',
            'surname' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->markEmailAsVerified();
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $this->mobility = $this->createCategory('Movilidad', 1);
        $this->health = $this->createCategory('Salud', 2);
        $this->roads = $this->createStrategicObjective('OE-1', 'Mejorar rutas', $this->mobility);
        $this->transit = $this->createStrategicObjective('OE-2', 'Transporte público', $this->mobility);
        $this->hospitals = $this->createStrategicObjective('OE-3', 'Hospitales', $this->health);

        $bikeLanes = $this->createObjective('Sumar ciclovías', $this->roads);
        $this->travel(1)->minutes();
        $buses = $this->createObjective('Renovar colectivos', $this->transit, hidden: true);
        $this->travel(1)->minutes();
        $this->createObjective('Ampliar guardias', $this->hospitals);

        $delayedGoal = $this->createGoal('Ciclovías zona norte', $bikeLanes, 'delayed');
        $this->createGoal('Ciclovías zona sur', $bikeLanes, 'ongoing');
        $busesGoal = $this->createGoal('Flota eléctrica', $buses, 'ongoing');
        $this->createReport('Avance ciclovías', $delayedGoal, '2026-02-01');
        $this->createReport('Avance flota', $busesGoal, '2026-05-01');
        $delayedGoal->responsibles()->attach($this->admin);
    }

    public function test_list_shows_counts_last_report_and_hidden_badge(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives'))
            ->assertOk()
            ->assertSee('3 resultado(s)')
            ->assertSeeInOrder(['Ampliar guardias', 'Renovar colectivos', 'Sumar ciclovías'])
            ->assertSeeInOrder(['Sumar ciclovías', '2 metas', '1 reporte', '1 miembro', 'Último reporte: 01/02/2026'])
            ->assertSee('Oculto')
            ->assertSee('OE-2');
    }

    public function test_list_searches_by_title(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['q' => 'ciclo']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Sumar ciclovías');
    }

    public function test_list_filters_by_category_and_strategic_objective(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['eje' => $this->mobility->id]))
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Ampliar guardias')
            ->assertSee('Transporte público')
            ->assertDontSee('OE-3 · Hospitales');

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['eje' => $this->mobility->id, 'estrategico' => $this->transit->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Renovar colectivos');

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['eje' => $this->mobility->id, 'estrategico' => $this->hospitals->id]))
            ->assertSee('2 resultado(s)');
    }

    public function test_list_filters_by_visibility(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['visibilidad' => 'oculto']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Renovar colectivos');

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['visibilidad' => 'publico']))
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Renovar colectivos');
    }

    public function test_list_filters_by_goals_and_reports(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['metas' => 'con-no-cumplidas']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Sumar ciclovías');

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['metas' => 'sin-metas']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Ampliar guardias');

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['metas' => 'sin-reportes']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Ampliar guardias');
    }

    public function test_list_can_be_sorted(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['orden' => 'recientes']))
            ->assertSeeInOrder(['Ampliar guardias', 'Renovar colectivos', 'Sumar ciclovías']);

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['orden' => 'ultimo-reporte']))
            ->assertSeeInOrder(['Renovar colectivos', 'Sumar ciclovías', 'Ampliar guardias']);
    }

    public function test_pagination_keeps_the_filters(): void
    {
        foreach (range(1, 10) as $number) {
            $this->createObjective('Ruta provincial '.$number, $this->roads);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.objectives', ['eje' => $this->mobility->id]))
            ->assertSee('12 resultado(s)')
            ->assertSee('eje='.$this->mobility->id.'&amp;page=2', false);
    }

    public function test_reports_count_links_to_the_filtered_reports_section(): void
    {
        $bikeLanes = Objective::where('title', 'Sumar ciclovías')->first();

        $this->actingAs($this->admin)
            ->get(route('admin.objectives'))
            ->assertSee(e(route('admin.reports', ['eje' => $this->mobility->id, 'objetivo' => $bikeLanes->id])), false);
    }

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createStrategicObjective(string $code, string $title, Category $category): StrategicObjective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = $code;
        $strategicObjective->title = $title;
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        return $strategicObjective;
    }

    private function createObjective(string $title, StrategicObjective $strategicObjective, bool $hidden = false): Objective
    {
        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(string $title, Objective $objective, string $status): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = $status;
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    private function createReport(string $title, Goal $goal, string $date): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = $date;
        $report->author()->associate($this->admin);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }
}
