<?php

namespace Tests\Feature;

use App\ActionLog;
use App\Category;
use App\Comment;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObjectivePanelCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $reporter;

    private User $citizen;

    private Objective $objective;

    private Goal $goal;

    private Goal $otherGoal;

    private Comment $goalComment;

    private Comment $goalReply;

    private Comment $otherGoalComment;

    private Comment $otherObjectiveComment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->manager = $this->createUser('manager@example.com', 'Marta', 'Coordinadora');
        $this->reporter = $this->createUser('reporter@example.com', 'Raúl', 'Reportero');
        $this->citizen = $this->createUser('vecina@example.com', 'Carla', 'Vecina');

        $this->objective = $this->createObjective('Reducir emisiones');
        $this->objective->members()->attach($this->manager, ['role' => 'manager']);
        $this->objective->members()->attach($this->reporter, ['role' => 'reporter']);
        $this->goal = $this->createGoal($this->objective, 'Plantar árboles');
        $this->otherGoal = $this->createGoal($this->objective, 'Sumar ciclovías');
        foreach ([$this->goal, $this->otherGoal] as $goal) {
            $goal->responsibles()->attach($this->manager);
            $goal->reporters()->attach($this->reporter);
        }
        $otherObjectiveGoal = $this->createGoal($this->createObjective('Otro objetivo'), 'Meta ajena');

        $this->goalComment = $this->createComment($this->createReport($this->goal, 'Avance de forestación'), 'Muy buen avance');
        $this->goalReply = $this->createReply($this->goalComment, 'Gracias por comentar');
        $this->otherGoalComment = $this->createComment($this->createReport($this->otherGoal, 'Nueva ciclovía'), 'Falta iluminación');
        $this->otherObjectiveComment = $this->createComment($this->createReport($otherObjectiveGoal, 'Reporte ajeno'), 'Comentario de otro objetivo');
    }

    public function test_objective_panel_lists_comments_of_all_its_goals_only(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('objectives.manage.comments', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('Muy buen avance')
            ->assertSee('Gracias por comentar')
            ->assertSee('Falta iluminación')
            ->assertSee('Meta: Sumar ciclovías')
            ->assertDontSee('Comentario de otro objetivo')
            ->assertSee('3 resultado(s)');
    }

    public function test_goal_panel_lists_only_the_goal_comments(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('objectives.manage.goals.comments', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('Muy buen avance')
            ->assertSee('Gracias por comentar')
            ->assertDontSee('Falta iluminación')
            ->assertDontSee('Comentario de otro objetivo')
            ->assertSee('2 resultado(s)');
    }

    public function test_panel_comments_can_be_searched_and_filtered(): void
    {
        $this->actingAs($this->manager)
            ->get(route('objectives.manage.comments', ['objectiveId' => $this->objective->id, 'q' => 'ciclovía']))
            ->assertSee('Falta iluminación')->assertDontSee('Muy buen avance');

        $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.comments', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'tipo' => 'respuestas']))
            ->assertSee('Gracias por comentar')->assertDontSee('Muy buen avance');
    }

    public function test_non_members_cannot_see_the_panel_comments(): void
    {
        $this->actingAs($this->citizen)
            ->get(route('objectives.manage.comments', ['objectiveId' => $this->objective->id]))
            ->assertForbidden();

        $this->actingAs($this->citizen)
            ->delete(route('objectives.manage.comments.delete.form', ['objectiveId' => $this->objective->id, 'commentId' => $this->goalComment->id]))
            ->assertForbidden();
        $this->assertNotSoftDeleted($this->goalComment);
    }

    public function test_assigned_users_can_download_comments_within_their_audience(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('objectives.manage.comments', ['objectiveId' => $this->objective->id]))
            ->assertSee('Descargar CSV');
        $this->actingAs($this->reporter)
            ->get(route('objectives.manage.comments.download', ['objectiveId' => $this->objective->id]))
            ->assertOk();
        $this->actingAs($this->reporter)
            ->get(route('objectives.manage.goals.comments.download', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertOk();

        $csv = $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.comments.download', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Muy buen avance', $csv);
        $this->assertStringContainsString('Gracias por comentar', $csv);
        $this->assertStringNotContainsString('Falta iluminación', $csv);

        $csv = $this->actingAs($this->manager)
            ->get(route('objectives.manage.comments.download', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Falta iluminación', $csv);
        $this->assertStringNotContainsString('Comentario de otro objetivo', $csv);
    }

    public function test_members_can_delete_comments_of_their_objective(): void
    {
        $this->actingAs($this->reporter)
            ->delete(route('objectives.manage.comments.delete.form', ['objectiveId' => $this->objective->id, 'commentId' => $this->goalComment->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($this->goalComment);
        $this->assertSoftDeleted($this->goalReply);
        $this->assertStringContainsString('[Reportero, Raúl] (Miembro del equipo) ha eliminado el comentario', ActionLog::latest('id')->first()->message);
    }

    public function test_goal_panel_deletes_a_reply_of_the_goal(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('objectives.manage.goals.comments.delete.form', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'commentId' => $this->goalReply->id]))
            ->assertRedirect();

        $this->assertSoftDeleted($this->goalReply);
        $this->assertNotSoftDeleted($this->goalComment);
    }

    public function test_comments_outside_the_scope_cannot_be_deleted(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('objectives.manage.comments.delete.form', ['objectiveId' => $this->objective->id, 'commentId' => $this->otherObjectiveComment->id]))
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->delete(route('objectives.manage.goals.comments.delete.form', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id, 'commentId' => $this->otherGoalComment->id]))
            ->assertNotFound();

        $this->assertNotSoftDeleted($this->otherObjectiveComment);
        $this->assertNotSoftDeleted($this->otherGoalComment);
    }

    public function test_panel_home_pages_show_the_latest_comments(): void
    {
        $this->actingAs($this->manager)
            ->get(route('objectives.manage.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('Últimos comentarios')
            ->assertSee('Falta iluminación')
            ->assertSee(route('objectives.manage.comments', ['objectiveId' => $this->objective->id]));

        $this->actingAs($this->manager)
            ->get(route('objectives.manage.goals.index', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('Gracias por comentar')
            ->assertDontSee('Falta iluminación')
            ->assertSee(route('objectives.manage.goals.comments', ['objectiveId' => $this->objective->id, 'goalId' => $this->goal->id]));
    }

    private function createObjective(string $title): Objective
    {
        $category = Category::first() ?? tap(new Category(), function (Category $category): void {
            $category->title = 'Eje sostenible';
            $category->icon = 'observatorio-sostenible';
            $category->color = '#12a24f';
            $category->order = 1;
            $category->save();
        });
        $strategicObjective = StrategicObjective::first() ?? tap(new StrategicObjective(), function (StrategicObjective $strategicObjective) use ($category): void {
            $strategicObjective->codigo = 'OE1';
            $strategicObjective->title = 'Objetivo estratégico verde';
            $strategicObjective->category()->associate($category);
            $strategicObjective->save();
        });

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = false;
        $objective->author()->associate($this->manager);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective, string $title): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    private function createReport(Goal $goal, string $title): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = now();
        $report->author()->associate($this->manager);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }

    private function createComment(Report $report, string $content): Comment
    {
        $this->travel(1)->minutes();
        $comment = new Comment();
        $comment->content = $content;
        $comment->user()->associate($this->citizen);
        $report->comments()->save($comment);

        return $comment;
    }

    private function createReply(Comment $parent, string $content): Comment
    {
        $this->travel(1)->minutes();
        $reply = new Comment();
        $reply->content = $content;
        $reply->user()->associate($this->manager);
        $parent->replies()->save($reply);

        return $reply;
    }

    private function createUser(string $email, string $name, string $surname): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}
