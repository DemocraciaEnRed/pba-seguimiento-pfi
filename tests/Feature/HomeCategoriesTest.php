<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::forever('app_homepage_show_categories_selector', true);

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_home_shows_each_axis_as_a_bar_linking_to_the_catalog(): void
    {
        $first = $this->createCategory('Eje Integridad', 1);
        $second = $this->createCategory('Eje Sostenible', 2);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('catalog').'#eje-'.$first->id, false)
            ->assertSee(route('catalog').'#eje-'.$second->id, false)
            ->assertSeeInOrder(['Eje Integridad', 'Eje Sostenible'])
            ->assertSee('Recorré el plan completo con todos los objetivos y metas')
            ->assertDontSee('portal-home-categories', false);
    }

    public function test_axis_counts_match_the_catalog_and_skip_hidden_objectives(): void
    {
        $category = $this->createCategory('Eje Integridad', 1);
        $firstStrategic = $this->createStrategicObjective($category, 'OE1');
        $this->createStrategicObjective($category, 'OE2');

        $visible = $this->createObjective($firstStrategic, hidden: false);
        $this->createGoal($visible);
        $this->createGoal($visible);
        $this->createGoal($visible);
        $this->createGoal($this->createObjective($firstStrategic, hidden: true));

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                'Eje Integridad',
                'Estrategias', '>2<',
                'Objetivos', '>1<',
                'Metas', '>3<',
            ], false);
    }

    public function test_axes_are_not_shown_when_the_setting_is_disabled(): void
    {
        $this->createCategory('Eje Integridad', 1);
        Cache::forever('app_homepage_show_categories_selector', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Eje Integridad')
            ->assertDontSee('Recorré el plan completo con todos los objetivos y metas');
    }

    public function test_catalog_exposes_an_anchor_per_axis(): void
    {
        $category = $this->createCategory('Eje Integridad', 1);

        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('id="eje-'.$category->id.'"', false);
    }

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'observatorio-integridad';
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

    private function createObjective(StrategicObjective $strategicObjective, bool $hidden): Objective
    {
        $objective = new Objective();
        $objective->title = 'Objetivo';
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->author);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective): Goal
    {
        $goal = new Goal();
        $goal->title = 'Meta';
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }
}
