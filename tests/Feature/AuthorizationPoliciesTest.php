<?php

namespace Tests\Feature;

use App\Category;
use App\Comment;
use App\Goal;
use App\Http\Middleware\LoadAuthorizationContext;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\Reports\ReportReview;
use App\Services\Reports\ReviewStatus;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthorizationPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $reporter;
    private User $otherReporter;
    private User $outsider;
    private Objective $objective;
    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = UserFactory::new()->create(['surname' => 'Admin']);
        $this->manager = UserFactory::new()->create(['surname' => 'Manager']);
        $this->reporter = UserFactory::new()->create(['surname' => 'Reporter']);
        $this->otherReporter = UserFactory::new()->create(['surname' => 'Other']);
        $this->outsider = UserFactory::new()->create(['surname' => 'Outsider']);

        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $this->admin->roles()->attach($role);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();
        $strategic = new StrategicObjective();
        $strategic->title = 'Objetivo estratégico';
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
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();
        $this->goal->responsibles()->attach($this->manager);
        $this->goal->reporters()->attach([$this->reporter->id, $this->otherReporter->id]);
    }

    public function test_goal_assignments_grant_reporting_but_only_admin_can_modify_structure(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('isAdmin'));
        $this->assertFalse(Gate::forUser($this->manager)->allows('isAdmin'));
        $this->assertFalse(Gate::forUser(null)->allows('isAdmin'));

        foreach ([$this->admin, $this->manager, $this->reporter, $this->outsider] as $user) {
            $canManage = $user->is($this->admin);
            $isMember = ! $user->is($this->outsider);
            $this->assertSame($isMember, $user->can('accessManagement', $this->objective));
            $this->assertSame($canManage, $user->can('update', $this->objective));
            $this->assertSame($canManage, $user->can('delete', $this->objective));
            $this->assertSame($canManage, $user->can('create', [Goal::class, $this->objective]));
            $this->assertSame($canManage, $user->can('update', $this->goal));
            $this->assertSame($canManage, $user->can('delete', $this->goal));
            $this->assertSame($isMember, $user->can('create', [Report::class, $this->goal]));

            $this->actingAs($user)->get(route('objectives.manage.edit', $this->objective->id))
                ->assertStatus($canManage ? 200 : 403);
            $this->actingAs($user)->get(route('objectives.manage.goals.edit', [
                'objectiveId' => $this->objective->id, 'goalId' => $this->goal->id,
            ]))->assertStatus($canManage ? 200 : 403);
        }
    }

    public function test_only_administration_can_hide_or_publish_an_objective(): void
    {
        $url = route('objectives.manage.configuration.hide.form', $this->objective->id);
        $configuration = route('objectives.manage.configuration', $this->objective->id);
        foreach ([false, true] as $hidden) {
            $this->objective->hidden = $hidden;
            $this->objective->save();
            $data = $hidden ? [] : ['hidden' => 'true'];

            foreach ([$this->manager, $this->reporter, $this->outsider] as $user) {
                $this->actingAs($user)->put($url, $data)->assertForbidden();
                $this->assertSame($hidden, (bool) $this->objective->fresh()->hidden);
            }
            $this->actingAs($this->manager)->get($configuration)->assertForbidden();
            $this->actingAs($this->admin)->get($configuration)->assertOk()->assertSee($url, false);
            $this->actingAs($this->admin)->put($url, $data)->assertRedirect($configuration)->assertSessionHasNoErrors();
            $this->assertSame(! $hidden, (bool) $this->objective->fresh()->hidden);
        }
    }

    public function test_report_policies_preserve_stage_author_and_goal_boundaries(): void
    {
        foreach (ReviewStatus::cases() as $status) {
            $report = $this->report($status);
            foreach ([$this->admin, $this->manager, $this->reporter, $this->otherReporter, $this->outsider] as $user) {
                $isAdmin = $user->is($this->admin);
                $isAuthor = $user->is($this->reporter);
                $isUnvalidated = ! in_array($status, [ReviewStatus::Approved, ReviewStatus::Validated], true);
                $canUpdate = $isAdmin || ($isUnvalidated && ($isAuthor || $user->is($this->manager)));
                $canView = $canUpdate
                    || ($status === ReviewStatus::Approved && ! $user->is($this->outsider))
                    || ($status === ReviewStatus::Validated
                        && ($isAdmin || $user->is($this->manager) || ($isAuthor && $user->isReporterForGoal($report->goal_id))));
                $canDelete = $isAdmin || ($isAuthor && $isUnvalidated);
                $canValidate = $status === ReviewStatus::Pending && ($isAdmin || $user->is($this->manager));
                $canReturn = $status === ReviewStatus::Pending && $user->is($this->manager);
                $canPublish = $status === ReviewStatus::Validated && $isAdmin;

                $this->assertSame($canView, $user->can('viewInManagement', $report));
                $this->assertSame($canUpdate, $user->can('update', $report));
                $this->assertSame($canDelete, $user->can('delete', $report));
                $this->assertSame($canValidate, $user->can('validate', $report));
                $this->assertSame($canReturn, $user->can('return', $report));
                $this->assertSame($canPublish, $user->can('publish', $report));
                $this->assertFalse($user->can('close', $report));
                $this->assertSame($canView, $report->canViewInManagement($user));
                $this->assertSame($canUpdate, $report->canManage($user));
                $this->assertSame($canDelete, $report->canDelete($user));
            }
        }
    }

    public function test_assignments_and_public_visibility_do_not_grant_reporting_on_other_goals(): void
    {
        $goal = new Goal();
        $goal->forceFill(['title' => 'Otra meta pública', 'status' => 'ongoing', 'visibility' => 'public',
            'objective_id' => $this->objective->id])->save();
        $parameters = ['objectiveId' => $this->objective->id, 'goalId' => $goal->id];
        $createUrl = route('objectives.manage.goals.reports.add', $parameters);
        $report = $this->report(ReviewStatus::Pending);
        $report->goal()->associate($goal);
        $report->save();

        foreach ([$this->manager, $this->reporter] as $user) {
            $this->assertFalse($user->can('create', [Report::class, $goal]));
            $this->assertFalse($user->can('update', $report));
            $this->assertFalse($user->can('viewInManagement', $report));
            $this->actingAs($user)->get(route('objectives.manage.goals', $this->objective->id))
                ->assertOk()->assertSee($goal->title);
            $this->get(route('objectives.manage.goals.index', $parameters))
                ->assertOk()->assertDontSee($createUrl, false)
                ->assertDontSee(route('objectives.manage.goals.milestones.add', $parameters), false);
            $this->get($createUrl)->assertForbidden();
        }

        $this->assertFalse($this->outsider->can('accessManagement', $this->objective));
        $this->assertFalse($this->outsider->can('update', $this->goal));
        $this->actingAs($this->outsider)->get(route('objectives.manage.index', $this->objective->id))->assertForbidden();
    }

    public function test_comment_team_badges_follow_the_author_and_moderation_is_scoped_to_the_goal(): void
    {
        $this->goal->visibility = 'public';
        $this->goal->save();
        $report = $this->report(ReviewStatus::Approved);
        $report->save();
        $comment = new Comment();
        $comment->content = 'Comentario';
        $comment->user()->associate($this->manager);
        $report->comments()->save($comment);
        $reply = new Comment();
        $reply->content = 'Respuesta';
        $reply->user()->associate($this->outsider);
        $comment->replies()->save($reply);
        $url = route('apiService.reports.comments', ['reportId' => $report->id]);
        foreach ([$this->manager, $this->outsider] as $viewer) {
            $this->actingAs($viewer)->getJson($url)->assertOk()
                ->assertJsonPath('data.0.author_assigned_goal', true)
                ->assertJsonPath('data.0.replies.0.author_assigned_goal', false);
        }

        $otherGoal = new Goal();
        $otherGoal->forceFill(['title' => 'Otra meta', 'status' => 'ongoing', 'objective_id' => $this->objective->id])->save();
        $otherGoal->responsibles()->attach($this->outsider);
        $this->actingAs($this->outsider)->deleteJson(route('apiService.reports.comments.delete', [
            'reportId' => $report->id, 'commentId' => $comment->id,
        ]))->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'deleted_at' => null]);
    }

    public function test_authorization_is_preloaded_once_and_does_not_serialize_assignments(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $this->manager);
        $middleware = new LoadAuthorizationContext();

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $middleware->handle($request, function () use ($request): Response {
                $this->assertCount(2, DB::getQueryLog());
                DB::flushQueryLog();
                for ($i = 0; $i < 3; $i++) {
                    $this->assertFalse($request->user()->hasRole('admin'));
                    $this->assertTrue($request->user()->can('accessManagement', $this->objective));
                    $this->assertFalse($request->user()->can('update', $this->goal));
                    $this->assertTrue($request->user()->isResponsibleForGoal($this->goal->id));
                    $this->assertFalse($request->user()->isReporterForGoal($this->goal->id));
                }
                $this->assertCount(0, DB::getQueryLog());
                $this->assertArrayNotHasKey('objectives', $request->user()->toArray());
                $this->assertArrayNotHasKey('authorizationGoals', $request->user()->toArray());

                return new Response();
            });
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->goal->responsibles()->detach($this->manager);
        $this->assertFalse($this->manager->can('update', $this->objective));
        $this->actingAs($this->manager)->get(route('objectives.manage.index', $this->objective->id))->assertForbidden();
    }

    public function test_failed_requests_do_not_keep_an_authorization_snapshot(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $this->admin);
        $error = new RuntimeException('Request failed');
        try {
            (new LoadAuthorizationContext())->handle($request, function () use ($error): Response {
                throw $error;
            });
            $this->fail('The exception must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame($error, $exception);
        }
        $this->admin->roles()->detach();
        $this->assertFalse(Gate::forUser($this->admin)->allows('isAdmin'));
    }

    public function test_report_mutations_recheck_permissions_after_preloading(): void
    {
        $report = $this->report(ReviewStatus::Pending);
        $report->save();
        $this->reporter->loadAuthorizationContext();
        $this->goal->reporters()->detach($this->reporter);

        try {
            app(ReportReview::class)->mutate($report, $this->reporter, function (): void {
                $this->fail('A removed author cannot change the report.');
            });
            $this->fail('The removed assignment must be rejected.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(1, $report->fresh()->review_version);
        }
    }

    private function report(ReviewStatus $status): Report
    {
        $report = new Report();
        $report->goal()->associate($this->goal);
        $report->author()->associate($this->reporter);
        $report->title = 'Reporte';
        $report->content = 'Contenido';
        $report->type = 'post';
        $report->date = '2026-11-18';
        $report->review_status = $status;

        return $report;
    }
}
