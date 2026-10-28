<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Milestone;
use App\Objective;
use App\Report;
use App\Role;
use App\StrategicObjective;
use App\User;
use App\Notifications\NewReport;
use App\Notifications\ReportReviewNotification;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Reports\ReportReview;
use App\Services\Reports\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $reporter;
    private User $other;
    private User $manager;
    private Goal $goal;
    private Objective $objective;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = \Database\Factories\UserFactory::new()->create(['surname' => 'Admin']);
        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $this->admin->roles()->attach($role);
        $this->reporter = \Database\Factories\UserFactory::new()->create(['surname' => 'Reportero']);
        $this->other = \Database\Factories\UserFactory::new()->create(['surname' => 'Otro']);
        $this->manager = \Database\Factories\UserFactory::new()->create(['surname' => 'Responsable']);
        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();
        $strategic = new StrategicObjective();
        $strategic->title = 'Estratégico';
        $strategic->codigo = 'OE1';
        $strategic->category()->associate($category);
        $strategic->save();
        $this->objective = new Objective();
        $this->objective->title = 'Objetivo';
        $this->objective->content = 'Contenido';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategic);
        $this->objective->save();
        $this->objective->members()->attach($this->reporter, ['role' => 'reporter']);
        $this->objective->members()->attach($this->other, ['role' => 'reporter']);
        $this->objective->members()->attach($this->manager, ['role' => 'manager']);
        $this->goal = new Goal();
        $this->goal->title = 'Meta';
        $this->goal->status = 'ongoing';
        $this->goal->measurement_mode = 'simple';
        $this->goal->indicator_goal = 100;
        $this->goal->indicator_progress = 40;
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();
        Notification::fake();
    }

    public function test_pending_report_has_no_effect_and_approval_applies_exactly_once(): void
    {
        $report = $this->submit(['status' => 'reached', 'progress' => 20.5]);
        $this->assertSame(ReviewStatus::Pending, $report->review_status);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->assertSame('ongoing', $this->goal->fresh()->status);
        Notification::assertSentTo($this->admin, ReportReviewNotification::class);
        Notification::assertNotSentTo($this->reporter, NewReport::class);
        $this->approve($report)->assertSessionHasNoErrors();
        $this->assertSame(60.5, $this->goal->fresh()->indicator_progress);
        $this->assertSame('reached', $this->goal->fresh()->status);
        $this->assertSame(40.0, $report->fresh()->previous_progress);
        $this->assertSame($this->admin->id, $report->fresh()->reviewed_by);
        $this->approve($report->fresh())->assertSessionHasErrors('review');
        $this->assertSame(60.5, $this->goal->fresh()->indicator_progress);
    }

    public function test_admin_publishes_directly_and_non_admin_cannot_approve(): void
    {
        $report = $this->submit([], $this->admin);
        $this->assertTrue($report->isApproved());
        $this->assertSame(45.0, $this->goal->fresh()->indicator_progress);
        $pending = $this->submit();
        foreach ([$this->reporter, $this->manager] as $actor) {
            $this->actingAs($actor)->post(route('reports.review.approve', $pending->id), $this->reviewData($pending))->assertForbidden();
        }
    }

    public function test_author_and_manager_can_edit_but_other_reporters_cannot(): void
    {
        $report = $this->submit();
        $this->actingAs($this->other)->get($this->panelUrl($report))->assertNotFound();
        foreach ([$this->reporter, $this->manager] as $actor) {
            $this->actingAs($actor)->get($this->panelUrl($report))->assertOk()->assertSee('Cambios propuestos');
            $this->actingAs($actor)->put($this->panelUrl($report, '.edit.form'), $this->payload(['title' => 'Corregido', 'review_version' => $report->fresh()->review_version]))->assertSessionHasNoErrors();
        }
        $this->assertSame($this->reporter->id, $report->fresh()->author_id);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->approve($report->fresh())->assertSessionHasNoErrors();
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.edit.form'), $this->payload())->assertForbidden();
        $this->actingAs($this->manager)->get($this->panelUrl($report, '.edit'))->assertForbidden();
    }

    public function test_return_requires_reason_and_only_explicit_resubmit_returns_it_to_pending(): void
    {
        $report = $this->submit();
        $this->actingAs($this->admin)->post(route('reports.review.return', $report->id), ['review_version' => 1])->assertSessionHasErrors('return_reason');
        $this->actingAs($this->admin)->post(route('reports.review.return', $report->id), ['review_version' => 1, 'return_reason' => 'Adjuntar evidencia'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Returned, $report->fresh()->review_status);
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.edit.form'), $this->payload(['review_version' => $report->fresh()->review_version]))->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Returned, $report->fresh()->review_status);
        $this->approve($report->fresh())->assertSessionHasErrors('review');
        $this->actingAs($this->manager)->post(route('reports.review.resubmit', $report->id), ['review_version' => $report->fresh()->review_version])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Pending, $report->fresh()->review_status);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
    }

    public function test_stale_content_or_changed_goal_requires_reload(): void
    {
        $report = $this->submit();
        $data = $this->reviewData($report);
        $this->actingAs($this->manager)->put($this->panelUrl($report, '.edit.form'), $this->payload(['review_version' => 1]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('reports.review.approve', $report->id), $data)->assertSessionHasErrors('review');
        $data = $this->reviewData($report->fresh());
        $this->goal->indicator_progress = 50;
        $this->goal->save();
        $this->actingAs($this->admin)->post(route('reports.review.approve', $report->id), $data)->assertSessionHasErrors('review');
        $this->approve($report->fresh())->assertSessionHasNoErrors();
        $this->assertSame(55.0, $this->goal->fresh()->indicator_progress);
    }

    public function test_pending_reports_are_excluded_from_public_surfaces_and_participation_even_for_admin(): void
    {
        $report = $this->submit(['title' => 'Propuesta privada']);
        foreach ([null, $this->reporter, $this->admin] as $user) {
            if ($user) {
                $this->actingAs($user);
            } else {
                $this->app['auth']->forgetGuards();
            }
            $this->get(route('reports.index', $report->id))->assertNotFound();
            $this->getJson(route('apiService.reports.comments', $report->id))->assertNotFound();
            $this->getJson(route('apiService.reports'))->assertDontSee('Propuesta privada');
            $this->getJson(route('apiService.goals.reports', $this->goal->id))->assertDontSee('Propuesta privada');
            $this->getJson(route('apiService.objectives.reports', $this->objective->id))->assertDontSee('Propuesta privada');
        }
        $this->actingAs($this->reporter)->postJson(route('apiService.reports.comments.create', $report->id), ['content' => 'Comentario'])->assertNotFound();
        $stats = (new \App\Services\Indicators\ObjectiveStats())->compute($this->objective);
        $this->assertSame(0, $stats['reports_total']);
        $this->assertNull($stats['last_report_date']);
    }

    public function test_period_is_reserved_without_counting_as_measured_and_can_be_freed_by_author(): void
    {
        $this->travelTo('2026-08-15');
        $configurator = app(GoalIndicatorConfigurator::class);
        $configurator->apply($this->goal, [
            'measurement_mode' => 'periodic', 'indicator' => 'Resultado', 'indicator_unit' => 'unidades',
            'indicator_direction' => 'higher_is_better', 'indicator_nature' => 'extensive',
            'period_type' => 'quarterly', 'period_start' => '2026-01', 'period_count' => 2,
            'period_targets' => [1 => 10, 2 => 10],
        ]);
        $period = $this->goal->periods()->first();
        $report = $this->submit(['goal_period_id' => $period->id, 'measured_value' => 0]);
        $this->assertNull($period->fresh()->progressReport);
        $this->assertSame($report->id, $period->fresh()->reservedReport->id);
        $this->actingAs($this->other)->post($this->createUrl(), $this->payload(['goal_period_id' => $period->id, 'measured_value' => 1]))->assertSessionHasErrors('goal_period_id');
        $this->actingAs($this->manager)->delete($this->panelUrl($report, '.delete.form'), ['password' => 'password'])->assertForbidden();
        $this->actingAs($this->reporter)->delete($this->panelUrl($report, '.delete.form'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNull($period->fresh()->reservedReport);
        $replacement = $this->submit(['goal_period_id' => $period->id, 'measured_value' => 0]);
        $this->approve($replacement)->assertSessionHasNoErrors();
        $this->assertSame(0.0, $period->fresh()->progressReport->measured_value);
    }

    public function test_milestone_date_is_proposed_until_approval_and_completed_hito_conflicts(): void
    {
        $milestone = new Milestone();
        $milestone->title = 'Hito';
        $milestone->order = 1;
        $milestone->goal()->associate($this->goal);
        $milestone->save();
        $report = $this->submit(['type' => 'milestone', 'milestone' => $milestone->id, 'milestone_date' => '2026-07-10']);
        $this->assertNull($milestone->fresh()->completed);
        $this->approve($report)->assertSessionHasNoErrors();
        $this->assertSame('2026-07-10', $milestone->fresh()->completed->format('Y-m-d'));
    }

    public function test_new_files_and_thumbnails_are_private_and_follow_approval_visibility(): void
    {
        Storage::fake('reports_private');
        $report = $this->submit([
            'files' => [UploadedFile::fake()->create('evidencia.pdf', 5, 'application/pdf')],
            'photos' => [UploadedFile::fake()->image('foto.jpg')],
        ]);
        $file = $report->files()->sole();
        $photo = $report->photos()->sole();
        Storage::disk('reports_private')->assertExists($file->getRawOriginal('path'));
        $this->assertStringNotContainsString('/storage/', $file->path);
        foreach ([$file->path, $photo->path, $photo->thumbnail_path] as $url) {
            $this->actingAs($this->other)->get($url)->assertNotFound();
            $this->actingAs($this->reporter)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->approve($report)->assertSessionHasNoErrors();
        $this->actingAs($this->other)->get($file->path)->assertOk();
        $this->objective->hidden = true;
        $this->objective->save();
        $outsider = \Database\Factories\UserFactory::new()->create(['surname' => 'Externo']);
        $this->actingAs($outsider)->get($file->path)->assertNotFound();
    }

    public function test_notification_channels_always_include_database_and_respect_mail_preference(): void
    {
        $report = $this->submit();
        $notice = new ReportReviewNotification($report, 'submitted', $this->reporter);
        $this->admin->notification_preferences = 'mail';
        $this->assertSame(['database', 'mail'], $notice->via($this->admin));
        $this->admin->notification_preferences = '';
        $this->assertSame(['database'], $notice->via($this->admin));
        $this->assertStringContainsString('Revisión de reporte', $notice->toMail($this->admin)->subject);
    }

    public function test_admin_directory_filters_review_status_and_exports_it(): void
    {
        $pending = $this->submit(['title' => 'Reporte por revisar']);
        $this->submit(['title' => 'Reporte publicado'], $this->admin);
        $this->actingAs($this->admin)->get(route('admin.reports', ['revision' => 'pending']))
            ->assertOk()->assertSee($pending->title)->assertDontSee('Reporte publicado')->assertSee('Pendientes de revisión: 1');
        $csv = $this->actingAs($this->admin)->get(route('admin.reports.download', ['revision' => 'pending']));
        $csv->assertOk();
        $this->assertStringContainsString('Revisión', $csv->streamedContent());
        $this->assertStringContainsString($pending->title, $csv->streamedContent());
        $this->assertStringNotContainsString('Reporte publicado', $csv->streamedContent());
    }

    public function test_sitemap_and_nested_objective_resources_exclude_proposals(): void
    {
        $report = $this->submit(['title' => 'Secreto pendiente']);
        $this->assertStringNotContainsString(route('reports.index', $report->id), app(\App\Services\PublicSitemap::class)->render());
        $this->getJson(route('apiService.objectives', ['with' => 'objective_latest_report,objective_latest_reports,objective_stats']))
            ->assertOk()->assertDontSee('Secreto pendiente')->assertJsonPath('data.0.reports_count', 0);
    }

    public function test_map_and_attachment_changes_invalidate_review_without_applying_goal_changes(): void
    {
        $this->seed(\Database\Seeders\SettingsTableSeeder::class);
        \App\Setting::where('name', 'app_map_enabled')->update(['value' => true]);
        \Illuminate\Support\Facades\Cache::flush();
        Storage::fake('reports_private');
        $report = $this->submit();
        $oldReview = $this->reviewData($report);
        $geometry = ['type' => 'FeatureCollection', 'features' => []];
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.map.form'), [
            'review_version' => 1, 'map_lat' => -36, 'map_long' => -58, 'map_zoom' => 4,
            'map_center' => json_encode([-58, -36]), 'map_geometries' => json_encode($geometry),
        ])->assertSessionHasNoErrors();
        $this->assertSame($geometry, $report->fresh()->map_geometries);
        $this->actingAs($this->admin)->post(route('reports.review.approve', $report->id), $oldReview)->assertSessionHasErrors('review');
        $this->actingAs($this->reporter)->post($this->panelUrl($report, '.files.form'), [
            'review_version' => $report->fresh()->review_version, 'file' => UploadedFile::fake()->create('anexo.txt', 1),
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $report->fresh()->review_version);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->actingAs($this->admin)->get($this->panelUrl($report))->assertOk()->assertSee('anexo.txt');
        $this->actingAs($this->reporter)->get($this->panelUrl($report, '.map'))->assertOk()->assertSee('FeatureCollection');
        $this->approve($report->fresh())->assertSessionHasNoErrors();
        $this->get(route('reports.index', $report->id))->assertOk()->assertSee('FeatureCollection');
        $report->map_geometries = json_encode($geometry);
        $report->save();
        $this->assertSame($geometry, $report->fresh()->map_geometries);
        Notification::assertSentToTimes($this->admin, ReportReviewNotification::class, 1);
    }

    public function test_storage_failure_rolls_back_report_and_direct_publication_and_cleans_partial_files(): void
    {
        Notification::fake();
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('reports_private')->andReturn($disk);
        $this->actingAs($this->admin)->post($this->createUrl(), $this->payload([
            'status' => 'reached', 'photos' => [UploadedFile::fake()->image('foto.jpg')],
        ]))->assertStatus(500);
        $this->assertSame(0, Report::count());
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->assertSame('ongoing', $this->goal->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_failure_during_approval_rolls_back_goal_and_report_without_notifications(): void
    {
        $report = $this->submit();
        Notification::fake();
        $fail = true;
        Report::saving(function (Report $saving) use (&$fail): void {
            if ($fail && $saving->isApproved()) {
                throw new \RuntimeException('Falla de persistencia simulada');
            }
        });
        try {
            $this->approve($report)->assertStatus(500);
            $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
            $this->assertSame(ReviewStatus::Pending, $report->fresh()->review_status);
            Notification::assertNothingSent();
        } finally {
            $fail = false;
        }
    }

    public function test_notifications_are_only_sent_to_subscribers_on_publication_when_requested(): void
    {
        $subscriber = \Database\Factories\UserFactory::new()->create(['surname' => 'Suscriptor']);
        $this->objective->subscribers()->attach($subscriber);
        $report = $this->submit(['type' => 'post', 'notify' => 'true']);
        Notification::assertNotSentTo($subscriber, NewReport::class);
        $this->actingAs($this->admin)->post(route('reports.review.approve', $report->id), $this->reviewData($report) + ['notify' => 'true'])->assertSessionHasNoErrors();
        Notification::assertSentTo($subscriber, NewReport::class, fn ($notice) => $notice->afterCommit === true);
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class);
        $this->objective->hidden = true;
        $this->objective->save();
        Notification::fake();
        $hidden = $this->submit(['type' => 'post', 'notify' => 'true']);
        $this->actingAs($this->admin)->post(route('reports.review.approve', $hidden->id), $this->reviewData($hidden) + ['notify' => 'true'])->assertSessionHasNoErrors();
        Notification::assertNotSentTo($subscriber, NewReport::class);
    }

    public function test_removing_membership_revokes_access_to_proposal_and_its_mutations(): void
    {
        $report = $this->submit();
        $this->objective->members()->detach($this->reporter);
        $this->actingAs($this->reporter)->get($this->panelUrl($report))->assertForbidden();
        $this->actingAs($this->reporter)->post(route('reports.review.resubmit', $report->id), ['review_version' => 1])->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function submit(array $overrides = [], ?User $actor = null): Report
    {
        $this->actingAs($actor ?? $this->reporter)->post($this->createUrl(), $this->payload($overrides))->assertSessionHasNoErrors();
        return Report::latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace(['type' => 'progress', 'title' => 'Propuesta', 'content' => 'Evidencia', 'date' => '2026-08-01', 'progress' => 5, 'tags' => []], $overrides);
    }

    private function createUrl(): string
    {
        return route('objectives.manage.goals.reports.add.form', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]);
    }

    private function panelUrl(Report $report, string $suffix = '.index'): string
    {
        return route('objectives.manage.goals.reports'.$suffix, ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'reportId' => $report->id]);
    }

    /** @return array{review_version: int, context: string} */
    private function reviewData(Report $report): array
    {
        return ['review_version' => $report->review_version, 'context' => app(ReportReview::class)->context($report)];
    }

    private function approve(Report $report): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('reports.review.approve', $report->id), $this->reviewData($report));
    }
}
