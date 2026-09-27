<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\Setting;
use App\StrategicObjective;
use App\User;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MapsDisabledTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Objective $objective;

    private Goal $goal;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsTableSeeder::class);
        Cache::flush();

        $this->admin = User::create([
            'name' => 'Ana',
            'surname' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#000000';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-1';
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Objetivo';
        $this->objective->content = 'Descripción';
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Meta';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();

        $this->report = new Report();
        $this->report->title = 'Reporte';
        $this->report->type = 'post';
        $this->report->content = 'Contenido';
        $this->report->date = '2026-04-05';
        $this->report->tags = [];
        $this->report->author()->associate($this->admin);
        $this->report->goal()->associate($this->goal);
        $this->report->save();
    }

    public function test_the_seeder_leaves_maps_disabled(): void
    {
        $this->assertFalse(app_setting('app_map_enabled'));
        $this->assertFalse(app_setting('app_homepage_show_map'));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function booleanValues(): array
    {
        return [
            'string false' => ['false', false],
            'string zero' => ['0', false],
            'empty string' => ['', false],
            'null' => [null, false],
            'string one' => ['1', true],
            'string true' => ['true', true],
        ];
    }

    #[DataProvider('booleanValues')]
    public function test_boolean_settings_are_casted_from_their_stored_value(mixed $storedValue, bool $expected): void
    {
        $setting = new Setting();
        $setting->type = 'boolean';
        $setting->value = $storedValue;

        $this->assertSame($expected, $setting->casted_value);
    }

    public function test_the_home_does_not_render_the_map_when_maps_are_disabled(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('<map-reports', false)
            ->assertDontSee('api.mapbox.com', false);
    }

    public function test_the_home_renders_the_map_when_maps_are_enabled(): void
    {
        $this->enableMaps();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<map-reports', false)
            ->assertSee('api.mapbox.com', false);
    }

    public function test_map_routes_return_not_found_when_maps_are_disabled(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('objectives.manage.map', $this->objectiveParameters()))->assertNotFound();
        $this->put(route('objectives.manage.configuration.map.form', $this->objectiveParameters()))->assertNotFound();
        $this->get(route('objectives.manage.goals.reports.map', $this->reportParameters()))->assertNotFound();
        $this->put(route('objectives.manage.goals.reports.map.form', $this->reportParameters()))->assertNotFound();
    }

    public function test_map_routes_are_available_when_maps_are_enabled(): void
    {
        $this->enableMaps();
        $this->actingAs($this->admin);

        $this->get(route('objectives.manage.map', $this->objectiveParameters()))->assertOk();
        $this->put(route('objectives.manage.configuration.map.form', $this->objectiveParameters()))->assertRedirect();
        $this->get(route('objectives.manage.goals.reports.map', $this->reportParameters()))->assertOk();
        $this->put(route('objectives.manage.goals.reports.map.form', $this->reportParameters()))->assertRedirect();
    }

    public function test_the_objective_configuration_hides_the_map_form_when_maps_are_disabled(): void
    {
        $this->actingAs($this->admin)
            ->get(route('objectives.manage.configuration', $this->objectiveParameters()))
            ->assertOk()
            ->assertDontSee('<set-map-default', false)
            ->assertDontSee('api.mapbox.com', false);
    }

    public function test_the_objective_configuration_shows_the_map_form_when_maps_are_enabled(): void
    {
        $this->enableMaps();

        $this->actingAs($this->admin)
            ->get(route('objectives.manage.configuration', $this->objectiveParameters()))
            ->assertOk()
            ->assertSee('<set-map-default', false);
    }

    public function test_admins_can_still_reach_the_map_settings_to_enable_maps(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.map'))
            ->assertOk()
            ->assertSee('Habilitar georeferenciación')
            ->assertDontSee('checked', false);
    }

    private function enableMaps(): void
    {
        Setting::whereIn('name', ['app_map_enabled', 'app_homepage_show_map'])->update(['value' => true]);
        Cache::flush();
    }

    /**
     * @return array{objectiveId: int}
     */
    private function objectiveParameters(): array
    {
        return ['objectiveId' => $this->objective->id];
    }

    /**
     * @return array{objectiveId: int, goalId: int, reportId: int}
     */
    private function reportParameters(): array
    {
        return [
            'objectiveId' => $this->objective->id,
            'goalId' => $this->goal->id,
            'reportId' => $this->report->id,
        ];
    }
}
