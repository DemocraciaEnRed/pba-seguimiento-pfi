<?php

namespace Tests\Feature;

use App\Category;
use App\Event;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\Setting;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

    private Event $event;

    private User $user;

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
        $this->objective->title = 'Datos abiertos';
        $this->objective->content = 'Contenido del objetivo';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->user);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Publicar datos';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();

        $this->report = new Report();
        $this->report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $this->report->title = 'Primer avance';
        $this->report->content = 'Contenido del reporte';
        $this->report->type = 'post';
        $this->report->date = '2026-08-01';
        $this->report->author()->associate($this->user);
        $this->report->goal()->associate($this->goal);
        $this->report->save();

        $this->event = new Event();
        $this->event->title = 'Actividad';
        $this->event->content = 'Contenido de la actividad';
        $this->event->date = now()->addDay();
        $this->event->author()->associate($this->user);
        $this->event->save();
    }

    /** @return array<string, array{0: bool}> */
    public static function indexingStates(): array
    {
        return ['disabled' => [false], 'enabled' => [true]];
    }

    #[DataProvider('indexingStates')]
    public function test_public_pages_follow_the_indexing_setting(bool $enabled): void
    {
        $this->setIndexing($enabled);

        foreach (['home', 'catalog', 'objectives', 'reports', 'about.general', 'about.faq', 'about.legal', 'events.upcoming', 'events.past'] as $name) {
            $this->assertRobots($this->get(route($name))->assertOk(), $enabled);
        }

        foreach ($this->entityUrls() as $url) {
            $this->assertRobots($this->get($url)->assertOk(), $enabled);
        }

        $this->assertRobots($this->get(route('events.index', [$this->event->id]))->assertOk(), $enabled);
        $this->head(route('home'))->assertOk()->assertHeader('X-Robots-Tag', $enabled ? 'index, follow' : 'noindex, follow');
    }

    public function test_a_missing_setting_keeps_public_pages_out_of_the_index(): void
    {
        Setting::where('name', 'app_search_indexing_enabled')->firstOrFail()->delete();
        $this->assertRobots($this->get(route('home'))->assertOk(), false);
    }

    #[DataProvider('indexingStates')]
    public function test_private_pages_remain_noindex(bool $enabled): void
    {
        $this->setIndexing($enabled);
        $this->assertRobots($this->get(route('login'))->assertOk(), false);
        $this->assertRobots($this->get(route('register'))->assertOk(), false);
        $this->assertRobots($this->get(route('password.request'))->assertOk(), false);

        $this->makeAdmin();
        $role = new Role();
        $role->name = 'user';
        $role->description = 'Usuario';
        $role->save();
        $this->user->roles()->attach($role);
        foreach (['admin.settings.seo', 'panel.index'] as $name) {
            $this->assertRobots($this->get(route($name))->assertOk(), false);
        }
        $this->assertRobots($this->get(route('objectives.manage.edit', [$this->objective->id]))->assertOk(), false);
        foreach ($this->entityUrls() as $url) {
            $this->assertRobots($this->get($url)->assertOk(), $enabled);
        }
    }

    public function test_hidden_entities_remain_noindex_for_admins_and_members_and_404_for_guests(): void
    {
        $this->setIndexing(true);
        $this->objective->hidden = true;
        $this->objective->save();

        foreach ($this->entityUrls() as $url) {
            $this->get($url)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        }

        $this->objective->members()->attach($this->user, ['role' => 'reporter']);
        $this->goal->reporters()->attach($this->user);
        $this->actingAs($this->user);
        foreach ($this->entityUrls() as $url) {
            $this->assertRobots($this->get($url)->assertOk(), false);
        }

        $this->makeAdmin();
        foreach ($this->entityUrls() as $url) {
            $this->assertRobots($this->get($url)->assertOk(), false);
        }
    }

    public function test_api_downloads_unknown_routes_and_errors_are_not_indexable(): void
    {
        $this->setIndexing(true);
        $this->getJson(route('apiService.reports'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/seo-does-not-exist')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get(route('objectives.index', [999999]))->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/catalogo')->assertRedirect('/plan')->assertHeader('X-Robots-Tag', 'noindex, follow');

        Route::middleware('web')->get('/seo-unknown-page', fn () => view('layouts.app'))->name('seo.unknown');
        $this->assertRobots($this->get('/seo-unknown-page')->assertOk(), false);

        Route::get('/seo-download', fn () => response('file contents', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="test.pdf"',
        ]));
        $this->get('/seo-download')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');

        Route::get('/seo-public-error', fn () => abort(503))->name('home');
        $this->get('/seo-public-error')->assertStatus(503)->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_entity_routes_are_excluded_unless_the_controller_confirms_public_visibility(): void
    {
        $this->setIndexing(true);
        Route::middleware('web')->get('/seo-unconfirmed-entity', fn () => view('layouts.app'))->name('objectives.index');
        $this->assertRobots($this->get('/seo-unconfirmed-entity')->assertOk(), false);
    }

    public function test_non_read_requests_never_become_indexable(): void
    {
        $this->setIndexing(true);
        Route::middleware('web')->post('/seo-post', fn () => view('layouts.app'))->name('home');
        $this->assertRobots($this->post('/seo-post')->assertOk(), false);
    }

    public function test_the_migration_adds_a_disabled_setting_without_overwriting_an_existing_value(): void
    {
        $migration = require database_path('migrations/2026_10_16_152807_add_search_indexing_enabled_setting.php');
        $this->setIndexing(true);
        $migration->up();
        $this->assertTrue(app_setting('app_search_indexing_enabled'));
        $this->assertSame(1, Setting::where('name', 'app_search_indexing_enabled')->count());

        $migration->down();
        $this->assertNull(Setting::where('name', 'app_search_indexing_enabled')->first());
        $this->assertFalse(app_setting('app_search_indexing_enabled', false));
        $migration->up();
        $this->assertFalse(app_setting('app_search_indexing_enabled'));
    }

    private function setIndexing(bool $enabled): void
    {
        $setting = Setting::where('name', 'app_search_indexing_enabled')->firstOrFail();
        $setting->value = $enabled;
        $setting->save();
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

    /** @return list<string> */
    private function entityUrls(): array
    {
        return [
            route('objectives.index', [$this->objective->id]),
            route('goals.index', [$this->goal->id]),
            route('reports.index', [$this->report->id]),
        ];
    }

    private function assertRobots(TestResponse $response, bool $indexable): void
    {
        $directives = $indexable ? 'index, follow' : 'noindex, follow';
        $response->assertHeader('X-Robots-Tag', $directives)
            ->assertSee('<meta name="robots" content="'.$directives.'">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<meta name="robots"'));
    }
}
