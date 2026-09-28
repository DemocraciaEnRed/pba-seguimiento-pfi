<?php

namespace Tests\Feature;

use App\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_delegates_axis_cards_to_the_stats_component(): void
    {
        $this->createCategory('Eje Integridad', 1);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('catalog-url="'.route('catalog').'"', false)
            ->assertSee('objectives-url="'.route('objectives').'"', false)
            ->assertSee('Recorré el plan completo con todos los objetivos y metas')
            ->assertDontSee('catalog-tree__bar', false)
            ->assertDontSee('Eje Integridad');
    }

    public function test_axes_are_shown_regardless_of_the_legacy_selector_setting(): void
    {
        Cache::forever('app_homepage_show_categories_selector', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('catalog-url="'.route('catalog').'"', false)
            ->assertSee('Recorré el plan completo con todos los objetivos y metas');
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
}
