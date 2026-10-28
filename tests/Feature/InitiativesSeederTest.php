<?php

namespace Tests\Feature;

use App\Category;
use App\Initiative;
use App\Role;
use App\User;
use Database\Seeders\BaseDataAppSeeder;
use Database\Seeders\InitiativesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitiativesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_initiatives_linked_to_axes_and_axis_descriptions(): void
    {
        $this->createAdmin();
        $this->seed(BaseDataAppSeeder::class);

        $this->seed(InitiativesSeeder::class);

        $this->assertSame(61, Initiative::query()->count());
        $this->assertSame(8, Initiative::query()->featuredOnHome()->count());
        $this->assertSame(range(1, 8), Initiative::query()->featuredOnHome()->orderBy('order')->pluck('order')->all());
        $this->assertSame(0, Initiative::query()->doesntHave('categories')->count());
        $this->assertSame(0, Category::query()->whereNull('description')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->createAdmin();
        $this->seed(BaseDataAppSeeder::class);

        $this->seed(InitiativesSeeder::class);
        $pivotRows = Initiative::query()->withCount('categories')->get()->sum('categories_count');
        $this->seed(InitiativesSeeder::class);

        $this->assertSame(61, Initiative::query()->count());
        $this->assertSame($pivotRows, Initiative::query()->withCount('categories')->get()->sum('categories_count'));
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'surname' => 'Test',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();

        $admin->roles()->attach($role);

        return $admin;
    }
}
