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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminHeroTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

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

        $category = new Category();
        $category->title = 'Eje sostenible';
        $category->icon = 'observatorio-sostenible';
        $category->color = '#12a24f';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE1';
        $strategicObjective->title = 'Objetivo estratégico verde';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Reducir emisiones';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Plantar árboles';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();

        $this->report = new Report();
        $this->report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $this->report->title = 'Avance de forestación';
        $this->report->type = 'post';
        $this->report->content = 'Contenido';
        $this->report->date = now();
        $this->report->author()->associate($this->admin);
        $this->report->goal()->associate($this->goal);
        $this->report->save();
    }

    public function test_admin_pages_render_the_title_in_the_hero(): void
    {
        foreach (['admin.index', 'admin.comments', 'admin.logs'] as $routeName) {
            $this->assertRendersHero($this->actingAs($this->admin)->get(route($routeName)))
                ->assertSee('<h1 class="hero__title">Administración</h1>', false);
        }
    }

    public function test_user_panel_renders_the_title_in_the_hero(): void
    {
        $user = User::create([
            'name' => 'Carla',
            'surname' => 'Vecina',
            'email' => 'vecina@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();
        $userRole = new Role();
        $userRole->name = 'user';
        $userRole->description = 'Usuario';
        $userRole->save();
        $user->roles()->attach($userRole);

        $this->assertRendersHero($this->actingAs($user)->get(route('panel.index')))
            ->assertSee('<h1 class="hero__title">Mi panel</h1>', false)
            ->assertSee('Resumen de tu cuenta')
            ->assertSee('Vecina, Carla')
            ->assertDontSee('Hola, Carla')
            ->assertDontSee('animate__', false);
    }

    public function test_objective_management_renders_the_objective_in_the_hero(): void
    {
        $this->assertRendersHero($this->actingAs($this->admin)->get(route('objectives.manage.index', ['objectiveId' => $this->objective->id])))
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Eje sostenible', 'Objetivo estratégico verde', 'Panel del objetivo general', 'Reducir emisiones', 'Ver en el portal', 'Resumen del objetivo'])
            ->assertDontSee('¡Bienvenido al panel de control del objetivo!');
    }

    public function test_goal_management_renders_the_goal_in_the_hero(): void
    {
        $this->assertRendersHero($this->actingAs($this->admin)->get(route('objectives.manage.goals.index', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id])))
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Eje sostenible', 'Reducir emisiones', 'Panel de la meta', 'Plantar árboles', 'Ver en el portal', 'Resumen de la meta']);
    }

    public function test_report_management_renders_the_report_in_the_hero(): void
    {
        $this->assertRendersHero($this->actingAs($this->admin)->get(route('objectives.manage.goals.reports.index', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'reportId' => $this->report->id])))
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Reducir emisiones', 'Meta:', 'Plantar árboles', 'Panel del reporte', 'Avance de forestación', 'Ver en el portal', 'Resumen del reporte']);
    }

    private function assertRendersHero(TestResponse $response): TestResponse
    {
        return $response->assertOk()
            ->assertSee('class="hero hero--', false)
            ->assertDontSee('app-portal-header', false)
            ->assertDontSee('push-to-header', false);
    }
}
