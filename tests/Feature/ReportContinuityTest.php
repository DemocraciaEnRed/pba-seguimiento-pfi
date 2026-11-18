<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\PfiCategory;
use App\Report;
use App\Role;
use App\Services\Goals\GoalAssignments;
use App\Services\Pfi\PfiMemberships;
use App\Services\Reports\ReviewStatus;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportContinuityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $responsible;
    private User $reporter;
    private User $replacement;
    private Goal $goal;
    private PfiCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->admin = UserFactory::new()->create(['surname' => 'Admin']);
        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $this->admin->roles()->attach($role);
        $this->category = PfiCategory::create(['name' => 'Equipo']);
        $this->responsible = $this->member();
        $this->reporter = $this->member();
        $this->replacement = $this->member();

        $category = new Category();
        $category->forceFill(['title' => 'Eje', 'icon' => 'fas fa-circle', 'color' => '#123456', 'order' => 1])->save();
        $strategic = new StrategicObjective();
        $strategic->forceFill(['codigo' => 'OE1', 'title' => 'Objetivo estratégico', 'category_id' => $category->id])->save();
        $objective = new Objective();
        $objective->forceFill(['title' => 'Objetivo', 'content' => 'Contenido', 'hidden' => false,
            'author_id' => $this->admin->id, 'strategic_objective_id' => $strategic->id])->save();
        $this->goal = $this->goal($objective);
        app(GoalAssignments::class)->assignResponsible($this->goal, $this->responsible, $this->admin);
        app(GoalAssignments::class)->assignReporter($this->goal, $this->reporter, $this->admin);
    }

    public function test_reporter_removal_requires_each_destination_and_preserves_authorship(): void
    {
        $pending = $this->report($this->goal);
        $returned = $this->report($this->goal, ReviewStatus::Returned);
        $approved = $this->report($this->goal, ReviewStatus::Approved);
        $url = route('panel.goal-assignments.reporters.delete', ['goalId' => $this->goal->id, 'userId' => $this->reporter->id]);
        $this->actingAs($this->responsible)->get(route('panel.goal-assignments.reporters.remove', [
            'goalId' => $this->goal->id, 'userId' => $this->reporter->id,
        ]))->assertOk()->assertSee($pending->title)->assertSee($returned->title)->assertDontSee($approved->title);
        $this->delete($url)->assertSessionHasErrors('destinations');
        $this->assertTrue($this->reporter->isReporterForGoal($this->goal->id));

        $this->post(route('panel.goal-assignments.reporters.store', [
            'goalId' => $this->goal->id, 'userId' => $this->replacement->id,
        ]))->assertSessionHasNoErrors();
        $this->delete($url, ['destinations' => [$pending->id => $this->replacement->id, $returned->id => $this->replacement->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        foreach ([$pending, $returned] as $report) {
            $report->refresh();
            $this->assertSame($this->reporter->id, $report->author_id);
            $this->assertSame($this->replacement->id, $report->attendant_id);
            $this->assertSame(2, $report->review_version);
            $this->assertTrue($this->replacement->can('update', $report));
            $this->assertFalse($this->reporter->can('update', $report));
        }
        $this->assertSame($this->reporter->id, $approved->fresh()->attendant_id);
        $this->assertFalse($this->replacement->can('update', $approved));
        $this->assertTrue($this->reporter->fresh()->isPfiMember());
        $this->actingAs($this->reporter)->get($this->reportUrl($pending))->assertForbidden();
        $this->actingAs($this->replacement)->get($this->reportUrl($pending))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_pfi_removal_is_atomic_across_goals_and_rejects_invalid_or_stale_destinations(): void
    {
        $otherGoal = $this->goal($this->goal->objective);
        $otherReplacement = $this->member();
        $nonPfi = UserFactory::new()->create(['surname' => 'No PFI']);
        $otherGoal->reporters()->attach($nonPfi);
        $assignments = app(GoalAssignments::class);
        $assignments->assignReporter($otherGoal, $this->reporter, $this->admin);
        $assignments->assignResponsible($otherGoal, $this->reporter, $this->admin);
        $assignments->assignReporter($this->goal, $this->replacement, $this->admin);
        $assignments->assignReporter($otherGoal, $otherReplacement, $this->admin);
        $first = $this->report($this->goal);
        $second = $this->report($otherGoal, ReviewStatus::Returned);
        $approved = $this->report($otherGoal, ReviewStatus::Approved);
        $url = route('admin.users.pfi.delete.form', ['userId' => $this->reporter->id]);
        $this->actingAs($this->admin)->get(route('admin.users.pfi.remove', ['userId' => $this->reporter->id]))
            ->assertOk()->assertSee('0 Responsable(s) restante(s)')->assertSee($first->title)->assertSee($second->title);

        foreach ([
            [$first->id => $this->replacement->id],
            [$first->id => $this->replacement->id, $second->id => $this->replacement->id],
            [$first->id => $this->replacement->id, $second->id => $this->reporter->id],
            [$first->id => $this->replacement->id, $second->id => $nonPfi->id],
            [$first->id => $this->replacement->id, $second->id => $otherReplacement->id, $approved->id => $otherReplacement->id],
        ] as $destinations) {
            $this->delete($url, ['destinations' => $destinations])->assertSessionHasErrors();
            $this->assertTrue($this->reporter->fresh()->isPfiMember());
            $this->assertSame(3, DB::table('goal_user')->where('user_id', $this->reporter->id)->count());
            $this->assertSame($this->reporter->id, $first->fresh()->attendant_id);
            $this->assertSame(1, $first->review_version);
        }

        $this->delete($url, ['destinations' => [$first->id => $this->replacement->id, $second->id => $otherReplacement->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertFalse($this->reporter->fresh()->isPfiMember());
        $this->assertSame(0, $this->reporter->pfiCategories()->count());
        $this->assertDatabaseMissing('goal_user', ['user_id' => $this->reporter->id]);
        $this->assertSame($this->replacement->id, $first->fresh()->attendant_id);
        $this->assertSame($otherReplacement->id, $second->fresh()->attendant_id);
        $this->assertSame($this->reporter->id, $second->author_id);
        $this->assertSame($this->reporter->id, $approved->fresh()->attendant_id);
    }

    public function test_last_responsible_can_leave_without_replacement_but_not_without_pending_continuity(): void
    {
        $report = $this->report($this->goal);
        app(GoalAssignments::class)->assignResponsible($this->goal, $this->reporter, $this->admin);
        app(GoalAssignments::class)->removeResponsible($this->goal, $this->responsible, $this->admin);
        app(GoalAssignments::class)->assignReporter($this->goal, $this->replacement, $this->admin);
        app(GoalAssignments::class)->removeReporter($this->goal, $this->reporter, $this->admin);
        $this->assertSame($this->reporter->id, $report->fresh()->attendant_id);
        $this->assertTrue($this->reporter->can('update', $report));
        $url = route('admin.goal-assignments.responsibles.delete', ['goalId' => $this->goal->id, 'userId' => $this->reporter->id]);
        $this->actingAs($this->admin)->delete($url)->assertSessionHasErrors('destinations');
        $this->delete($url, ['destinations' => [$report->id => $this->replacement->id]])->assertSessionHas('success');
        $this->assertSame(0, $this->goal->responsibles()->count());
        $this->assertSame($this->replacement->id, $report->fresh()->attendant_id);
    }

    public function test_reassignment_only_grants_attention_to_the_selected_pending_and_checks_version_and_state(): void
    {
        $report = $this->report($this->goal);
        $other = $this->report($this->goal);
        app(GoalAssignments::class)->assignReporter($this->goal, $this->replacement, $this->admin);
        $url = route('objectives.manage.goals.reports.attendant', [
            'objectiveId' => $this->goal->objective_id, 'goalId' => $this->goal->id, 'reportId' => $report->id,
        ]);
        $data = ['attendant_id' => $this->replacement->id, 'review_version' => 1];
        $this->actingAs($this->reporter)->put($url, $data)->assertForbidden();
        $this->actingAs($this->responsible)->put($url, $data)->assertSessionHasNoErrors();
        $this->assertTrue($this->replacement->can('update', $report->fresh()));
        $this->assertFalse($this->replacement->can('update', $other));
        $this->assertSame($this->reporter->id, $report->fresh()->author_id);
        $this->put($url, $data)->assertSessionHasErrors('review');
        $report->refresh();
        $report->review_status = ReviewStatus::Approved;
        $report->save();
        $this->actingAs($this->admin)->put($url, [
            'attendant_id' => $this->replacement->id, 'review_version' => $report->review_version,
        ])->assertSessionHasErrors('destinations');
        $this->assertSame($this->replacement->id, $report->fresh()->attendant_id);
    }

    public function test_removal_rechecks_the_actors_assignment_after_preloading(): void
    {
        $this->responsible->loadAuthorizationContext();
        DB::table('goal_user')->where('goal_id', $this->goal->id)->where('user_id', $this->responsible->id)->delete();
        try {
            app(GoalAssignments::class)->removeReporter($this->goal, $this->reporter, $this->responsible);
            $this->fail('A revoked Responsible must not remove assignments.');
        } catch (AuthorizationException) {
            $this->assertTrue($this->reporter->isReporterForGoal($this->goal->id));
        }
    }

    public function test_only_admin_can_remove_pfi_membership(): void
    {
        foreach ([$this->responsible, $this->reporter] as $actor) {
            $this->actingAs($actor)->get(route('admin.users.pfi.remove', ['userId' => $this->reporter->id]))->assertForbidden();
            $this->delete(route('admin.users.pfi.delete.form', ['userId' => $this->reporter->id]))->assertForbidden();
        }
        $this->assertTrue($this->reporter->fresh()->isPfiMember());
        $this->assertTrue($this->reporter->isReporterForGoal($this->goal->id));
    }

    public function test_backfill_only_initializes_pending_attendants_without_overwriting_reassignments(): void
    {
        $pending = $this->report($this->goal);
        $approved = $this->report($this->goal, ReviewStatus::Approved);
        $pending->attendant_id = null;
        $pending->save();
        $approved->attendant_id = null;
        $approved->save();
        $migration = require database_path('migrations/2026_11_18_153728_backfill_report_attendants.php');
        $migration->up();
        $this->assertSame($this->reporter->id, $pending->fresh()->attendant_id);
        $this->assertNull($approved->fresh()->attendant_id);
        $pending->attendant_id = $this->replacement->id;
        $pending->save();
        $migration->up();
        $this->assertSame($this->replacement->id, $pending->fresh()->attendant_id);
        $this->assertSame($this->reporter->id, $pending->author_id);
    }

    private function member(): User
    {
        $user = UserFactory::new()->create(['surname' => 'Integrante']);
        app(PfiMemberships::class)->admit($user, [$this->category->id], $this->admin);

        return $user;
    }

    private function goal(Objective $objective): Goal
    {
        $goal = new Goal();
        $goal->forceFill(['title' => 'Meta '.($objective->goals()->count() + 1), 'status' => 'ongoing',
            'objective_id' => $objective->id, 'visibility' => 'internal'])->save();

        return $goal;
    }

    private function report(Goal $goal, ReviewStatus $status = ReviewStatus::Pending): Report
    {
        $report = new Report();
        $report->forceFill(['title' => 'Reporte '.(Report::count() + 1), 'type' => 'post', 'content' => 'Contenido',
            'date' => now(), 'goal_id' => $goal->id, 'author_id' => $this->reporter->id,
            'attendant_id' => $this->reporter->id, 'review_status' => $status])->save();

        return $report;
    }

    private function reportUrl(Report $report): string
    {
        return route('objectives.manage.goals.reports.index', [
            'objectiveId' => $this->goal->objective_id, 'goalId' => $report->goal_id, 'reportId' => $report->id,
        ]);
    }
}
