<?php

namespace Tests\Feature;

use App\Organization;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrganizationsListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

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

    public function test_organizations_are_listed_alphabetically_in_a_compact_list(): void
    {
        $this->createOrganization('Zeta Asociación');
        $this->createOrganization('Alfa Fundación');

        $this->actingAs($this->admin)
            ->get(route('admin.organizations'))
            ->assertOk()
            ->assertSee('2 resultado(s)')
            ->assertSee('list-group', false)
            ->assertSeeInOrder(['Alfa Fundación', '0 objetivos', 'Zeta Asociación'])
            ->assertSee(asset('img/default-background.png'), false);
    }

    public function test_empty_list_shows_a_message(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.organizations'))
            ->assertOk()
            ->assertSee('No hay organizaciones creadas');
    }

    private function createOrganization(string $name): Organization
    {
        $organization = new Organization();
        $organization->name = $name;
        $organization->description = "Descripción de {$name}";
        $organization->save();

        return $organization;
    }
}
