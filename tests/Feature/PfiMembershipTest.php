<?php

namespace Tests\Feature;

use App\ActionLog;
use App\Category;
use App\Objective;
use App\PfiCategory;
use App\PfiMembership;
use App\Role;
use App\Services\Pfi\PfiMemberships;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PfiMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $person;

    private User $other;

    private PfiCategory $territory;

    private PfiCategory $academy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();

        $this->admin = $this->createUser('Ana', 'Admin', 'admin@example.com');
        $this->admin->roles()->attach($role);
        $this->person = $this->createUser('Juan', 'Pérez', 'juan@example.com');
        $this->other = $this->createUser('Lucía', 'Gómez', 'lucia@example.com');

        $this->territory = PfiCategory::create(['name' => 'Territorio']);
        $this->academy = PfiCategory::create(['name' => 'Academia']);
    }

    public function test_only_admins_can_manage_pfi_categories_and_memberships(): void
    {
        $this->get(route('admin.pfi-categories'))->assertRedirect(route('login'));

        $this->actingAs($this->person)->get(route('admin.pfi-categories'))->assertForbidden();
        $this->actingAs($this->person)->get(route('admin.pfi-categories.create'))->assertForbidden();
        $this->actingAs($this->person)
            ->post(route('admin.pfi-categories.create.form'), ['name' => 'Nueva', 'is_active' => '1'])
            ->assertForbidden();
        $this->actingAs($this->person)
            ->put(route('admin.pfi-categories.edit.form', $this->territory), ['name' => 'Otra'])
            ->assertForbidden();
        $this->actingAs($this->person)
            ->post(route('admin.users.pfi.store.form', ['userId' => $this->person->id]), ['categories' => [$this->territory->id]])
            ->assertForbidden();

        $this->assertDatabaseMissing('pfi_categories', ['name' => 'Nueva']);
        $this->assertTrue($this->territory->fresh()->is_active);
        $this->assertSame(0, PfiMembership::count());
    }

    public function test_admin_can_create_and_edit_categories_with_unique_names(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pfi-categories'))
            ->assertOk()
            ->assertSee('Territorio')
            ->assertSee('Integrante MISTO')
            ->assertSee(route('admin.pfi-categories.create'));
        $this->actingAs($this->admin)->get(route('admin.pfi-categories.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.pfi-categories.create.form'), ['name' => 'Universidades', 'is_active' => '1'])
            ->assertRedirect(route('admin.pfi-categories'));
        $category = PfiCategory::where('name', 'Universidades')->firstOrFail();
        $this->assertTrue($category->is_active);
        $this->assertLogged('[Admin, Ana] ha creado la categoría PFI [Universidades]');
        $this->actingAs($this->admin)->get(route('admin.pfi-categories.edit', $category))
            ->assertOk()
            ->assertSee('value="Universidades"', false);

        $this->actingAs($this->admin)
            ->from(route('admin.pfi-categories.create'))
            ->post(route('admin.pfi-categories.create.form'), ['name' => 'Territorio', 'is_active' => '1'])
            ->assertRedirect(route('admin.pfi-categories.create'))
            ->assertSessionHasErrors(['name' => 'Ya existe una categoría PFI con ese nombre.']);
        $this->assertSame(1, PfiCategory::where('name', 'Territorio')->count());

        $this->actingAs($this->admin)
            ->put(route('admin.pfi-categories.edit.form', $category), ['name' => 'Universidades públicas', 'is_active' => '1'])
            ->assertRedirect(route('admin.pfi-categories'))
            ->assertSessionHasNoErrors();
        $this->assertSame('Universidades públicas', $category->fresh()->name);
        $this->assertLogged('[Admin, Ana] ha editado la categoría PFI [Universidades públicas]');

        $this->actingAs($this->admin)
            ->put(route('admin.pfi-categories.edit.form', $category), ['name' => 'Territorio', 'is_active' => '1'])
            ->assertSessionHasErrors('name');
    }

    public function test_deactivating_a_category_keeps_associations_and_excludes_it_from_new_selections(): void
    {
        $this->admit($this->person, [$this->territory, $this->academy]);

        $this->actingAs($this->admin)
            ->put(route('admin.pfi-categories.edit.form', $this->territory), ['name' => 'Territorio'])
            ->assertRedirect(route('admin.pfi-categories'));

        $this->assertFalse($this->territory->fresh()->is_active);
        $this->assertLogged('[Admin, Ana] ha desactivado la categoría PFI [Territorio]');
        $this->assertSame(['Academia', 'Territorio'], $this->person->pfiCategories()->pluck('name')->all());
        $this->actingAs($this->admin)->get(route('admin.pfi-categories'))->assertSeeInOrder(['Territorio', 'Inactiva']);

        $this->actingAs($this->admin)->get(route('admin.users.show', ['userId' => $this->person->id]))
            ->assertSee('id="pfi-category-'.$this->territory->id.'"', false)
            ->assertSee('Inactiva');
        $this->actingAs($this->admin)->get(route('admin.users.show', ['userId' => $this->other->id]))
            ->assertDontSee('id="pfi-category-'.$this->territory->id.'"', false)
            ->assertSee('id="pfi-category-'.$this->academy->id.'"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.users.pfi.store.form', ['userId' => $this->other->id]), ['categories' => [$this->territory->id]])
            ->assertSessionHasErrors('categories.0');
        $this->assertFalse($this->other->fresh()->isPfiMember());

        $this->actingAs($this->admin)
            ->put(route('admin.pfi-categories.edit.form', $this->territory), ['name' => 'Territorio', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->territory->fresh()->is_active);
        $this->assertLogged('[Admin, Ana] ha reactivado la categoría PFI [Territorio]');
    }

    public function test_admin_can_admit_a_person_with_at_least_one_active_category(): void
    {
        $this->assertSame(0, PfiMembership::count());
        $url = route('admin.users.pfi.store.form', ['userId' => $this->person->id]);
        $profile = route('admin.users.show', ['userId' => $this->person->id]);

        $this->actingAs($this->admin)->get($profile)
            ->assertOk()
            ->assertSee('Equipos PFI')
            ->assertSee('Dar de alta como integrante PFI');

        $this->actingAs($this->admin)->from($profile)->post($url, ['categories' => []])
            ->assertRedirect($profile)
            ->assertSessionHasErrors(['categories' => 'Seleccioná al menos una categoría PFI.']);
        $this->actingAs($this->admin)->from($profile)->post($url, ['categories' => [999999]])
            ->assertSessionHasErrors('categories.0');
        $this->assertFalse($this->person->fresh()->isPfiMember());

        $this->actingAs($this->admin)
            ->post($url, ['categories' => [$this->territory->id, $this->academy->id]])
            ->assertRedirect($profile)
            ->assertSessionHas('success');

        $member = $this->person->fresh(['pfiMembership.addedBy', 'pfiCategories']);
        $this->assertTrue($member->isPfiMember());
        $this->assertTrue($member->pfiMembership->addedBy->is($this->admin));
        $this->assertSame(['Academia', 'Territorio'], $member->pfiCategories->pluck('name')->all());
        $this->assertLogged('[Admin, Ana] ha dado de alta a [Pérez, Juan] como integrante PFI');

        $this->actingAs($this->admin)->get($profile)
            ->assertSee('Integrante PFI desde')
            ->assertSee('alta realizada por Admin, Ana')
            ->assertSee('Guardar categorías')
            ->assertSee(route('admin.users.pfi.remove', ['userId' => $this->person->id]))
            ->assertDontSee('Dar de alta como integrante PFI');

        $this->actingAs($this->admin)->post($url, ['categories' => [$this->territory->id]])
            ->assertRedirect($profile)
            ->assertSessionHas('warning');
        $this->assertSame(1, PfiMembership::where('user_id', $this->person->id)->count());
        $this->assertSame(2, $member->pfiCategories()->count());
    }

    public function test_admin_can_reclassify_a_member_keeping_categories_already_assigned(): void
    {
        $this->admit($this->person, [$this->territory]);
        $this->territory->update(['is_active' => false]);
        $url = route('admin.users.pfi.update.form', ['userId' => $this->person->id]);

        $this->actingAs($this->admin)
            ->put($url, ['categories' => [$this->territory->id, $this->academy->id]])
            ->assertRedirect(route('admin.users.show', ['userId' => $this->person->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Academia', 'Territorio'], $this->person->pfiCategories()->pluck('name')->all());
        $log = ActionLog::latest('id')->first();
        $this->assertSame('[Admin, Ana] ha modificado las categorías PFI de [Pérez, Juan]', $log->message);
        $this->assertSame(['Academia'], json_decode($log->context, true)['pfi_categories_added']);

        $this->actingAs($this->admin)->put($url, ['categories' => []])->assertSessionHasErrors('categories');
        $this->assertSame(2, $this->person->pfiCategories()->count());

        $this->actingAs($this->admin)->put($url, ['categories' => [$this->academy->id]])->assertSessionHasNoErrors();
        $this->assertSame(['Territorio'], json_decode(ActionLog::latest('id')->first()->context, true)['pfi_categories_removed']);
        $this->actingAs($this->admin)
            ->put($url, ['categories' => [$this->territory->id, $this->academy->id]])
            ->assertSessionHasErrors('categories.0');
        $this->assertSame(['Academia'], $this->person->pfiCategories()->pluck('name')->all());

        $logs = ActionLog::count();
        $this->actingAs($this->admin)->put($url, ['categories' => [$this->academy->id]])->assertSessionHasNoErrors();
        $this->assertSame($logs, ActionLog::count());

        $this->actingAs($this->admin)
            ->put(route('admin.users.pfi.update.form', ['userId' => $this->other->id]), ['categories' => [$this->academy->id]])
            ->assertSessionHas('error');
        $this->assertSame(0, $this->other->pfiCategories()->count());
    }

    public function test_pfi_membership_grants_no_access(): void
    {
        $this->admit($this->person, [$this->territory]);
        $objective = $this->createObjective();
        $member = $this->person->fresh();

        $this->assertFalse(Gate::forUser($member)->allows('isAdmin'));
        $this->assertFalse(Gate::forUser($member)->allows('searchPfiCandidates'));
        $this->assertFalse($member->can('accessManagement', $objective));
        $this->assertFalse($member->can('update', $objective));

        $this->actingAs($member)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($member)->get(route('objectives.manage.edit', $objective->id))->assertForbidden();
        $this->actingAs($member)
            ->getJson(route('apiService.pfi.candidates', ['name' => 'Pérez']))
            ->assertForbidden();
    }

    public function test_users_list_filters_by_pfi_membership_and_category(): void
    {
        $this->admit($this->person, [$this->territory]);
        $this->admit($this->other, [$this->academy]);

        $this->actingAs($this->admin)->get(route('admin.users', ['pfi' => 'si']))
            ->assertOk()
            ->assertSee('2 resultado(s)')
            ->assertSee('Pérez, Juan')
            ->assertSee('Gómez, Lucía')
            ->assertSee('title="Integrante PFI: Territorio"', false)
            ->assertDontSee('Admin, Ana');
        $this->actingAs($this->admin)->get(route('admin.users', ['pfi' => 'no']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Admin, Ana');
        $this->actingAs($this->admin)->get(route('admin.users', ['categoria_pfi' => $this->territory->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Pérez, Juan')
            ->assertDontSee('Gómez, Lucía');
        $this->actingAs($this->admin)->get(route('admin.users', ['categoria_pfi' => 'otra']))
            ->assertSee('3 resultado(s)');

        $this->actingAs($this->admin)->get(route('admin.users.objectives', ['userId' => $this->person->id]))
            ->assertSee('title="Integrante PFI: Territorio"', false);
    }

    public function test_users_csv_includes_pfi_columns(): void
    {
        $this->admit($this->person, [$this->territory, $this->academy]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.users.download', ['pfi' => 'si']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Integrante PFI', $csv);
        $this->assertStringContainsString('Categorías PFI', $csv);
        $this->assertMatchesRegularExpression('/juan@example\.com.*Sí.*Academia, Territorio/', $csv);
        $this->assertStringNotContainsString('lucia@example.com', $csv);
    }

    public function test_candidate_search_only_returns_pfi_members_to_admins(): void
    {
        $url = fn (array $query): string => route('apiService.pfi.candidates', $query);
        $this->get($url(['name' => 'Pérez']))->assertRedirect(route('login'));

        $this->admit($this->person, [$this->territory]);
        $this->createUser('Juana', 'Pérez', 'juana@example.com');

        foreach (['Juan', 'Pérez', 'juan@example', 'Juan Pérez', 'Pérez Juan'] as $search) {
            $this->actingAs($this->admin)->getJson($url(['name' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $this->person->id)
                ->assertJsonMissingPath('data.0.email');
        }

        $this->actingAs($this->admin)->getJson($url(['name' => 'Gómez']))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->admin)->getJson($url(['name' => 'Pérez', 'with' => 'user_email']))
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.email', 'juan@example.com');
        $this->actingAs($this->admin)->getJson($url(['name' => 'Pé']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->other)->getJson($url(['name' => 'Pérez']))->assertForbidden();
    }

    public function test_legacy_objective_coordinators_cannot_search_candidates_or_manage_structure(): void
    {
        $objective = $this->createObjective();
        $objective->members()->attach($this->person, ['role' => 'manager']);
        $this->admit($this->other, [$this->academy]);
        $url = route('apiService.pfi.candidates', ['name' => 'Gómez', 'with' => 'user_email']);

        $this->assertFalse($this->person->can('update', $objective));
        $this->actingAs($this->person)->getJson($url)->assertForbidden();

        $this->admit($this->person, [$this->territory]);
        $this->actingAs($this->person)->getJson($url)->assertForbidden();
    }

    public function test_legacy_team_assignment_is_retired(): void
    {
        $objective = $this->createObjective();
        $objective->members()->attach($this->person, ['role' => 'manager']);
        Notification::fake();

        $legacyUrl = route('objectives.manage.team', $objective->id).'/agregar';
        $this->actingAs($this->person)->get($legacyUrl)->assertNotFound();

        $this->actingAs($this->person)
            ->post($legacyUrl, [
                'userId' => $this->other->id,
                'role' => 'reporter',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('objective_user', [
            'objective_id' => $objective->id,
            'user_id' => $this->other->id,
            'role' => 'reporter',
        ]);
        $this->assertFalse($this->other->fresh()->isPfiMember());
    }

    public function test_initial_categories_migration_is_idempotent_and_keeps_categories_in_use(): void
    {
        $seeded = ['Integrante MISTO', 'Ente', 'Empresa', 'Organismos MISP'];
        $this->assertSame(4, PfiCategory::active()->whereIn('name', $seeded)->count());

        $migration = require database_path('migrations/2026_11_18_140351_seed_initial_pfi_categories.php');
        $migration->up();
        $this->assertSame(4, PfiCategory::whereIn('name', $seeded)->count());

        $this->admit($this->person, [PfiCategory::where('name', 'Ente')->firstOrFail()]);
        $migration->down();
        $this->assertSame(['Ente'], PfiCategory::whereIn('name', $seeded)->pluck('name')->all());
        $this->assertSame(['Ente'], $this->person->pfiCategories()->pluck('name')->all());
        $this->assertTrue(PfiCategory::whereKey($this->territory->id)->exists());

        $migration->up();
        $this->assertSame(4, PfiCategory::whereIn('name', $seeded)->count());
    }

    /**
     * @param  list<PfiCategory>  $categories
     */
    private function admit(User $user, array $categories): void
    {
        app(PfiMemberships::class)->admit($user, array_map(fn (PfiCategory $category): int => $category->id, $categories), $this->admin);
    }

    private function assertLogged(string $message): void
    {
        $this->assertContains($message, ActionLog::pluck('message')->all());
    }

    private function createObjective(): Objective
    {
        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->codigo = 'OE1';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = 'Objetivo';
        $objective->content = 'Contenido';
        $objective->hidden = false;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createUser(string $name, string $surname, string $email): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}
