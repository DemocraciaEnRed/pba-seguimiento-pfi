<?php

namespace Tests\Feature;

use App\Role;
use App\Setting;
use App\User;
use Database\Factories\UserFactory;
use Database\Seeders\SettingsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
        $this->admin->markEmailAsVerified();
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);
    }

    public function test_the_general_settings_section_was_removed(): void
    {
        $this->assertFalse(Route::has('admin.settings'));
        $this->actingAs($this->admin)->get('/admin/configuracion/editar')->assertStatus(405);
        $this->actingAs($this->admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertDontSee('Configuración general')
            ->assertSee('Personalizar Home')
            ->assertSee('SEO y Analytics');
    }

    public function test_the_seeder_no_longer_creates_the_static_asset_settings(): void
    {
        $this->assertSame(0, Setting::whereIn('name', [
            'app_logo_color',
            'app_logo_white',
            'app_logo_footer',
            'app_favicon',
            'app_footer_contact_info',
            'app_footer_description',
        ])->count());
        $this->assertSame('Mapa de Fortalecimiento Institucional MISP 2024-2027 - PBA', app_setting('app_social_title'));
    }

    public function test_the_portal_uses_the_static_logos_and_favicon(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('img/default-logo-white.svg'))
            ->assertSee(asset('img/default-logo-color.svg'))
            ->assertSee(asset('img/logo-large-color.svg'))
            ->assertSee(asset('favicon.png'))
            ->assertSee('mesadeayuda@minfra.gba.gob.ar');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(asset('img/default-logo-white.svg'));
    }

    public function test_saving_a_setting_refreshes_its_cached_value(): void
    {
        $this->assertTrue(app_setting('app_homepage_show_latest_reports'));

        $this->actingAs($this->admin)
            ->from(route('admin.settings.homepage'))
            ->put(route('admin.settings.form'), [
                'name' => 'app_homepage_show_latest_reports',
                'type' => 'boolean',
                'cached' => 'true',
            ])
            ->assertRedirect(route('admin.settings.homepage'))
            ->assertSessionHas('success');

        $this->assertFalse(app_setting('app_homepage_show_latest_reports'));
    }

    public function test_the_home_subtitle_can_be_updated_without_leaving_a_stale_cached_value(): void
    {
        $subtitle = 'Un tablero abierto para conocer y controlar el avance de los objetivos y metas del Plan de Fortalecimiento Institucional y visibilizar el impacto de la gestión.';
        $setting = Setting::where('name', 'app_homepage_subtitle')->firstOrFail();
        $setting->value = 'Bajada anterior';
        $setting->save();
        $this->assertSame('Bajada anterior', app_setting('app_homepage_subtitle'));

        $this->actingAs($this->admin)
            ->from(route('admin.settings.homepage'))
            ->put(route('admin.settings.form'), [
                'name' => 'app_homepage_subtitle',
                'value' => $subtitle,
                'type' => 'string',
                'cached' => 'true',
            ])
            ->assertRedirect(route('admin.settings.homepage'))
            ->assertSessionHas('success');

        $this->assertSame($subtitle, $setting->fresh()->value);
        $this->assertSame($subtitle, app_setting('app_homepage_subtitle'));
        $this->get(route('home'))->assertOk()->assertSee($subtitle)->assertDontSee('Bajada anterior');
        $this->get(route('home'))->assertOk()->assertSee($subtitle);
    }

    public function test_uploading_a_file_setting_redirects_back_and_refreshes_the_cache(): void
    {
        Storage::fake('settings');
        $this->assertNull(app_setting('app_social_image'));

        $this->actingAs($this->admin)
            ->from(route('admin.settings.seo'))
            ->put(route('admin.settings.form.file'), [
                'name' => 'app_social_image',
                'type' => 'string',
                'cached' => 'true',
                'file' => UploadedFile::fake()->image('social.png'),
            ])
            ->assertRedirect(route('admin.settings.seo'))
            ->assertSessionHas('success');

        $this->assertStringStartsWith('/storage/settings/app_social_image-', app_setting('app_social_image'));
    }

    public function test_indexing_is_disabled_by_default_and_has_a_single_setting(): void
    {
        $this->assertFalse(app_setting('app_search_indexing_enabled'));
        $this->assertSame(1, Setting::where('name', 'app_search_indexing_enabled')->count());

        $this->actingAs($this->admin)->get(route('admin.settings.seo'))
            ->assertOk()
            ->assertSee('Permitir que los buscadores indexen las páginas públicas')
            ->assertSee('Esto no restringe el acceso público');
    }

    public function test_an_admin_can_enable_and_disable_indexing_without_clearing_the_cache(): void
    {
        foreach ([true, false] as $enabled) {
            $data = [
                'name' => 'app_search_indexing_enabled',
                'type' => 'boolean',
                'cached' => 'true',
            ];
            if ($enabled) {
                $data['value'] = 'true';
            }

            $this->actingAs($this->admin)->from(route('admin.settings.seo'))
                ->put(route('admin.settings.form'), $data)
                ->assertRedirect(route('admin.settings.seo'))
                ->assertSessionHas('success');

            $this->assertSame($enabled, app_setting('app_search_indexing_enabled'));
            $this->assertSame($enabled, Setting::where('name', 'app_search_indexing_enabled')->firstOrFail()->casted_value);
            $this->get(route('home'))->assertHeader('X-Robots-Tag', $enabled ? 'index, follow' : 'noindex, follow');
            $response = $this->get(route('admin.settings.seo'));
            $this->assertMatchesRegularExpression(
                '/id="app_search_indexing_enabled"[^>]*'.($enabled ? 'checked' : 'value="true"\s*').'>/',
                $response->getContent(),
            );
        }
    }

    public function test_indexing_rejects_invalid_values_and_types(): void
    {
        foreach ([['string', 'true'], ['boolean', 'invalid']] as [$type, $value]) {
            $this->actingAs($this->admin)->from(route('admin.settings.seo'))
                ->put(route('admin.settings.form'), [
                    'name' => 'app_search_indexing_enabled',
                    'type' => $type,
                    'value' => $value,
                ])
                ->assertSessionHasErrors($type === 'string' ? 'type' : 'value');

            $this->assertFalse(app_setting('app_search_indexing_enabled'));
        }
    }

    public function test_only_admins_can_access_or_save_the_indexing_setting(): void
    {
        $data = ['name' => 'app_search_indexing_enabled', 'type' => 'boolean', 'value' => 'true'];

        $this->get(route('admin.settings.seo'))->assertRedirect(route('login'));
        $this->put(route('admin.settings.form'), $data)->assertRedirect(route('login'));

        $user = UserFactory::new()->create(['surname' => 'User']);
        $this->actingAs($user)->get(route('admin.settings.seo'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.form'), $data)->assertForbidden();
        $this->assertFalse(app_setting('app_search_indexing_enabled'));
    }
}
