<?php

namespace Tests\Feature;

use App\Category;
use App\Objective;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStrategicObjectivesListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $mobility;

    private Category $health;

    private StrategicObjective $roads;

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

        $this->health = $this->createCategory('Salud', 2);
        $this->mobility = $this->createCategory('Movilidad', 1);
        $this->createStrategicObjective('OE-3', 'Hospitales', $this->health);
        $this->createStrategicObjective('OE-2', 'Transporte público', $this->mobility);
        $this->roads = $this->createStrategicObjective('OE-1', 'Mejorar rutas', $this->mobility);

        foreach (['Sumar ciclovías', 'Repavimentar rutas'] as $title) {
            $objective = new Objective();
            $objective->title = $title;
            $objective->content = 'Descripción';
            $objective->hidden = false;
            $objective->author()->associate($this->admin);
            $objective->strategicObjective()->associate($this->roads);
            $objective->save();
        }
    }

    public function test_list_is_grouped_by_category_with_objective_counts(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.strategic-objectives'))
            ->assertOk()
            ->assertSeeInOrder(['Movilidad', 'OE-1', 'Mejorar rutas', '2 objetivos generales', 'OE-2', 'Transporte público', '0 objetivos generales', 'Salud', 'OE-3', 'Hospitales'])
            ->assertSee(e(route('admin.objectives', ['eje' => $this->mobility->id, 'estrategico' => $this->roads->id])), false)
            ->assertSee(route('admin.categories.edit', ['categoryId' => $this->health->id]));
    }

    public function test_list_searches_by_code_or_title(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.strategic-objectives', ['q' => 'OE-3']))
            ->assertSee('Hospitales')
            ->assertDontSee('Mejorar rutas');

        $this->actingAs($this->admin)
            ->get(route('admin.strategic-objectives', ['q' => 'rutas']))
            ->assertSee('Mejorar rutas')
            ->assertDontSee('Hospitales');
    }

    public function test_list_filters_by_category(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.strategic-objectives', ['eje' => $this->health->id]))
            ->assertSee('Hospitales')
            ->assertDontSee('Transporte público');
    }

    public function test_empty_search_shows_a_no_results_message(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.strategic-objectives', ['q' => 'inexistente']))
            ->assertSee('No se encontraron objetivos estratégicos')
            ->assertDontSee('No hay objetivos estratégicos cargados');
    }

    public function test_categories_list_shows_strategic_and_general_objective_counts(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.categories'))
            ->assertOk()
            ->assertSee('list-group', false)
            ->assertSeeInOrder(['Movilidad', '2 objetivos estratégicos', '2 objetivos generales', 'Salud', '1 objetivo estratégico', '0 objetivos generales'])
            ->assertSee(e(route('admin.strategic-objectives', ['eje' => $this->mobility->id])), false)
            ->assertSee(e(route('admin.objectives', ['eje' => $this->mobility->id])), false);
    }

    public function test_objectives_list_renders_as_a_single_list_group(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.objectives'))
            ->assertOk()
            ->assertSee('Repavimentar rutas')
            ->assertSee('Sumar ciclovías');

        $this->assertSame(1, substr_count($response->getContent(), '<div class="list-group shadow-sm mb-3">'));
        $this->assertStringNotContainsString('card mb-2 shadow-sm', $response->getContent());
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
}
