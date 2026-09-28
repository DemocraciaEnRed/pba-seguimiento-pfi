<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\StrategicObjective;
use App\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_reuses_existing_hierarchy(): void
    {
        $this->seed(RoleTableSeeder::class);
        $author = User::create([
            'name' => 'Autor',
            'surname' => 'Base',
            'email' => 'autor@example.com',
            'password' => Hash::make('password'),
        ]);

        $category = new Category();
        $category->title = 'Eje existente';
        $category->icon = 'observatorio-integridad';
        $category->color = '#003563';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE1';
        $strategicObjective->title = 'Objetivo estratégico existente';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objectives = collect(['Objetivo general A', 'Objetivo general B'])->map(function (string $title) use ($strategicObjective, $author): Objective {
            $objective = new Objective();
            $objective->title = $title;
            $objective->content = $title;
            $objective->strategicObjective()->associate($strategicObjective);
            $objective->author()->associate($author);
            $objective->save();

            return $objective;
        });

        $this->seed(DemoSeeder::class);

        $this->assertSame(1, Category::query()->count());
        $this->assertSame(1, StrategicObjective::query()->count());
        $this->assertSame(2, Objective::query()->count());

        foreach ($objectives as $objective) {
            $objective->refresh();

            $this->assertFalse((bool) $objective->hidden);
            $this->assertSame($author->id, $objective->author_id);
            $this->assertSame($strategicObjective->id, $objective->strategic_objective_id);
            $this->assertSame(3, $objective->goals()->count());
            $this->assertSame(6, $objective->members()->count());
            $this->assertSame(3, $objective->organizations()->count());
        }

        $this->assertSame(6, Goal::query()->count());
        $this->assertTrue(User::query()->where('email', 'admin@admin.com')->first()->hasRole('admin'));
    }
}
