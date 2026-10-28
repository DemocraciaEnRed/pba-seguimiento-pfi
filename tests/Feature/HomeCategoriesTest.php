<?php

namespace Tests\Feature;

use App\Category;
use App\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\SettingsTableSeeder;
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
            ->assertSee('plan-cover-url="'.collect(config('plan.documents'))->firstWhere('acronym', 'PFI')['cover'].'"', false)
            ->assertDontSee('Recorré el plan completo con todos los objetivos y metas')
            ->assertDontSee('catalog-tree__bar', false)
            ->assertDontSee('Eje Integridad');
    }

    public function test_axes_are_shown_without_the_legacy_selector_setting(): void
    {
        $this->seed(SettingsTableSeeder::class);

        $this->assertFalse(Setting::where('name', 'app_homepage_show_categories_selector')->exists());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('catalog-url="'.route('catalog').'"', false)
            ->assertSee('plan-cover-url=', false);
    }

    public function test_catalog_exposes_an_anchor_per_axis(): void
    {
        $category = $this->createCategory('Eje Integridad', 1);

        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('id="eje-'.$category->id.'"', false);
    }

    public function test_home_links_to_the_configured_digital_library_in_a_new_tab(): void
    {
        $libraryUrl = 'https://www.minfra.gba.gob.ar/test-bibliotecadigital/home';
        $this->assertSame($libraryUrl, config('plan.library_url'));

        config(['plan.library_url' => $libraryUrl.'?origen=mapa']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.$libraryUrl.'?origen=mapa" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('Biblioteca Digital')
            ->assertSee('se abre en una pestaña nueva');
    }

    public function test_the_plan_invitation_uses_the_pfi_cover_not_the_first_document(): void
    {
        config(['plan.documents' => [
            ['acronym' => 'PEI', 'cover' => 'https://example.test/pei.png'],
            ['acronym' => 'PFI', 'cover' => 'https://example.test/pfi.png'],
        ]]);
        $this->get(route('home'))->assertOk()
            ->assertSee('plan-cover-url="https://example.test/pfi.png"', false)
            ->assertDontSee('plan-cover-url="https://example.test/pei.png"', false);

        config(['plan.documents' => []]);
        $this->get(route('home'))->assertOk()->assertSee('plan-cover-url=""', false);
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
