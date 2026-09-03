<?php

namespace Tests\Feature;

use App\Category;
use App\Objective;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StrategicObjectiveHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_uses_a_direct_strategic_objective_relationship(): void
    {
        $this->assertFalse(Schema::hasTable('general_objectives'));
        $this->assertFalse(Schema::hasColumn('objectives', 'category_id'));
        $this->assertFalse(Schema::hasColumn('objectives', 'general_objective_id'));
        $this->assertTrue(Schema::hasColumn('objectives', 'strategic_objective_id'));
    }

    public function test_an_objective_derives_its_category_from_its_strategic_objective(): void
    {
        $firstCategory = $this->createCategory('Eje 1', 1);
        $secondCategory = $this->createCategory('Eje 2', 2);
        $firstStrategicObjective = $this->createStrategicObjective($firstCategory, 'OE-1');
        $secondStrategicObjective = $this->createStrategicObjective($secondCategory, 'OE-2');

        $author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $objective = new Objective();
        $objective->title = 'Objetivo específico';
        $objective->content = 'Descripción';
        $objective->author()->associate($author);
        $objective->strategicObjective()->associate($firstStrategicObjective);
        $objective->save();

        $this->assertTrue($objective->fresh()->strategicObjective->is($firstStrategicObjective));
        $this->assertTrue($objective->fresh()->category->is($firstCategory));
        $this->assertTrue($firstCategory->objectives()->whereKey($objective->id)->exists());

        $objective->strategicObjective()->associate($secondStrategicObjective);
        $objective->save();

        $this->assertTrue($objective->fresh()->category->is($secondCategory));
        $this->assertFalse($firstCategory->objectives()->whereKey($objective->id)->exists());
        $this->assertTrue($secondCategory->objectives()->whereKey($objective->id)->exists());
    }

    public function test_an_admin_can_create_an_objective_for_a_strategic_objective(): void
    {
        $category = $this->createCategory('Eje 1', 1);
        $strategicObjective = $this->createStrategicObjective($category, 'OE-1');
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.objectives.create.form'), [
            'title' => 'Objetivo específico',
            'content' => 'Descripción',
            'strategic_objective' => $strategicObjective->id,
            'tags' => [],
            'organizations' => [],
        ]);

        $objective = Objective::sole();

        $response->assertRedirect(route('objectives.manage.index', ['objectiveId' => $objective->id]));
        $this->assertTrue($objective->strategicObjective->is($strategicObjective));
        $this->assertTrue($objective->category->is($category));
    }

    public function test_an_admin_cannot_create_an_objective_without_a_strategic_objective(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->from(route('admin.objectives.create'))->post(route('admin.objectives.create.form'), [
            'title' => 'Objetivo específico',
            'content' => 'Descripción',
            'tags' => [],
            'organizations' => [],
        ]);

        $response->assertRedirect(route('admin.objectives.create'));
        $response->assertSessionHasErrors('strategic_objective');
        $this->assertSame(0, Objective::count());
    }

    public function test_deleting_a_strategic_objective_reassigns_its_objectives(): void
    {
        $category = $this->createCategory('Eje 1', 1);
        $source = $this->createStrategicObjective($category, 'OE-1');
        $replacement = $this->createStrategicObjective($category, 'OE-2');
        $admin = $this->createAdmin();

        $objective = new Objective();
        $objective->title = 'Objetivo específico';
        $objective->content = 'Descripción';
        $objective->author()->associate($admin);
        $objective->strategicObjective()->associate($source);
        $objective->save();

        $response = $this->actingAs($admin)->delete(route('admin.strategic-objectives.delete.form', [
            'strategicObjectiveId' => $source->id,
        ]), [
            'strategic_objective' => $replacement->id,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.strategic-objectives'));
        $this->assertSoftDeleted($source);
        $this->assertTrue($objective->fresh()->strategicObjective->is($replacement));
    }

    public function test_deleting_a_category_reassigns_its_strategic_objectives(): void
    {
        $source = $this->createCategory('Eje 1', 1);
        $replacement = $this->createCategory('Eje 2', 2);
        $strategicObjective = $this->createStrategicObjective($source, 'OE-1');
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->delete(route('admin.categories.delete.form', [
            'categoryId' => $source->id,
        ]), [
            'category' => $replacement->id,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.categories'));
        $this->assertModelMissing($source);
        $this->assertTrue($strategicObjective->fresh()->category->is($replacement));
    }

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'fas fa-circle';
        $category->color = '#000000';
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createStrategicObjective(Category $category, string $code): StrategicObjective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = $code;
        $strategicObjective->title = "Objetivo estratégico {$code}";
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        return $strategicObjective;
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'surname' => 'Test',
            'email' => 'admin@example.com',
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
