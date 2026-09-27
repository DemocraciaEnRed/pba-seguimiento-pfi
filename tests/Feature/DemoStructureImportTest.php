<?php

namespace Tests\Feature;

use App\Goal;
use App\Objective;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoStructureImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_spreadsheet_imports_cleanly_on_a_fresh_install(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->sole();

        $this->actingAs($admin)
            ->post(route('admin.import.upload'), [
                'file' => new UploadedFile(database_path('seeders/demo-importacion-estructura.csv'), 'demo-importacion-estructura.csv', 'text/csv', null, true),
            ])
            ->assertRedirect(route('admin.import.preview'));

        $plan = $this->actingAs($admin)->get(route('admin.import.preview'))->assertOk()->viewData('plan');

        $this->assertSame([], $plan->errors);
        $this->assertSame([
            'Objetivos estratégicos' => ['create' => 1, 'update' => 0, 'unchanged' => 6],
            'Objetivos' => ['create' => 3, 'update' => 0, 'unchanged' => 8],
            'Metas' => ['create' => 15, 'update' => 0, 'unchanged' => 0],
        ], $plan->summary());

        $this->actingAs($admin)->post(route('admin.import.confirm'))->assertRedirect(route('admin.import'));

        $this->assertSame(39, StrategicObjective::count());
        $this->assertSame(184, Objective::count());
        $this->assertSame(15, Goal::count());
        $this->assertSame(3, Objective::where('hidden', true)->count());
        $this->assertSame(
            [1 => 45.0, 2 => 45.0, 3 => 40.0, 4 => 40.0, 5 => 35.0, 6 => 30.0],
            Goal::where('title', 'Plazo de pago de certificados de obra')->sole()->periods->pluck('target_value', 'number')->all(),
        );
    }
}
