<?php

namespace Tests\Feature;

use App\Category;
use App\Event;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\PublicSitemap;
use App\Services\SearchIndexing;
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
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use LengthException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Objective $objective;

    private Objective $hiddenObjective;

    private Goal $goal;

    private Goal $hiddenGoal;

    private Report $report;

    private Report $hiddenReport;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsTableSeeder::class);
        Cache::flush();
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
        $this->objective->title = 'Publicar datos';
        $this->objective->content = 'Datos del plan';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->user);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->hiddenObjective = $this->objective->replicate();
        $this->hiddenObjective->hidden = true;
        $this->hiddenObjective->save();

        $this->goal = $this->createGoal($this->objective);
        $this->hiddenGoal = $this->createGoal($this->hiddenObjective);
        $this->report = $this->createReport($this->goal);
        $this->hiddenReport = $this->createReport($this->hiddenGoal);

        $this->event = new Event();
        $this->event->title = 'Presentación del plan';
        $this->event->content = 'Actividad pública';
        $this->event->date = now()->addDay();
        $this->event->author()->associate($this->user);
        $this->event->save();
    }

    public function test_indexing_disabled_keeps_sitemaps_unavailable_and_robots_permissive(): void
    {
        $this->get(route('sitemap'))->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get(route('sitemap.part', ['section' => 'objectives', 'page' => 1]))->assertNotFound();
        $response = $this->get(route('robots'))->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame("User-agent: *\nDisallow:\n", $response->getContent());
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }

    public function test_missing_setting_also_disables_the_sitemap(): void
    {
        Setting::where('name', 'app_search_indexing_enabled')->firstOrFail()->delete();
        $this->get(route('sitemap'))->assertNotFound();
        $this->get(route('robots'))->assertOk()->assertDontSee('Sitemap:');
    }

    public function test_sitemap_contains_only_unique_public_canonical_urls(): void
    {
        $this->enableIndexing();
        $response = $this->get(route('sitemap', ['s' => 'ignored', 'utm_source' => 'test']))->assertOk();
        $xml = $this->xml($response, 'urlset');
        $urls = $this->locations($xml, 'url');
        $expected = array_map(fn (string $name): string => route($name), SearchIndexing::PUBLIC_PAGE_ROUTES);
        $expected = [...$expected,
            route('objectives.index', [$this->objective->id]),
            route('goals.index', [$this->goal->id]),
            route('reports.index', [$this->report->id]),
            route('events.index', [$this->event->id]),
        ];
        $this->assertEqualsCanonicalizing($expected, $urls);
        $this->assertSame($urls, array_values(array_unique($urls)));
        $this->assertCount(13, $urls);
        $this->assertSame(0, $xml->query('//s:priority | //s:changefreq')->length);

        foreach ($urls as $url) {
            $page = $this->get($url)->assertOk();
            $page->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertHeader('X-Robots-Tag', 'index, follow');
            $this->assertNull(parse_url($url, PHP_URL_QUERY));
        }
    }

    public function test_sitemap_visibility_does_not_depend_on_the_current_user(): void
    {
        $this->enableIndexing();
        $anonymous = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->hiddenObjective->members()->attach($this->user, ['role' => 'reporter']);
        $this->actingAs($this->user)->get(route('sitemap'))->assertOk()->assertContent($anonymous);
        $this->makeAdmin();
        $this->get(route('sitemap'))->assertOk()->assertContent($anonymous);
        $this->get(route('sitemap.part', ['section' => 'objectives', 'page' => 1]))
            ->assertOk()->assertDontSee(route('objectives.index', [$this->hiddenObjective->id]), false);
    }

    public function test_changing_visibility_updates_the_very_next_response(): void
    {
        $this->enableIndexing();
        $this->get(route('sitemap'))->assertOk()->assertSee(route('reports.index', [$this->report->id]), false);
        $this->objective->hidden = true;
        $this->objective->save();
        $urls = $this->locations($this->xml($this->get(route('sitemap'))->assertOk(), 'urlset'), 'url');
        $this->assertCount(10, $urls);
        $this->assertNotContains(route('objectives.index', [$this->objective->id]), $urls);
        $this->assertNotContains(route('goals.index', [$this->goal->id]), $urls);
        $this->assertNotContains(route('reports.index', [$this->report->id]), $urls);
        $this->get(route('sitemap.part', ['section' => 'objectives', 'page' => 1]))->assertNotFound();
        $this->get(route('sitemap.part', ['section' => 'goals', 'page' => 1]))->assertNotFound();
        $this->get(route('sitemap.part', ['section' => 'reports', 'page' => 1]))->assertNotFound();

        $this->objective->hidden = false;
        $this->objective->save();
        $this->assertCount(13, $this->locations($this->xml($this->get(route('sitemap'))->assertOk(), 'urlset'), 'url'));
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function deletedEntities(): array
    {
        return [
            'objective' => ['objective', 10],
            'goal' => ['goal', 11],
            'report' => ['report', 12],
            'event' => ['event', 12],
        ];
    }

    #[DataProvider('deletedEntities')]
    public function test_soft_deleted_entities_and_their_descendants_are_excluded(string $entity, int $count): void
    {
        $this->enableIndexing();
        $this->get(route('sitemap'))->assertOk();
        $this->{$entity}->delete();
        $urls = $this->locations($this->xml($this->get(route('sitemap'))->assertOk(), 'urlset'), 'url');
        $this->assertCount($count, $urls);
    }

    public function test_robots_and_all_sitemap_endpoints_change_immediately_after_admin_saves(): void
    {
        $this->makeAdmin();
        foreach ([true, false] as $enabled) {
            $this->get(route('robots'));
            $this->get(route('sitemap'));
            $this->put(route('admin.settings.form'), [
                'name' => 'app_search_indexing_enabled',
                'type' => 'boolean',
                'value' => $enabled ? 'true' : 'false',
                'cached' => 'true',
            ])->assertRedirect()->assertSessionHas('success');

            $robots = $this->get(route('robots'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame(
                "User-agent: *\nDisallow:\n".($enabled ? "\nSitemap: ".route('sitemap')."\n" : ''),
                $robots->getContent(),
            );
            foreach ([route('sitemap'), route('sitemap.part', ['section' => 'pages', 'page' => 1])] as $url) {
                $this->get($url)->assertStatus($enabled ? 200 : 404)
                    ->assertHeader('Cache-Control', 'no-store, private');
            }
        }
    }

    public function test_lastmod_uses_stored_dates_instead_of_the_generation_time(): void
    {
        $this->enableIndexing();
        $this->objective->updated_at = '2025-01-02 03:04:05';
        $this->objective->save();
        Event::whereKey($this->event->id)->update(['updated_at' => null]);
        $xml = $this->xml($this->get(route('sitemap'))->assertOk(), 'urlset');
        $url = route('objectives.index', [$this->objective->id]);
        $this->assertSame(
            $this->objective->updated_at->toAtomString(),
            $xml->evaluate('string(//s:url[s:loc="'.$url.'"]/s:lastmod)'),
        );
        $this->assertSame(0, $xml->query('//s:url[s:loc="'.route('home').'"]/s:lastmod')->length);
        $this->assertSame(0, $xml->query('//s:url[s:loc="'.route('events.index', [$this->event->id]).'"]/s:lastmod')->length);
    }

    public function test_sitemap_urls_use_https_and_are_correctly_xml_escaped(): void
    {
        $this->enableIndexing();
        URL::forceRootUrl('https://seo.example.test/planes/a&b');
        URL::forceScheme('https');
        try {
            $response = $this->get('https://seo.example.test/sitemap.xml')->assertOk()->assertSee('a&amp;b', false);
            $urls = $this->locations($this->xml($response, 'urlset'), 'url');
            foreach ($urls as $url) {
                $this->assertStringStartsWith('https://seo.example.test/planes/a&b', $url);
            }
            $this->get('https://seo.example.test/robots.txt')->assertOk()->assertSee('Sitemap: '.route('sitemap'), false);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    public function test_the_exact_batch_limit_is_a_single_sitemap_and_the_next_url_creates_an_index(): void
    {
        $this->enableIndexing();
        $this->insertEvents(PublicSitemap::URLS_PER_FILE - 13);
        $response = $this->get(route('sitemap'))->assertOk();
        $this->assertCount(PublicSitemap::URLS_PER_FILE, $this->locations($this->xml($response, 'urlset'), 'url'));
        $this->insertEvents(1);
        $xml = $this->xml($this->get(route('sitemap'))->assertOk(), 'sitemapindex');
        $this->assertCount(5, $this->locations($xml, 'sitemap'));
        $this->assertSame(0, $xml->query('//s:lastmod')->length);
    }

    public function test_split_sitemaps_are_complete_bounded_and_have_no_duplicates(): void
    {
        $this->enableIndexing();
        $this->insertEvents(PublicSitemap::URLS_PER_FILE);
        $indexResponse = $this->get(route('sitemap'))->assertOk();
        $parts = $this->locations($this->xml($indexResponse, 'sitemapindex'), 'sitemap');
        $this->assertCount(6, $parts);
        $urls = [];
        foreach ($parts as $part) {
            $response = $this->get($part)->assertOk();
            $partUrls = $this->locations($this->xml($response, 'urlset'), 'url');
            $this->assertLessThanOrEqual(PublicSitemap::URLS_PER_FILE, count($partUrls));
            $urls = [...$urls, ...$partUrls];
        }
        $this->assertCount(PublicSitemap::URLS_PER_FILE + 13, $urls);
        $this->assertSame($urls, array_values(array_unique($urls)));
        $eventPart = route('sitemap.part', ['section' => 'events', 'page' => 2]);
        $this->assertCount(1, $this->locations($this->xml($this->get($eventPart)->assertOk(), 'urlset'), 'url'));
        $this->event->delete();
        $this->get($eventPart)->assertNotFound();

        $index = $this->xml($this->get(route('sitemap'))->assertOk(), 'sitemapindex');
        $this->assertNotContains($eventPart, $this->locations($index, 'sitemap'));
        $this->enableIndexing(false);
        foreach ([route('sitemap'), ...$parts] as $part) {
            $this->get($part)->assertNotFound();
        }
    }

    public function test_empty_and_invalid_sitemap_parts_return_404(): void
    {
        $this->enableIndexing();
        foreach ([
            '/sitemaps/unknown/1.xml',
            '/sitemaps/objectives/0.xml',
            '/sitemaps/objectives/-1.xml',
            '/sitemaps/objectives/01.xml',
            '/sitemaps/objectives/999999999999999999999.xml',
            '/sitemaps/objectives/2.xml',
            '/sitemaps/pages/2.xml',
            '/sitemaps/reports/999.xml',
        ] as $url) {
            $this->get($url)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        }
    }

    public function test_sitemap_queries_are_batched_without_loading_entity_relationships(): void
    {
        $this->enableIndexing();
        app_setting('app_search_indexing_enabled');
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->get(route('sitemap'))->assertOk();
            $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
            DB::disableQueryLog();
            $this->insertEvents(PublicSitemap::URLS_PER_FILE);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get(route('sitemap'))->assertOk();
            $this->assertSame(4, count(DB::getQueryLog()));
            DB::flushQueryLog();
            $this->get(route('sitemap.part', ['section' => 'events', 'page' => 1]))->assertOk();
            $queries = DB::getQueryLog();
            $this->assertCount(1, $queries);
            $this->assertStringContainsString('`id`, `updated_at`', $queries[0]['query']);
            $this->assertStringContainsString('limit 1000', $queries[0]['query']);
        } finally {
            DB::disableQueryLog();
        }
    }

    /** @return array<string, array{0: int, 1: bool}> */
    public static function xmlSizes(): array
    {
        return [
            'exact protocol limit' => [PublicSitemap::MAX_XML_BYTES, false],
            'one byte over limit' => [PublicSitemap::MAX_XML_BYTES + 1, true],
        ];
    }

    #[DataProvider('xmlSizes')]
    public function test_xml_exceeding_the_byte_limit_is_rejected_explicitly(int $size, bool $rejected): void
    {
        $view = Mockery::mock(\Illuminate\Contracts\View\View::class);
        $view->shouldReceive('with')->once()->andReturnSelf();
        $view->shouldReceive('render')->once()->andReturn(str_repeat(' ', $size));
        View::shouldReceive('make')->once()->with('sitemap::sitemap', [], [])->andReturn($view);
        if ($rejected) {
            $this->expectException(LengthException::class);
            $this->expectExceptionMessage('The sitemap exceeds the protocol limit of 50 MB.');
        }

        $xml = (new PublicSitemap())->render();
        $this->assertSame($size, strlen($xml));
    }

    private function enableIndexing(bool $enabled = true): void
    {
        $setting = Setting::where('name', 'app_search_indexing_enabled')->firstOrFail();
        $setting->value = $enabled;
        $setting->save();
    }

    private function createGoal(Objective $objective): Goal
    {
        $goal = new Goal();
        $goal->title = 'Portal de datos';
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    private function createReport(Goal $goal): Report
    {
        $report = new Report();
        $report->title = 'Avance';
        $report->content = 'Publicación de datos';
        $report->type = 'post';
        $report->date = '2026-08-01';
        $report->author()->associate($this->user);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }

    private function insertEvents(int $count): void
    {
        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'title' => 'Actividad '.$index,
                'content' => 'Contenido',
                'date' => '2026-08-01 10:00:00',
                'author_id' => $this->user->id,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ];
        }
        Event::insert($rows);
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

    private function xml(TestResponse $response, string $root): DOMXPath
    {
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');
        $content = $response->getContent();
        $this->assertLessThanOrEqual(PublicSitemap::MAX_XML_BYTES, strlen($content));
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($content));
        $this->assertSame($root, $document->documentElement->localName);
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $document->documentElement->namespaceURI);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xpath;
    }

    /** @return list<string> */
    private function locations(DOMXPath $xml, string $tag): array
    {
        $urls = [];
        foreach ($xml->query('//s:'.$tag.'/s:loc') as $node) {
            $urls[] = $node->textContent;
        }

        return $urls;
    }
}
