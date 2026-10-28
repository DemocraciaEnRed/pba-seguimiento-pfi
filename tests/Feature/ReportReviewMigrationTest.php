<?php

namespace Tests\Feature;

use App\Services\Reports\LegacyReportApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class ReportReviewMigrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function afterRefreshingDatabase(): void
    {
        // The legacy base migration drops parent tables before their children.
        $this->beforeApplicationDestroyed(fn () => \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints());
    }

    public function test_existing_reports_are_approved_once_by_admin_one_without_reapplying_effects(): void
    {
        $migration = require database_path('migrations/2026_10_16_181811_approve_existing_reports.php');
        $migration->down();
        $this->fixtures(true);
        DB::table('reports')->insert($this->legacyReport());
        DB::table('reports')->insert(array_replace($this->legacyReport(), ['deleted_at' => now()]));
        $before = DB::table('goals')->first();
        Notification::fake();
        $migration->up();
        $this->assertSame(2, DB::table('reports')->where('review_status', 'approved')->where('reviewed_by', 1)->count());
        $this->assertNotNull(DB::table('reports')->value('reviewed_at'));
        $this->assertSame(2, (int) DB::table('reports')->value('author_id'));
        $this->assertSame(1, DB::table('reports')->whereNotNull('deleted_at')->count());
        $this->assertEquals($before, DB::table('goals')->first());
        DB::table('reports')->insert(array_replace($this->legacyReport(), ['review_status' => 'pending']));
        (new LegacyReportApproval())->run();
        $this->assertSame(1, DB::table('reports')->where('review_status', 'pending')->count());
        Notification::assertNothingSent();
    }

    public function test_historical_data_requires_admin_one_and_is_not_partially_changed_on_error(): void
    {
        $migration = require database_path('migrations/2026_10_16_181811_approve_existing_reports.php');
        $migration->down();
        $this->fixtures(false);
        DB::table('reports')->insert($this->legacyReport());
        try {
            $migration->up();
            $this->fail('Debe validar el administrador 1.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('usuario 1', $exception->getMessage());
        }
        $this->assertSame(1, DB::table('reports')->whereNull('review_status')->whereNull('reviewed_by')->count());
        DB::table('users')->where('id', 1)->delete();
        $this->expectException(RuntimeException::class);
        $migration->up();
    }

    public function test_empty_installation_needs_no_admin_and_new_reports_default_to_pending(): void
    {
        $migration = require database_path('migrations/2026_10_16_181811_approve_existing_reports.php');
        $migration->down();
        $migration->up();
        $this->fixtures(false);
        $data = $this->legacyReport();
        unset($data['review_status']);
        DB::table('reports')->insert($data);
        $this->assertSame('pending', DB::table('reports')->value('review_status'));
    }

    private function fixtures(bool $admin): void
    {
        foreach ([1, 2] as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Usuario', 'surname' => 'Prueba', 'email' => $id.'@example.com', 'password' => 'hash']);
        }
        if ($admin) {
            $role = DB::table('roles')->insertGetId(['name' => 'admin', 'description' => 'Administrador']);
            DB::table('role_user')->insert(['role_id' => $role, 'user_id' => 1]);
        }
        $category = DB::table('categories')->insertGetId(['title' => 'Eje', 'icon' => 'fas fa-circle', 'color' => '#123456', 'order' => 1]);
        $strategic = DB::table('strategic_objectives')->insertGetId(['codigo' => 'OE1', 'title' => 'Estratégico', 'category_id' => $category]);
        $objective = DB::table('objectives')->insertGetId(['title' => 'Objetivo', 'content' => 'Contenido', 'author_id' => 2, 'strategic_objective_id' => $strategic]);
        DB::table('goals')->insert(['id' => 1, 'objective_id' => $objective, 'title' => 'Meta', 'status' => 'reached', 'indicator_progress' => 80]);
    }

    /** @return array<string, mixed> */
    private function legacyReport(): array
    {
        return ['author_id' => 2, 'goal_id' => 1, 'title' => 'Histórico', 'type' => 'progress', 'content' => 'Contenido', 'date' => '2026-01-01', 'progress' => 80, 'review_status' => null];
    }
}
