<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelSideMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reporter;

    private Objective $objective;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = $this->createUser('admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);
        $this->reporter = $this->createUser('reporter@example.com');

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
        $this->goal->reporters()->attach($this->reporter);
    }

    public function test_category_links_its_title_and_shows_the_create_button(): void
    {
        $this->blade('<x-side-menu.category label="Ejes" icon="fas fa-tags" href="/ejes" :active="true" create-href="/ejes/crear" create-label="Crear eje" />')
            ->assertSee('<a href="/ejes" class="category is-active"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<a href="/ejes/crear" class="side-menu__add" title="Crear eje" aria-label="Crear eje">', false)
            ->assertDontSee('menu-link', false);
    }

    public function test_category_without_link_renders_a_heading_with_its_items(): void
    {
        $this->blade('<x-side-menu.category label="Administrar"><a class="item-link" href="/config">Configuración</a></x-side-menu.category>')
            ->assertSee('<h6 class="category mb-0">Administrar</h6>', false)
            ->assertSee('<div class="menu-link"><a class="item-link" href="/config">Configuración</a></div>', false)
            ->assertDontSee('side-menu__add', false);
    }

    public function test_admin_menu_links_titles_to_lists_and_create_buttons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.categories'))
            ->assertOk()
            ->assertSee('<a href="'.route('admin.categories').'" class="category is-active"', false)
            ->assertSee('<a href="'.route('admin.categories.create').'" class="side-menu__add" title="Crear eje"', false)
            ->assertSee('aria-label="Crear objetivo general"', false)
            ->assertDontSee('>Listar</a>', false)
            ->assertSeeInOrder(['aria-label="Plan"', 'Ejes', 'Organizaciones', 'aria-label="Participación"', 'Reportes', 'Comentarios', 'Actividades', 'aria-label="Portal"', 'Preguntas frecuentes', 'Personalizar Home', 'aria-label="Sistema"', 'Usuarios', 'Bitácora de acciones'], false);
    }

    public function test_group_renders_its_label(): void
    {
        $this->blade('<x-side-menu.group label="Plan"><a class="category" href="/ejes">Ejes</a></x-side-menu.group>')
            ->assertSee('role="group" aria-label="Plan"', false)
            ->assertSee('<p class="side-menu__group-label">Plan</p>', false);

        $this->blade('<x-side-menu.group><a class="category" href="/">Inicio</a></x-side-menu.group>')
            ->assertDontSee('side-menu__group-label', false);
    }

    public function test_dashboard_stats_use_the_grid_utilities(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('d-grid grid-cols-2 grid-cols-md-3 grid-cols-lg-5 gap-3', false)
            ->assertDontSee('col-lg-3 mb-3', false);
    }

    public function test_objective_menu_shows_structural_actions_only_to_admins(): void
    {
        $url = route('objectives.manage.index', ['objectiveId' => $this->objective->id]);

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('aria-label="Nueva meta"', false)
            ->assertSee(route('admin.goal-assignments.objective', ['objectiveId' => $this->objective->id]))
            ->assertSeeInOrder(['>Seguimiento<', '>Participación<', '>Equipo<', '>Configuración<'], false)
            ->assertSee('Editar objetivo');

        $this->actingAs($this->reporter)->get($url)
            ->assertOk()
            ->assertSee(route('objectives.manage.goals', ['objectiveId' => $this->objective->id]))
            ->assertDontSee('aria-label="Nueva meta"', false)
            ->assertDontSee('aria-label="Agregar miembro"', false)
            ->assertDontSee('Editar objetivo')
            ->assertDontSee('aria-label="Configuración"', false);
    }

    public function test_goal_menu_marks_the_create_button_as_active(): void
    {
        $this->actingAs($this->admin)
            ->get(route('objectives.manage.goals.milestones.add', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('class="side-menu__add is-active" title="Nuevo hito"', false)
            ->assertSee('aria-label="Nuevo reporte"', false);
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'name' => 'Nombre',
            'surname' => 'Apellido',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}
