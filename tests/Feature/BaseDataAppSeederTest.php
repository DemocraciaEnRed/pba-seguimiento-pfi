<?php

namespace Tests\Feature;

use App\Category;
use App\Objective;
use App\Role;
use App\StrategicObjective;
use App\User;
use Database\Seeders\BaseDataAppSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BaseDataAppSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_base_data_seeder_populates_expected_hierarchy(): void
    {
        $this->createAdmin();

        $this->seed(BaseDataAppSeeder::class);

        $this->assertSame(7, Category::query()->count());
        $this->assertSame(38, StrategicObjective::query()->count());
        $this->assertSame(181, Objective::query()->count());

        $integrityAxis = Category::query()->where('order', 1)->first();
        $this->assertNotNull($integrityAxis);
        $this->assertSame('observatorio-integridad', $integrityAxis->icon);
        $this->assertSame('#082d81', $integrityAxis->color);
        $this->assertSame(
            array_keys(Category::AVAILABLE_ICONS),
            Category::query()->orderBy('order')->pluck('icon')->all()
        );

        $strategicCodes = StrategicObjective::query()->pluck('codigo')->all();
        $this->assertCount(38, array_unique($strategicCodes));

        foreach ($strategicCodes as $code) {
            $this->assertMatchesRegularExpression('/^OE[1-9][0-9]*$/', $code);
        }

        $objectives = Objective::query()->get();

        foreach ($objectives as $objective) {
            $this->assertNotNull($objective->author_id);
            $this->assertNotSame('', trim($objective->content));
            $this->assertNotNull($objective->strategic_objective_id);
        }

        $duplicatedTitle = 'Impulsar políticas que fomenten la inclusión con perspectiva de género y de sostenibilidad desde un enfoque de derechos humanos';
        $duplicatedObjectives = Objective::query()->where('title', $duplicatedTitle)->get();

        $this->assertCount(2, $duplicatedObjectives);
        $this->assertCount(2, $duplicatedObjectives->pluck('strategic_objective_id')->unique());

        if (Schema::hasColumn('objectives', 'codigo')) {
            $objectiveCodes = Objective::query()->pluck('codigo')->all();
            $objectiveCodes = array_filter($objectiveCodes);
            $this->assertCount(181, $objectiveCodes);
            $this->assertCount(181, array_unique($objectiveCodes));

            foreach ($objectiveCodes as $code) {
                $this->assertMatchesRegularExpression('/^OG[1-9][0-9]*$/', $code);
            }
        }
    }

    public function test_base_data_seeder_creates_a_fallback_admin_when_missing(): void
    {
        $this->assertSame(0, User::query()->count());

        $this->seed(BaseDataAppSeeder::class);

        $fallbackAdmin = User::query()->first();

        $this->assertNotNull($fallbackAdmin);
        $this->assertTrue($fallbackAdmin->hasRole('admin'));
        $this->assertTrue($fallbackAdmin->hasRole('user'));
        $this->assertSame(181, Objective::query()->count());
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
