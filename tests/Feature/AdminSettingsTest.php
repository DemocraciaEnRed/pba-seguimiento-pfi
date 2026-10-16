<?php

namespace Tests\Feature;

use App\Role;
use App\Setting;
use App\User;
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
}
