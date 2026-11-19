<?php

namespace Tests\Feature;

use App\Category;
use App\ActionLog;
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
        $this->goal = new Goal();
        $this->goal->title = 'Meta';
        $this->goal->status = 'ongoing';
        $this->goal->measurement_mode = 'simple';
        $this->goal->indicator_goal = 100;
        $this->goal->indicator_progress = 40;
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();
        $this->goal->responsibles()->attach($this->manager);
        $this->goal->reporters()->attach([$this->reporter->id, $this->other->id]);
        Notification::fake();
    }

    public function test_responsible_validation_has_no_effect_and_administration_publication_applies_once(): void
    {
        $this->goal->responsibles()->attach($this->other);
        $report = $this->submit(['status' => 'reached', 'progress' => 20.5]);
        $this->assertSame(ReviewStatus::Pending, $report->review_status);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->assertSame('ongoing', $this->goal->fresh()->status);
        Notification::assertSentTo($this->manager, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->manager)['action'] === 'submitted');
        Notification::assertNotSentTo($this->admin, ReportReviewNotification::class);
        Notification::assertNotSentTo($this->reporter, NewReport::class);
        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Validated, $report->fresh()->review_status);
        $this->assertSame($this->manager->id, $report->fresh()->validated_by);
        Notification::assertSentTo($this->admin, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->admin)['action'] === 'validated');
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->reporter)['action'] === 'validated');
        Notification::assertSentToTimes($this->other, ReportReviewNotification::class, 1);
        $this->actingAs($this->other)->get($this->panelUrl($report))->assertOk()->assertSee('Validado');
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->assertSame('ongoing', $this->goal->fresh()->status);
        $this->publish($report->fresh())->assertSessionHasNoErrors();
        $this->assertSame(60.5, $this->goal->fresh()->indicator_progress);
        $this->assertSame('reached', $this->goal->fresh()->status);
        $this->assertSame(40.0, $report->fresh()->previous_progress);
        $this->assertSame($this->admin->id, $report->fresh()->reviewed_by);
        Notification::assertSentTo($this->manager, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->manager)['action'] === 'published'
            && $notice->toDatabase($this->manager)['url'] === route('reports.index', $report->id));
        $this->publish($report->fresh())->assertForbidden();
        $this->assertSame(60.5, $this->goal->fresh()->indicator_progress);
    }

    public function test_admin_publishes_public_submissions_directly_and_cannot_return_a_report(): void
    {
        $report = $this->submit([], $this->admin);
        $this->assertTrue($report->isApproved());
        $this->assertSame(45.0, $this->goal->fresh()->indicator_progress);
        Notification::assertSentTo($this->manager, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->manager)['action'] === 'published');
        Notification::assertSentTo($this->manager, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->manager)['url'] === route('reports.index', $report->id));
        Notification::assertNotSentTo($this->admin, ReportReviewNotification::class);
        $pending = $this->submit();
        foreach ([$this->reporter, $this->manager] as $actor) {
            $this->actingAs($actor)->post(route('reports.review.publish', $pending->id), $this->reviewData($pending))->assertForbidden();
        }
        $this->actingAs($this->admin)->post(route('reports.review.return', $pending->id), [
            'review_version' => $pending->review_version, 'return_reason' => 'Falta evidencia',
        ])->assertForbidden();
        $this->validateReport($pending, $this->manager)->assertSessionHasNoErrors();
        $this->publish($pending->fresh())->assertSessionHasNoErrors();
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
        $this->validateReport($report->fresh(), $this->manager)->assertSessionHasNoErrors();
        $this->publish($report->fresh())->assertSessionHasNoErrors();
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.edit.form'), $this->payload())->assertForbidden();
        $this->actingAs($this->manager)->get($this->panelUrl($report, '.edit'))->assertForbidden();
    }

    public function test_return_requires_reason_and_only_explicit_resubmit_returns_it_to_pending(): void
    {
        $report = $this->submit();
        $this->goal->responsibles()->attach($this->other);
        $this->actingAs($this->admin)->post(route('reports.review.return', $report->id), ['review_version' => 1, 'return_reason' => 'Adjuntar evidencia'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('reports.review.return', $report->id), ['review_version' => 1])->assertSessionHasErrors('return_reason');
        $this->actingAs($this->manager)->post(route('reports.review.return', $report->id), ['review_version' => 1, 'return_reason' => 'Adjuntar evidencia'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Returned, $report->fresh()->review_status);
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->reporter)['action'] === 'returned');
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.edit.form'), $this->payload(['review_version' => $report->fresh()->review_version]))->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Returned, $report->fresh()->review_status);
        $this->publish($report->fresh())->assertForbidden();
        $this->actingAs($this->manager)->post(route('reports.review.resubmit', $report->id), ['review_version' => $report->fresh()->review_version])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Pending, $report->fresh()->review_status);
        Notification::assertSentTo($this->other, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->other)['action'] === 'resubmitted');
        Notification::assertNotSentTo($this->manager, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->manager)['action'] === 'resubmitted');
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
    }

    public function test_stale_content_or_changed_goal_requires_reload(): void
    {
        $report = $this->submit();
        $data = $this->reviewData($report);
        $this->actingAs($this->manager)->put($this->panelUrl($report, '.edit.form'), $this->payload(['review_version' => 1]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('reports.review.validate', $report->id), $data)->assertSessionHasErrors('review');
        $this->validateReport($report->fresh(), $this->manager)->assertSessionHasNoErrors();
        $data = $this->reviewData($report->fresh());
        $this->goal->indicator_progress = 50;
        $this->goal->save();
        $this->actingAs($this->admin)->post(route('reports.review.publish', $report->id), $data)->assertSessionHasErrors('review');
        $this->publish($report->fresh())->assertSessionHasNoErrors();
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
        $this->validateReport($replacement, $this->manager)->assertSessionHasNoErrors();
        $this->publish($replacement->fresh())->assertSessionHasNoErrors();
        $this->assertSame(0.0, $period->fresh()->progressReport->measured_value);
    }

    public function test_periodic_report_reservation_survives_return_and_validation_until_publication(): void
    {
        $this->travelTo('2026-08-15');
        app(GoalIndicatorConfigurator::class)->apply($this->goal, [
            'measurement_mode' => 'periodic', 'indicator' => 'Resultado', 'indicator_unit' => 'unidades',
            'indicator_direction' => 'higher_is_better', 'indicator_nature' => 'extensive',
            'period_type' => 'quarterly', 'period_start' => '2026-01', 'period_count' => 2,
            'period_targets' => [1 => 10, 2 => 10],
        ]);
        $period = $this->goal->periods()->firstOrFail();
        $report = $this->submit(['goal_period_id' => $period->id, 'measured_value' => 7]);

        $this->actingAs($this->manager)->post(route('reports.review.return', $report->id), [
            'review_version' => $report->review_version, 'return_reason' => 'Aclarar resultado',
        ])->assertSessionHasNoErrors();
        $this->assertSame($report->id, $period->fresh()->reservedReport->id);

        $this->actingAs($this->manager)->post(route('reports.review.resubmit', $report->id), [
            'review_version' => $report->fresh()->review_version,
        ])->assertSessionHasNoErrors();
        $this->validateReport($report->fresh(), $this->manager)->assertSessionHasNoErrors();
        $this->assertSame($report->id, $period->fresh()->reservedReport->id);
        $this->assertNull($period->fresh()->progressReport);

        $this->publish($report->fresh())->assertSessionHasNoErrors();
        $this->assertSame($report->id, $period->fresh()->progressReport->id);

        $milestone = new Milestone();
        $milestone->title = 'Hito compatible';
        $milestone->order = 1;
        $milestone->goal()->associate($this->goal);
        $milestone->save();
        $milestoneReport = $this->submit(['type' => 'milestone', 'milestone' => $milestone->id]);
        $this->validateReport($milestoneReport, $this->manager)->assertSessionHasNoErrors();
        $this->publish($milestoneReport->fresh())->assertSessionHasNoErrors();
        $this->assertSame($report->id, $period->fresh()->progressReport->id);
        $this->assertNotNull($milestone->fresh()->completed);
    }

    public function test_internal_report_is_validated_then_explicitly_closed_without_publication(): void
    {
        $this->goal->visibility = 'internal';
        $this->goal->save();
        $report = $this->submit(['progress' => 5]);

        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Validated, $report->fresh()->review_status);
        $this->assertNull($report->fresh()->closed_at);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->assertNotFoundWhileInternal($report);

        $this->actingAs($this->reporter)->post(route('reports.review.close', $report->id), $this->reviewData($report->fresh()))
            ->assertForbidden();
        $this->actingAs($this->admin)->post(route('reports.review.publish', $report->id), $this->reviewData($report->fresh()))
            ->assertForbidden();
        $this->actingAs($this->admin)->post(route('reports.review.close', $report->id), $this->reviewData($report->fresh()))
            ->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame(ReviewStatus::Validated, $report->review_status);
        $this->assertSame($this->admin->id, $report->closed_by);
        $this->assertNotNull($report->closed_at);
        $this->assertTrue($report->isConsolidated());
        $this->assertSame(45.0, $this->goal->fresh()->indicator_progress);
        $this->actingAs($this->admin)->get($this->panelUrl($report))->assertOk()->assertSee('Cerrado y consolidado');
        $this->actingAs($this->admin)->get($this->panelUrl($report, '.edit'))->assertForbidden();
        $this->actingAs($this->admin)->delete($this->panelUrl($report, '.delete.form'), ['password' => 'password'])->assertForbidden();
        $this->assertSame(1, (new \App\Services\Indicators\ObjectiveStats())->compute($this->objective, $this->admin)['reports_total']);
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->reporter)['action'] === 'closed'
            && $notice->toDatabase($this->reporter)['url'] === route('objectives.manage.goals.reports.index', [
                'objectiveId' => $this->objective->id,
                'goalId' => $this->goal->id,
                'reportId' => $report->id,
            ]));

        $adminCreated = $this->submit(['progress' => 3], $this->admin);
        $this->assertSame(ReviewStatus::Validated, $adminCreated->review_status);
        $this->assertNull($adminCreated->closed_at);
        $this->assertSame(45.0, $this->goal->fresh()->indicator_progress);
        $this->assertNotFoundWhileInternal($adminCreated);
        Notification::assertNotSentTo($this->admin, NewReport::class);
    }

    public function test_submission_falls_back_to_all_admins_without_responsibles_and_validation_notifies_admin_work(): void
    {
        $otherAdmin = \Database\Factories\UserFactory::new()->create(['surname' => 'Otro admin']);
        $otherAdmin->roles()->attach($this->admin->roles()->where('name', 'admin')->firstOrFail());
        $this->goal->responsibles()->detach();

        $report = $this->submit();
        Notification::assertSentTo($this->admin, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->admin)['action'] === 'submitted');
        Notification::assertSentTo($otherAdmin, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($otherAdmin)['action'] === 'submitted');
        Notification::assertNotSentTo($this->reporter, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->reporter)['action'] === 'submitted');

        $this->validateReport($report, $this->admin)->assertSessionHasNoErrors();
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->reporter)['action'] === 'validated');
        Notification::assertSentTo($otherAdmin, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($otherAdmin)['action'] === 'validated');
        Notification::assertNotSentTo($this->admin, ReportReviewNotification::class, fn (ReportReviewNotification $notice): bool => $notice->toDatabase($this->admin)['action'] === 'validated');
    }

    public function test_internal_periodic_measurement_counts_only_after_administration_closes_it(): void
    {
        $this->travelTo('2026-08-15');
        $this->goal->visibility = 'internal';
        $this->goal->save();
        app(GoalIndicatorConfigurator::class)->apply($this->goal, [
            'measurement_mode' => 'periodic', 'indicator' => 'Resultado', 'indicator_unit' => 'unidades',
            'indicator_direction' => 'higher_is_better', 'indicator_nature' => 'extensive',
            'period_type' => 'quarterly', 'period_start' => '2026-01', 'period_count' => 2,
            'period_targets' => [1 => 10, 2 => 10],
        ]);
        $period = $this->goal->periods()->firstOrFail();
        $report = $this->submit(['goal_period_id' => $period->id, 'measured_value' => 7]);
        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->assertNull($period->fresh()->progressReport);

        $this->actingAs($this->admin)->post(route('reports.review.close', $report->id), $this->reviewData($report->fresh()))
            ->assertSessionHasNoErrors();
        $this->assertSame($report->id, $period->fresh()->progressReport->id);
        $this->assertSame(1, (new \App\Services\Indicators\ObjectiveStats())->compute($this->objective, $this->admin)['reports_total']);
        $this->assertSame(0, (new \App\Services\Indicators\ObjectiveStats())->compute($this->objective)['reports_total']);
    }

    public function test_edit_audit_records_actor_and_changed_field_names_without_copying_values(): void
    {
        $report = $this->submit();
        $lastLogId = ActionLog::max('id') ?? 0;
        $this->actingAs($this->reporter)->put($this->panelUrl($report, '.edit.form'), $this->payload([
            'title' => 'Título actualizado', 'content' => 'Texto actualizado', 'review_version' => 1,
        ]))->assertSessionHasNoErrors();

        $log = ActionLog::where('id', '>', $lastLogId)->latest('id')->firstOrFail();
        $context = json_decode($log->context, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($this->reporter->id, $context['user_id']);
        $this->assertContains('title', $context['changed_fields']);
        $this->assertContains('content', $context['changed_fields']);
        $this->assertArrayNotHasKey('content', $context);
        $this->assertStringNotContainsString('Texto actualizado', $log->context);
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
        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->publish($report->fresh())->assertSessionHasNoErrors();
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
        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->publish($report->fresh())->assertSessionHasNoErrors();
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
        $validated = $this->submit(['title' => 'Reporte validado']);
        $this->validateReport($validated, $this->manager)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.reports', ['revision' => 'pending']))
            ->assertOk()->assertSee($pending->title)->assertDontSee('Reporte publicado')->assertSee('Pendientes de revisión: 1');
        $this->actingAs($this->admin)->get(route('admin.reports', ['revision' => 'validated']))
            ->assertOk()->assertSee($validated->title)->assertDontSee($pending->title)
            ->assertSee('Validados pendientes de Administración: 1');
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
        $this->actingAs($this->manager)->post(route('reports.review.validate', $report->id), $oldReview)->assertSessionHasErrors('review');
        $this->actingAs($this->reporter)->post($this->panelUrl($report, '.files.form'), [
            'review_version' => $report->fresh()->review_version, 'file' => UploadedFile::fake()->create('anexo.txt', 1),
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $report->fresh()->review_version);
        $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
        $this->actingAs($this->admin)->get($this->panelUrl($report))->assertOk()->assertSee('anexo.txt');
        $this->actingAs($this->reporter)->get($this->panelUrl($report, '.map'))->assertOk()->assertSee('FeatureCollection');
        $this->validateReport($report->fresh(), $this->manager)->assertSessionHasNoErrors();
        $this->publish($report->fresh())->assertSessionHasNoErrors();
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

    public function test_failure_during_publication_rolls_back_goal_and_report_without_publication_notifications(): void
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
            $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
            Notification::fake();
            $this->publish($report->fresh())->assertStatus(500);
            $this->assertSame(40.0, $this->goal->fresh()->indicator_progress);
            $this->assertSame(ReviewStatus::Validated, $report->fresh()->review_status);
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
        $this->validateReport($report, $this->manager)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('reports.review.publish', $report->id), $this->reviewData($report->fresh()) + ['notify' => 'true'])->assertSessionHasNoErrors();
        Notification::assertSentTo($subscriber, NewReport::class, fn ($notice) => $notice->afterCommit === true);
        Notification::assertSentTo($this->reporter, ReportReviewNotification::class);
        $this->objective->hidden = true;
        $this->objective->save();
        Notification::fake();
        $hidden = $this->submit(['type' => 'post', 'notify' => 'true']);
        $this->validateReport($hidden, $this->manager)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('reports.review.publish', $hidden->id), $this->reviewData($hidden->fresh()) + ['notify' => 'true'])->assertSessionHasNoErrors();
        Notification::assertNotSentTo($subscriber, NewReport::class);

        $this->goal->visibility = 'internal';
        $this->goal->save();
        Notification::fake();
        $this->submit(['type' => 'post', 'notify' => 'true'], $this->admin);
        Notification::assertNotSentTo($subscriber, NewReport::class);
    }

    public function test_removing_membership_revokes_access_to_proposal_and_its_mutations(): void
    {
        $report = $this->submit();
        $this->goal->reporters()->detach($this->reporter);
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

    private function validateReport(Report $report, User $actor): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)->post(route('reports.review.validate', $report->id), $this->reviewData($report));
    }

    private function publish(Report $report): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('reports.review.publish', $report->id), $this->reviewData($report));
    }

    private function assertNotFoundWhileInternal(Report $report): void
    {
        $this->actingAs($this->admin)->get(route('reports.index', $report->id))->assertNotFound();
        $this->getJson(route('apiService.reports'))->assertDontSee($report->title);
    }
}
