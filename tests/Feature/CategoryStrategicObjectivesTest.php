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

class CategoryStrategicObjectivesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_category_with_strategic_objectives(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.categories.create.form'), [
            'title' => 'Educación',
            'icon' => 'fas fa-book',
            'color' => '#123456',
            'order' => 1,
            'strategic_objectives' => [
                ['codigo' => 'OE-001', 'title' => 'Primer objetivo'],
                ['codigo' => 'OE-002', 'title' => 'Segundo objetivo'],
            ],
        ]);

        $category = Category::sole();

        $response->assertRedirect(route('admin.categories'));
        $this->assertCount(2, $category->strategicObjectives);
        $this->assertSame(['OE-001', 'OE-002'], $category->strategicObjectives->pluck('codigo')->all());
    }

    public function test_admin_can_edit_a_category_and_sync_its_strategic_objectives(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $existingObjective = $this->createStrategicObjective($category, 'OE-001', 'Original');

        $response = $this->actingAs($admin)->put(route('admin.categories.edit.form', ['categoryId' => $category->id]), [
            'title' => 'Educación actualizada',
            'icon' => 'fas fa-book-open',
            'color' => '#654321',
            'order' => 2,
            'strategic_objectives' => [
                [
                    'id' => $existingObjective->id,
                    'codigo' => 'OE-001-A',
                    'title' => 'Actualizado',
                    'delete' => 0,
                ],
                ['codigo' => 'OE-002', 'title' => 'Nuevo objetivo'],
            ],
        ]);

        $response->assertRedirect(route('admin.categories'));
        $this->assertSame('Educación actualizada', $category->fresh()->title);
        $this->assertSame(['OE-001-A', 'OE-002'], $category->fresh()->strategicObjectives->pluck('codigo')->all());
    }

    public function test_admin_can_delete_an_unassigned_strategic_objective_from_the_combined_form(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $strategicObjective = $this->createStrategicObjective($category, 'OE-001', 'A eliminar');

        $response = $this->actingAs($admin)->put(route('admin.categories.edit.form', ['categoryId' => $category->id]), [
            'title' => $category->title,
            'icon' => $category->icon,
            'color' => $category->color,
            'order' => $category->order,
            'strategic_objectives' => [[
                'id' => $strategicObjective->id,
                'codigo' => $strategicObjective->codigo,
                'title' => $strategicObjective->title,
                'delete' => 1,
            ]],
        ]);

        $response->assertRedirect(route('admin.categories'));
        $this->assertSoftDeleted($strategicObjective);
    }

    public function test_admin_cannot_delete_a_strategic_objective_with_specific_objectives(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $strategicObjective = $this->createStrategicObjective($category, 'OE-001', 'En uso');
        $objective = new Objective();
        $objective->title = 'Objetivo específico';
        $objective->content = 'Descripción';
        $objective->author()->associate($admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        $response = $this->actingAs($admin)->from(route('admin.categories.edit', ['categoryId' => $category->id]))
            ->put(route('admin.categories.edit.form', ['categoryId' => $category->id]), [
                'title' => $category->title,
                'icon' => $category->icon,
                'color' => $category->color,
                'order' => $category->order,
                'strategic_objectives' => [[
                    'id' => $strategicObjective->id,
                    'codigo' => $strategicObjective->codigo,
                    'title' => $strategicObjective->title,
                    'delete' => 1,
                ]],
            ]);

        $response->assertRedirect(route('admin.categories.edit', ['categoryId' => $category->id]));
        $response->assertSessionHasErrors('strategic_objectives');
        $this->assertModelExists($strategicObjective);
    }

    private function createCategory(): Category
    {
        $category = new Category();
        $category->title = 'Educación';
        $category->icon = 'fas fa-book';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        return $category;
    }

    private function createStrategicObjective(Category $category, string $code, string $title): StrategicObjective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = $code;
        $strategicObjective->title = $title;
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        return $strategicObjective;
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'surname' => 'Test',
            'email' => uniqid('admin-', true).'@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $admin->roles()->attach($role);

        return $admin;
    }
}
