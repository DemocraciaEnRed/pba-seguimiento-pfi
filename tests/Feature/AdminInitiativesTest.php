<?php

namespace Tests\Feature;

use App\Category;
use App\Initiative;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminInitiativesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $sustainable;

    private Category $innovation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(Initiative::DISK);

        $this->admin = $this->createUser('admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $this->sustainable = $this->createCategory('Eje sostenible', 'observatorio-sostenible', '#12a24f', 1);
        $this->innovation = $this->createCategory('Innovación', 'observatorio-innovacion', '#58c0dc', 2);
    }

    public function test_only_admins_can_access_initiatives(): void
    {
        $this->get(route('admin.initiatives'))->assertRedirect(route('login'));

        $user = $this->createUser('usuario@example.com');
        $this->actingAs($user)->get(route('admin.initiatives'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.initiatives.create.form'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('initiatives', 0);
    }

    public function test_admin_lists_and_filters_initiatives(): void
    {
        $featured = Initiative::factory()->featured()->create(['title' => 'Ministerio Abierto']);
        $featured->categories()->attach($this->sustainable);
        $other = Initiative::factory()->create(['title' => 'InfraAprende']);
        $other->categories()->attach($this->innovation);

        $this->actingAs($this->admin)->get(route('admin.initiatives'))
            ->assertOk()
            ->assertSee('Ministerio Abierto')
            ->assertSee('InfraAprende');

        $this->actingAs($this->admin)->get(route('admin.initiatives', ['category' => $this->innovation->id]))
            ->assertOk()
            ->assertDontSee('Ministerio Abierto')
            ->assertSee('InfraAprende');

        $this->actingAs($this->admin)->get(route('admin.initiatives', ['featured' => 1]))
            ->assertOk()
            ->assertSee('Ministerio Abierto')
            ->assertDontSee('InfraAprende');
    }

    public function test_admin_creates_initiative_with_images(): void
    {
        $this->actingAs($this->admin)->get(route('admin.initiatives.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.initiatives.create.form'), $this->validPayload([
                'featured_on_home' => '1',
                'background' => UploadedFile::fake()->image('fondo.jpg', 2400, 1200),
                'logo' => UploadedFile::fake()->image('logo.png', 800, 800),
            ]))
            ->assertRedirect(route('admin.initiatives'))
            ->assertSessionHas('success');

        $initiative = Initiative::sole();
        $this->assertSame('Ministerio Abierto', $initiative->title);
        $this->assertTrue($initiative->featured_on_home);
        $this->assertSame(3, $initiative->order);
        $this->assertEqualsCanonicalizing([$this->sustainable->id, $this->innovation->id], $initiative->categories->pluck('id')->all());
        Storage::disk(Initiative::DISK)->assertExists([$initiative->background_path, $initiative->logo_path]);
        $this->assertStringEndsWith('.png', $initiative->logo_path);
        $this->assertSame(1600, getimagesizefromstring(Storage::disk(Initiative::DISK)->get($initiative->background_path))[0]);
        $this->assertSame(400, getimagesizefromstring(Storage::disk(Initiative::DISK)->get($initiative->logo_path))[0]);
    }

    public function test_creation_validates_required_fields_and_formats(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.initiatives.create.form'), [
                'title' => '',
                'subtitle' => '',
                'url' => 'no-es-una-url',
                'categories' => [],
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors(['title', 'subtitle', 'url', 'categories', 'logo']);

        $this->actingAs($this->admin)
            ->post(route('admin.initiatives.create.form'), $this->validPayload(['categories' => [999]]))
            ->assertSessionHasErrors('categories.0');

        $this->assertDatabaseCount('initiatives', 0);
    }

    public function test_admin_updates_initiative_replacing_and_removing_images(): void
    {
        $initiative = Initiative::factory()->featured()->create();
        $initiative->categories()->attach($this->sustainable);
        Storage::disk(Initiative::DISK)->put('old-bg.jpg', 'x');
        Storage::disk(Initiative::DISK)->put('old-logo.png', 'x');
        $initiative->forceFill(['background_path' => 'old-bg.jpg', 'logo_path' => 'old-logo.png'])->save();

        $this->actingAs($this->admin)->get(route('admin.initiatives.edit', $initiative))->assertOk()->assertSee($initiative->title);

        $this->actingAs($this->admin)
            ->put(route('admin.initiatives.edit.form', $initiative), $this->validPayload([
                'title' => 'Nuevo título',
                'url' => 'https://ejemplo.gba.gob.ar',
                'categories' => [$this->innovation->id],
                'background' => UploadedFile::fake()->image('nuevo.jpg', 800, 400),
                'remove_logo' => '1',
            ]))
            ->assertRedirect(route('admin.initiatives'));

        $initiative->refresh();
        $this->assertSame('Nuevo título', $initiative->title);
        $this->assertSame('https://ejemplo.gba.gob.ar', $initiative->url);
        $this->assertFalse($initiative->featured_on_home);
        $this->assertSame([$this->innovation->id], $initiative->categories->pluck('id')->all());
        $this->assertNull($initiative->logo_path);
        $this->assertNotSame('old-bg.jpg', $initiative->background_path);
        Storage::disk(Initiative::DISK)->assertMissing(['old-bg.jpg', 'old-logo.png']);
        Storage::disk(Initiative::DISK)->assertExists($initiative->background_path);
    }

    public function test_admin_deletes_initiative_with_password_confirmation(): void
    {
        $initiative = Initiative::factory()->create(['background_path' => 'bg.jpg']);
        $initiative->categories()->attach($this->sustainable);
        Storage::disk(Initiative::DISK)->put('bg.jpg', 'x');

        $this->actingAs($this->admin)->get(route('admin.initiatives.delete', $initiative))->assertOk();

        $this->actingAs($this->admin)
            ->delete(route('admin.initiatives.delete.form', $initiative), ['password' => 'incorrecta'])
            ->assertSessionHasErrors('password');
        $this->assertModelExists($initiative);

        $this->actingAs($this->admin)
            ->delete(route('admin.initiatives.delete.form', $initiative), ['password' => 'password'])
            ->assertRedirect(route('admin.initiatives'));

        $this->assertModelMissing($initiative);
        $this->assertDatabaseCount('category_initiative', 0);
        Storage::disk(Initiative::DISK)->assertMissing('bg.jpg');
    }

    public function test_admin_saves_category_description(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.categories.edit.form', ['categoryId' => $this->sustainable->id]), [
                'title' => 'Eje sostenible',
                'description' => 'Promueve una gestión sostenible.',
                'icon' => 'observatorio-sostenible',
                'color' => '#12a24f',
                'order' => 1,
            ])
            ->assertRedirect(route('admin.categories'));

        $this->assertSame('Promueve una gestión sostenible.', $this->sustainable->fresh()->description);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ministerio Abierto',
            'subtitle' => 'Portal de transparencia activa del Ministerio.',
            'url' => '',
            'categories' => [$this->sustainable->id, $this->innovation->id],
            'order' => 3,
        ], $overrides);
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'name' => 'Ana',
            'surname' => 'Pérez',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
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
}
