<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Initiative;
use App\Objective;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalInitiativesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->author = User::create([
            'name' => 'Ana',
            'surname' => 'Pérez',
            'email' => 'autora@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_home_shows_only_featured_initiatives_in_order(): void
    {
        $category = $this->createCategory('Integridad', 'observatorio-integridad', '#003563', 1);
        $second = Initiative::factory()->featured()->create(['title' => 'MapaInversiones PBA', 'order' => 2]);
        $first = Initiative::factory()->featured()->create(['title' => 'Ministerio Abierto', 'order' => 1]);
        $notFeatured = Initiative::factory()->create(['title' => 'Código de Ética']);
        $category->initiatives()->attach([$first->id, $second->id, $notFeatured->id]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Iniciativas destacadas')
            ->assertSeeInOrder(['Ministerio Abierto', 'MapaInversiones PBA'])
            ->assertDontSee('Código de Ética');
    }

    public function test_home_hides_the_section_without_featured_initiatives(): void
    {
        Initiative::factory()->create(['title' => 'Código de Ética']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Iniciativas destacadas')
            ->assertDontSee('data-initiatives-carousel', false);
    }

    public function test_all_cards_open_detail_and_external_links_are_inside_the_modal(): void
    {
        $linked = Initiative::factory()->featured()->create([
            'title' => 'Ministerio Abierto',
            'subtitle' => str_repeat('Texto completo de la iniciativa. ', 18),
            'order' => 1,
            'links' => [
                ['label' => 'Sitio web', 'url' => 'https://ministerioabierto.minfra.gba.gob.ar/'],
                ['label' => 'Documentación', 'url' => 'https://example.com/docs'],
            ],
        ]);
        Initiative::factory()->featured()->create(['title' => 'Código de Ética', 'order' => 2]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $triggers = $xpath->query('//article//button[@data-toggle="modal"]');
        $this->assertSame(2, $triggers->length);
        $this->assertSame(0, $xpath->query('//article[contains(@class, "initiative-card")]//a')->length);
        $this->assertSame(0, $xpath->query('//*[@id="app"]//div[contains(@class, "initiative-modal")]')->length);

        foreach ($triggers as $trigger) {
            $id = $trigger->getAttribute('aria-controls');
            $this->assertSame('#'.$id, $trigger->getAttribute('data-target'));
            $modal = $xpath->query('//*[@id="'.$id.'"]')->item(0);
            $this->assertNotNull($modal);
            if (str_contains($trigger->getAttribute('aria-label'), $linked->title)) {
                $this->assertStringContainsString($linked->subtitle, $modal->textContent);
                $this->assertSame(2, $xpath->query('.//a[@target="_blank" and @rel="noopener noreferrer"]', $modal)->length);
                $this->assertStringContainsString('Documentación', $modal->textContent);
            } else {
                $this->assertSame(0, $xpath->query('.//a', $modal)->length);
            }
        }
        $this->assertStringContainsString('href="https://ministerioabierto.minfra.gba.gob.ar/" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('--initiative-color: #009aae', $html);
    }

    public function test_home_cached_attributes_keep_links_and_escape_detail_content(): void
    {
        $initiative = Initiative::factory()->featured()->create([
            'title' => 'Título <script>alert(1)</script>',
            'subtitle' => 'Texto <b>sin HTML</b>',
            'links' => [['label' => '<b>Etiqueta</b>', 'url' => 'https://example.com/?a=1&b=2']],
        ]);
        Cache::put('home.initiatives', []);

        foreach (range(1, 2) as $request) {
            $this->get(route('home'))->assertOk()
                ->assertSee(e($initiative->title), false)
                ->assertSee(e($initiative->subtitle), false)
                ->assertSee(e($initiative->links[0]['label']), false)
                ->assertSee(e($initiative->links[0]['url']), false)
                ->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->assertIsArray(Cache::get(Initiative::HOME_CACHE_KEY));
    }

    public function test_initiative_card_renders_background_and_logo(): void
    {
        Initiative::factory()->featured()->create([
            'background_path' => 'fondo.jpg',
            'logo_path' => 'logo.png',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('initiative-card--has-image', false)
            ->assertSee(Initiative::first()->backgroundUrl(), false)
            ->assertSee('<div class="initiative-card__logo">', false)
            ->assertSee(Initiative::first()->logoUrl(), false);
    }

    public function test_plan_shows_intro_documents_and_axis_navigation(): void
    {
        $category = $this->createCategory('Integridad', 'observatorio-integridad', '#003563', 1);

        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('¿Qué es el PFI?')
            ->assertSeeInOrder(['Brechas', 'Capacidades', 'Resultados', 'Impactos', 'Valor público'])
            ->assertSee('¿Cómo se hace el seguimiento?')
            ->assertSee(config('plan.documents.0.url'), false)
            ->assertSee(config('plan.documents.1.url'), false)
            ->assertSee('href="#eje-'.$category->id.'"', false);
    }

    public function test_plan_renders_a_section_per_axis_with_its_content(): void
    {
        $integrity = $this->createCategory('Integridad', 'observatorio-integridad', '#003563', 1);
        $integrity->forceFill(['description' => 'Promueve la transparencia activa.'])->save();
        $innovation = $this->createCategory('Innovación', 'observatorio-innovacion', '#58c0dc', 2);

        $objective = $this->createObjective($integrity, 'Fortalecer la integridad', 'Publicar datos abiertos');
        $this->createObjective($integrity, 'Objetivo oculto', 'Meta oculta', hidden: true);

        $shared = Initiative::factory()->create(['title' => 'Ministerio Abierto']);
        $shared->categories()->attach([$integrity->id, $innovation->id]);
        $innovationOnly = Initiative::factory()->create(['title' => 'SIO PBA']);
        $innovationOnly->categories()->attach($innovation);

        $html = $this->get(route('catalog'))->assertOk()->getContent();
        $integritySection = $this->section($html, $integrity);
        $innovationSection = $this->section($html, $innovation);

        $this->assertStringContainsString('Eje 1', $integritySection);
        $this->assertStringContainsString('Promueve la transparencia activa.', $integritySection);
        $this->assertStringContainsString('--axis-color: #003563', $integritySection);
        $this->assertStringContainsString('Fortalecer la integridad', $integritySection);
        $this->assertStringContainsString('Publicar datos abiertos', $integritySection);
        $this->assertStringContainsString(route('objectives.index', $objective->id), $integritySection);
        $this->assertStringNotContainsString('Meta oculta', $integritySection);
        $this->assertStringContainsString(e(route('reports', ['category' => $integrity->id])), $integritySection);
        $this->assertStringContainsString('category='.$integrity->id, $integritySection);
        $this->assertStringContainsString('Ministerio Abierto', $integritySection);
        $this->assertStringContainsString('--initiative-color: #003563', $integritySection);
        $this->assertStringContainsString('--carousel-color: #003563', $integritySection);
        $this->assertStringNotContainsString('SIO PBA', $integritySection);

        $this->assertStringContainsString('Eje 2', $innovationSection);
        $this->assertStringContainsString('Ministerio Abierto', $innovationSection);
        $this->assertStringContainsString('SIO PBA', $innovationSection);
        $this->assertStringContainsString('--initiative-color: #58c0dc', $innovationSection);
        $this->assertStringContainsString('No hay objetivos estratégicos cargados.', $innovationSection);
    }

    public function test_plan_query_count_does_not_grow_with_axes_or_initiatives(): void
    {
        $this->seedAxisWithContent(1);
        $baseline = $this->countCatalogQueries();

        $this->seedAxisWithContent(2);
        $this->seedAxisWithContent(3);

        $this->assertSame($baseline, $this->countCatalogQueries());
    }

    public function test_shared_initiative_has_unique_modals_with_each_axis_color(): void
    {
        $integrity = $this->createCategory('Integridad', 'observatorio-integridad', '#003563', 1);
        $innovation = $this->createCategory('Innovación', 'observatorio-innovacion', '#58c0dc', 2);
        $initiative = Initiative::factory()->withLink()->create(['subtitle' => str_repeat('Detalle ', 75)]);
        $initiative->categories()->attach([$integrity->id, $innovation->id]);

        $xpath = $this->xpath($this->get(route('catalog'))->assertOk()->getContent());
        $ids = [];
        foreach ([$integrity, $innovation] as $category) {
            $trigger = $xpath->query('//*[@id="eje-'.$category->id.'"]//button[@data-toggle="modal"]')->item(0);
            $this->assertNotNull($trigger);
            $id = $trigger->getAttribute('aria-controls');
            $ids[] = $id;
            $modal = $xpath->query('//*[@id="'.$id.'"]')->item(0);
            $this->assertSame('--initiative-color: '.$category->color, $modal->getAttribute('style'));
            $this->assertStringContainsString($initiative->subtitle, $modal->textContent);
            $this->assertSame(1, $xpath->query('.//a[@target="_blank"]', $modal)->length);
        }
        $this->assertCount(2, array_unique($ids));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    private function countCatalogQueries(): int
    {
        cache()->flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('catalog'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    private function seedAxisWithContent(int $order): void
    {
        $category = $this->createCategory("Eje {$order}", 'observatorio-integridad', '#003563', $order);
        $this->createObjective($category, "Objetivo {$order}", "Meta {$order}");
        Initiative::factory()->count(2)->create()->each(fn (Initiative $initiative) => $initiative->categories()->attach($category));
    }

    private function section(string $html, Category $category): string
    {
        $start = strpos($html, '<section id="eje-'.$category->id.'"');
        $this->assertNotFalse($start, "No se encontró la sección del eje {$category->title}");

        $end = strpos($html, '<section id="eje-', $start + 1);

        return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
    }

    private function createCategory(string $title, string $icon, string $color, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = $icon;
        $category->color = $color;
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createObjective(Category $category, string $title, string $goalTitle, bool $hidden = false): Objective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-'.$category->id.'-'.$title;
        $strategicObjective->title = "OE {$title}";
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->author);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        $goal = new Goal();
        $goal->title = $goalTitle;
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $objective;
    }
}
