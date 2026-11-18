<?php

namespace Tests\Feature;

use App\Services\Workspace\WorkDesk;
use App\User;
use Database\Seeders\WorkDeskDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkDeskDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fills_every_work_desk_section_for_each_profile(): void
    {
        $this->withoutVite();
        $this->seed(WorkDeskDemoSeeder::class);
        $workDesk = app(WorkDesk::class);
        $person = fn (string $key): User => User::query()->where('email', WorkDeskDemoSeeder::PEOPLE[$key]['email'])->firstOrFail();

        $this->assertSame(['goals' => 0, 'action' => 0, 'waiting' => 0, 'administration' => 4, 'alerts' => 4], $workDesk->summary($person('admin')));
        $this->assertSame(['goals' => 5, 'action' => 12, 'waiting' => 6, 'administration' => null, 'alerts' => null], $workDesk->summary($person('rosa')));
        $this->assertSame(['goals' => 3, 'action' => 2, 'waiting' => 4, 'administration' => null, 'alerts' => null], $workDesk->summary($person('raul')));
        $this->assertSame(['goals' => 3, 'action' => 1, 'waiting' => 11, 'administration' => null, 'alerts' => null], $workDesk->summary($person('paula')));
        $this->assertSame(['goals' => 4, 'action' => 1, 'waiting' => 4, 'administration' => null, 'alerts' => null], $workDesk->summary($person('diego')));
        $this->assertSame(['goals' => 0, 'action' => 0, 'waiting' => 0, 'administration' => null, 'alerts' => null], $workDesk->summary($person('sofia')));
        $this->assertSame(3, $workDesk->uncoveredGoals()->count());
        $this->assertSame(['Relevamiento de locales disponibles'], $workDesk->orphanedReports()->pluck('title')->all());

        $this->actingAs($person('rosa'))->get(route('panel.desk'))->assertOk()->assertSee('Visita técnica a la Escuela N° 1');
        $this->actingAs($person('sofia'))->get(route('panel.desk'))->assertOk();
        $this->actingAs($person('carlos'))->get(route('panel.desk'))->assertForbidden();
    }

    public function test_demo_refuses_to_load_twice(): void
    {
        $this->seed(WorkDeskDemoSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La demo ya está cargada');

        $this->seed(WorkDeskDemoSeeder::class);
    }
}
