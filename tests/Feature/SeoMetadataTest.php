<?php

namespace Tests\Feature;

use App\Category;
use App\Event;
use App\Goal;
use App\ImageFile;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\SeoMetadata;
use App\Setting;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Database\Seeders\SettingsTableSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsTableSeeder::class);
        Cache::flush();
        $this->setSetting('app_social_title', 'Plan provincial');
        $this->setSetting('app_social_description', 'Descripción general de la plataforma.');
        $this->user = UserFactory::new()->create(['surname' => 'User']);

        $category = new Category();
        $category->title = 'Integridad';
        $category->icon = 'fas fa-star';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE1';
        $strategicObjective->title = 'Transparencia';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Publicar datos abiertos';
        $this->objective->content = 'Información sobre el acceso a datos públicos.';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->user);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Portal publicado';
        $this->goal->indicator = 'Cantidad de conjuntos de datos';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();

        $this->report = new Report();
        $this->report->title = 'Avance de la meta';
        $this->report->content = 'Se publicaron los primeros datos.';
        $this->report->type = 'post';
        $this->report->date = '2026-08-01';
        $this->report->author()->associate($this->user);
        $this->report->goal()->associate($this->goal);
        $this->report->save();

        $this->event = new Event();
        $this->event->title = 'Presentación del portal';
        $this->event->content = 'Actividad para conocer los datos publicados.';
        $this->event->date = now()->addDay();
        $this->event->author()->associate($this->user);
        $this->event->save();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sectionPages(): array
    {
        return [
            'home' => ['home', 'Plan provincial'],
            'plan' => ['catalog', 'El Plan - Plan provincial'],
            'objectives' => ['objectives', 'Objetivos - Plan provincial'],
            'reports' => ['reports', 'Seguimiento - Plan provincial'],
            'about' => ['about.general', 'Acerca de - Plan provincial'],
            'help' => ['about.faq', 'Ayuda y preguntas frecuentes - Plan provincial'],
            'legal' => ['about.legal', 'Información legal - Plan provincial'],
            'upcoming' => ['events.upcoming', 'Próximas actividades - Plan provincial'],
            'past' => ['events.past', 'Actividades pasadas - Plan provincial'],
        ];
    }

    #[DataProvider('sectionPages')]
    public function test_public_sections_have_specific_metadata_and_clean_canonicals(string $routeName, string $title): void
    {
        $url = route($routeName);
        $response = $this->get($url.'?utm_source=newsletter&fbclid=test')->assertOk();
        $metadata = $this->assertMetadata($response, $url);
        $this->assertSame($title, $metadata['title']);
        $this->assertSame(asset('social-sharer.png'), $metadata['image']);
        if ($routeName === 'home') {
            $this->assertSame('Descripción general de la plataforma.', $metadata['description']);
        } else {
            $this->assertNotSame('Descripción general de la plataforma.', $metadata['description']);
        }
        $response->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_entity_pages_use_their_content_and_named_canonical_routes(): void
    {
        $objectiveUrl = route('objectives.index', [$this->objective->id]);
        $metadata = $this->assertMetadata($this->get($objectiveUrl.'?utm_campaign=test')->assertOk(), $objectiveUrl);
        $this->assertSame('Publicar datos abiertos - Plan provincial', $metadata['title']);
        $this->assertSame($this->objective->content, $metadata['description']);

        $goalUrl = route('goals.index', [$this->goal->id]);
        $metadata = $this->assertMetadata($this->get($goalUrl)->assertOk(), $goalUrl);
        $this->assertSame('Portal publicado - Plan provincial', $metadata['title']);
        $this->assertStringContainsString($this->goal->title, $metadata['description']);
        $this->assertStringContainsString($this->goal->indicator, $metadata['description']);
        $this->assertStringContainsString($this->objective->title, $metadata['description']);

        $reportUrl = route('reports.index', [$this->report->id]);
        $response = $this->get($reportUrl)->assertOk();
        $metadata = $this->assertMetadata($response, $reportUrl);
        $this->assertSame('Avance de la meta - Portal publicado - Plan provincial', $metadata['title']);
        $this->assertSame($this->report->content, $metadata['description']);
        $response->assertSee('<h1 class="hero__title">Avance de la meta</h1>', false);
        $this->assertSame('Avance de la meta', $this->report->fresh()->title);

        $eventUrl = route('events.index', [$this->event->id]);
        $metadata = $this->assertMetadata($this->get($eventUrl)->assertOk(), $eventUrl);
        $this->assertSame('Presentación del portal - Plan provincial', $metadata['title']);
        $this->assertSame($this->event->content, $metadata['description']);
    }

    public function test_report_title_does_not_repeat_the_goal_when_it_already_has_context(): void
    {
        $this->report->title = 'Avance de Portal publicado';
        $this->report->save();
        $url = route('reports.index', [$this->report->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertSame('Avance de Portal publicado - Plan provincial', $metadata['title']);
    }

    public function test_metadata_normalizes_text_and_escapes_attributes_without_changing_content(): void
    {
        $this->objective->title = "  Gestión \"abierta\" & participación\nciudadana  ";
        $this->objective->content = "<p>Más   información</p><p>con \"comillas\", &amp; datos.</p>Segunda<br>línea.";
        $this->objective->save();
        $url = route('objectives.index', [$this->objective->id]);
        $response = $this->get($url)->assertOk();
        $metadata = $this->assertMetadata($response, $url);
        $this->assertSame('Gestión "abierta" & participación ciudadana - Plan provincial', $metadata['title']);
        $this->assertSame('Más información con "comillas", & datos. Segunda línea.', $metadata['description']);
        $response->assertSee('content="Más información con &quot;comillas&quot;, &amp; datos. Segunda línea."', false);
        $this->assertSame($this->objective->content, $this->objective->fresh()->content);
    }

    public function test_long_descriptions_are_limited_to_160_characters_without_truncating_titles(): void
    {
        $this->objective->content = str_repeat('Información pública accesible. ', 30);
        $this->objective->title = str_repeat('Objetivo de participación ', 6);
        $this->objective->save();
        $url = route('objectives.index', [$this->objective->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertLessThanOrEqual(160, mb_strlen($metadata['description']));
        $this->assertStringEndsWith('...', $metadata['description']);
        $this->assertSame(trim($this->objective->title).' - Plan provincial', $metadata['title']);
    }

    public function test_empty_descriptions_and_settings_have_meaningful_fallbacks(): void
    {
        $this->objective->content = " \n ";
        $this->objective->save();
        $url = route('objectives.index', [$this->objective->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertSame($this->objective->title, $metadata['description']);

        $this->goal->indicator = null;
        $this->goal->save();
        $url = route('goals.index', [$this->goal->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertStringNotContainsString('Indicador:', $metadata['description']);
        $this->assertStringContainsString($this->objective->title, $metadata['description']);

        $this->report->content = '';
        $this->report->save();
        $url = route('reports.index', [$this->report->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertStringContainsString('Reporte de la meta '.$this->goal->title, $metadata['description']);

        $this->event->content = '';
        $this->event->save();
        $url = route('events.index', [$this->event->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertSame($this->event->title, $metadata['description']);

        $this->setSetting('app_social_title', " \n ");
        $this->setSetting('app_social_description', null);
        config(['app.name' => 'Nombre de respaldo']);
        $metadata = $this->assertMetadata($this->get(route('home'))->assertOk(), route('home'));
        $this->assertSame('Nombre de respaldo', $metadata['title']);
        $this->assertSame('Nombre de respaldo', $metadata['description']);
    }

    public function test_entities_use_their_images_and_goals_inherit_the_objective_cover(): void
    {
        $this->setSetting('app_social_image', '/storage/settings/global.png');
        $cover = $this->image('storage/objectives/cover.jpg');
        $this->objective->cover()->save($cover);

        foreach ([
            route('objectives.index', [$this->objective->id]),
            route('goals.index', [$this->goal->id]),
        ] as $url) {
            $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
            $this->assertSame(asset('storage/objectives/cover.jpg'), $metadata['image']);
        }

        $this->report->photos()->save($this->image('/storage/reports/first.jpg'));
        $this->report->photos()->save($this->image('/storage/reports/second.jpg'));
        $url = route('reports.index', [$this->report->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertSame(asset('storage/reports/first.jpg'), $metadata['image']);

        $this->event->photos()->save($this->image('https://images.example.com/event.jpg'));
        $url = route('events.index', [$this->event->id]);
        $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
        $this->assertSame('https://images.example.com/event.jpg', $metadata['image']);
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function globalImages(): array
    {
        return [
            'null' => [null, '/social-sharer.png'],
            'empty' => ['', '/social-sharer.png'],
            'whitespace' => [" \n ", '/social-sharer.png'],
            'relative' => ['storage/settings/social.png', '/storage/settings/social.png'],
            'root relative' => ['/storage/settings/social.png', '/storage/settings/social.png'],
            'absolute https' => ['https://images.example.com/social.png', 'https://images.example.com/social.png'],
            'absolute http' => ['http://images.example.com/social.png', 'http://images.example.com/social.png'],
            'scheme relative' => ['//images.example.com/social.png', 'http://images.example.com/social.png'],
        ];
    }

    #[DataProvider('globalImages')]
    public function test_image_urls_are_absolute_and_use_the_global_fallback(?string $path, string $expected): void
    {
        $this->setSetting('app_social_image', $path);
        foreach ([route('home'), route('reports.index', [$this->report->id])] as $url) {
            $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
            $this->assertSame(url($expected), $metadata['image']);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function listingFilters(): array
    {
        return [
            'search' => ['objectives', 's=datos'],
            'category' => ['objectives', 'category=1'],
            'strategic objective' => ['objectives', 'strategic_objective=1'],
            'sorting objectives' => ['objectives', 'sort=last_report'],
            'type' => ['reports', 'type=post'],
            'objective' => ['reports', 'objective=1'],
            'goal' => ['reports', 'goal=1'],
            'date' => ['reports', 'date_range=last_year'],
            'sorting reports' => ['reports', 'sort=oldest'],
            'legacy sorting' => ['reports', 'order_by=date,desc'],
            'empty search' => ['objectives', 's='],
            'array input' => ['reports', 'category[]=1'],
        ];
    }

    #[DataProvider('listingFilters')]
    public function test_searches_and_filters_are_noindex_with_the_section_canonical(string $name, string $query): void
    {
        $this->setSetting('app_search_indexing_enabled', '1');
        $url = route($name);
        $response = $this->get($url.'?'.$query.'&utm_source=test')->assertOk();
        $this->assertMetadata($response, $url);
        $response->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_tracking_parameters_do_not_prevent_public_indexing(): void
    {
        $this->setSetting('app_search_indexing_enabled', '1');
        foreach (['home', 'objectives', 'reports'] as $name) {
            $url = route($name);
            $response = $this->get($url.'?utm_source=test&gclid=test')->assertOk();
            $this->assertMetadata($response, $url);
            $response->assertHeader('X-Robots-Tag', 'index, follow')
                ->assertSee('<meta name="robots" content="index, follow">', false);
        }
    }

    public function test_existing_event_pagination_has_its_own_canonical(): void
    {
        foreach (['events.upcoming', 'events.past'] as $name) {
            $url = route($name, ['page' => 2]);
            $this->assertMetadata($this->get($url.'&utm_source=test')->assertOk(), $url);
            $this->assertMetadata($this->get(route($name, ['page' => 1]))->assertOk(), route($name));
            $this->assertMetadata($this->get(route($name, ['page' => ['invalid']]))->assertOk(), route($name));
        }
    }

    public function test_forced_https_is_used_for_canonicals_and_scheme_relative_images(): void
    {
        $this->setSetting('app_social_image', '//images.example.com/social.png');
        URL::forceScheme('https');
        try {
            $url = route('catalog');
            $metadata = $this->assertMetadata($this->get($url)->assertOk(), $url);
            $this->assertStringStartsWith('https://', $url);
            $this->assertSame('https://images.example.com/social.png', $metadata['image']);
        } finally {
            URL::forceScheme(null);
        }
    }

    public function test_private_pages_and_hidden_entities_do_not_advertise_a_canonical_url(): void
    {
        $this->assertMetadata($this->get(route('login'))->assertOk(), null);
        $this->makeAdmin();
        $response = $this->get(route('admin.settings.seo'))->assertOk();
        $this->assertMetadata($response, null);
        $response->assertSee('src="'.asset('social-sharer.png').'"', false);

        $this->setSetting('app_social_image', 'https://images.example.com/social.png');
        $this->get(route('admin.settings.seo'))->assertOk()->assertSee('src="https://images.example.com/social.png"', false);
        $this->objective->hidden = true;
        $this->objective->save();
        $this->setSetting('app_search_indexing_enabled', '1');
        foreach ([
            route('objectives.index', [$this->objective->id]),
            route('goals.index', [$this->goal->id]),
            route('reports.index', [$this->report->id]),
        ] as $url) {
            $response = $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
            $this->assertMetadata($response, null);
        }
    }

    public function test_metadata_composition_does_not_query_entity_relationships(): void
    {
        $this->objective->load('cover');
        $this->report->load('photos');
        $this->event->load('photos');
        foreach (['app_social_title', 'app_social_description', 'app_social_image'] as $key) {
            app_setting($key);
        }
        $metadata = new SeoMetadata();
        $metadata->imageUrl();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $metadata->objective($this->objective);
        $metadata->goal($this->goal, $this->objective);
        $metadata->report($this->report, $this->goal, $this->objective);
        $metadata->event($this->event);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $queries);
    }

    private function setSetting(string $name, ?string $value): void
    {
        $setting = Setting::where('name', $name)->firstOrFail();
        $setting->value = $value;
        $setting->save();
    }

    private function image(string $path): ImageFile
    {
        $image = new ImageFile();
        $image->name = 'social.jpg';
        $image->path = $path;
        $image->size = '100';
        $image->mime = 'image/jpeg';

        return $image;
    }

    private function makeAdmin(): void
    {
        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $this->user->roles()->attach($role);
        $this->actingAs($this->user);
    }

    /** @return array{title: string, description: string, image: string} */
    private function assertMetadata(TestResponse $response, ?string $url): array
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//head/title')->length);
        $title = $xpath->evaluate('string(//head/title)');
        $values = [];
        foreach (['title', 'description', 'og:type', 'og:title', 'og:description', 'og:image', 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'] as $key) {
            $attribute = str_starts_with($key, 'og:') ? 'property' : 'name';
            $selector = '//head/meta[@'.$attribute.'="'.$key.'"]';
            $this->assertSame(1, $xpath->query($selector)->length, $key);
            $values[$key] = $xpath->evaluate('string('.$selector.'/@content)');
            $this->assertNotSame('', $values[$key], $key);
        }
        $this->assertSame($title, $values['title']);
        $this->assertSame($title, $values['og:title']);
        $this->assertSame($title, $values['twitter:title']);
        $this->assertSame($values['description'], $values['og:description']);
        $this->assertSame($values['description'], $values['twitter:description']);
        $this->assertSame($values['og:image'], $values['twitter:image']);
        $this->assertSame('summary_large_image', $values['twitter:card']);
        $this->assertLessThanOrEqual(160, mb_strlen($values['description']));
        foreach (['//head/link[@rel="canonical"]/@href', '//head/meta[@property="og:url"]/@content', '//head/meta[@name="twitter:url"]/@content'] as $selector) {
            $this->assertSame($url === null ? 0 : 1, $xpath->query($selector)->length, $selector);
            if ($url !== null) {
                $this->assertSame($url, $xpath->evaluate('string('.$selector.')'));
            }
        }

        return ['title' => $title, 'description' => $values['description'], 'image' => $values['og:image']];
    }
}
