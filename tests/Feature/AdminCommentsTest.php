<?php

namespace Tests\Feature;

use App\ActionLog;
use App\Category;
use App\Comment;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $citizen;

    private Report $report;

    private Report $otherReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = $this->createUser('admin@example.com', 'Ana', 'Admin');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $this->citizen = $this->createUser('vecina@example.com', 'Carla', 'Vecina');

        $category = new Category();
        $category->title = 'Eje sostenible';
        $category->icon = 'observatorio-sostenible';
        $category->color = '#12a24f';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE1';
        $strategicObjective->title = 'Objetivo estratégico verde';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = 'Reducir emisiones';
        $objective->content = 'Descripción';
        $objective->hidden = false;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        $goal = new Goal();
        $goal->title = 'Plantar árboles';
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        $this->report = $this->createReport($goal, 'Avance de forestación');
        $this->otherReport = $this->createReport($goal, 'Nueva ciclovía');
    }

    public function test_only_admins_can_access_the_comments_section(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Hola');

        $this->get(route('admin.comments'))->assertRedirect(route('login'));
        $this->actingAs($this->citizen)->get(route('admin.comments'))->assertForbidden();
        $this->actingAs($this->citizen)->get(route('admin.comments.download'))->assertForbidden();
        $this->actingAs($this->citizen)->delete(route('admin.comments.delete.form', ['commentId' => $comment->id]))->assertForbidden();
        $this->assertNotSoftDeleted($comment);
    }

    public function test_admin_menu_links_to_the_comments_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee(route('admin.comments'));
    }

    public function test_lists_comments_and_replies_with_their_report(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $this->createReply($comment, $this->admin, 'Gracias por comentar');
        $this->createComment($this->otherReport, $this->citizen, 'Falta iluminación');

        $this->actingAs($this->admin)
            ->get(route('admin.comments'))
            ->assertOk()
            ->assertSeeInOrder(['Falta iluminación', 'Nueva ciclovía'])
            ->assertSeeInOrder(['Gracias por comentar', 'Avance de forestación'])
            ->assertSee('Muy buen avance')
            ->assertSee('Respuesta')
            ->assertSee('3 resultado(s)');
    }

    public function test_search_matches_content_author_and_report(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $this->createReply($comment, $this->admin, 'Gracias por comentar');
        $this->createComment($this->otherReport, $this->admin, 'Falta iluminación');

        $this->actingAs($this->admin)->get(route('admin.comments', ['q' => 'iluminación']))
            ->assertSee('Falta iluminación')->assertDontSee('Muy buen avance')->assertDontSee('Gracias por comentar');

        $this->actingAs($this->admin)->get(route('admin.comments', ['q' => 'Vecina']))
            ->assertSee('Muy buen avance')->assertDontSee('Falta iluminación');

        $this->actingAs($this->admin)->get(route('admin.comments', ['q' => 'forestación']))
            ->assertSee('Muy buen avance')->assertSee('Gracias por comentar')->assertDontSee('Falta iluminación');
    }

    public function test_type_filter_separates_comments_and_replies(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $this->createReply($comment, $this->admin, 'Gracias por comentar');

        $this->actingAs($this->admin)->get(route('admin.comments', ['tipo' => 'comentarios']))
            ->assertSee('Muy buen avance')->assertDontSee('Gracias por comentar');

        $this->actingAs($this->admin)->get(route('admin.comments', ['tipo' => 'respuestas']))
            ->assertSee('Gracias por comentar')->assertDontSee('Muy buen avance');
    }

    public function test_deleting_a_comment_also_deletes_its_replies_and_logs_the_action(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $reply = $this->createReply($comment, $this->admin, 'Gracias por comentar');
        $other = $this->createComment($this->otherReport, $this->citizen, 'Falta iluminación');

        $this->actingAs($this->admin)
            ->from(route('admin.comments'))
            ->delete(route('admin.comments.delete.form', ['commentId' => $comment->id]))
            ->assertRedirect(route('admin.comments'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($comment);
        $this->assertSoftDeleted($reply);
        $this->assertNotSoftDeleted($other);

        $log = ActionLog::latest('id')->first();
        $this->assertStringContainsString('ha eliminado el comentario de [Vecina, Carla] hecho en el reporte [Avance de forestación]', $log->message);
    }

    public function test_deleting_a_reply_keeps_the_parent_comment(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $reply = $this->createReply($comment, $this->admin, 'Gracias por comentar');

        $this->actingAs($this->admin)
            ->delete(route('admin.comments.delete.form', ['commentId' => $reply->id]))
            ->assertRedirect();

        $this->assertSoftDeleted($reply);
        $this->assertNotSoftDeleted($comment);
    }

    public function test_deleted_comments_are_not_listed(): void
    {
        $this->createComment($this->report, $this->citizen, 'Comentario borrado')->delete();

        $this->actingAs($this->admin)->get(route('admin.comments'))
            ->assertDontSee('Comentario borrado')
            ->assertSee('No se encontraron comentarios');
    }

    public function test_downloads_the_filtered_comments_as_csv(): void
    {
        $comment = $this->createComment($this->report, $this->citizen, 'Muy buen avance');
        $this->createReply($comment, $this->admin, 'Gracias por comentar');
        $this->createComment($this->otherReport, $this->citizen, 'Falta iluminación');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.comments.download', ['q' => 'forestación']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Tipo,ID,"Respuesta a (ID)",Autor', $csv);
        $this->assertStringContainsString('Respuesta,'.($comment->id + 1).','.$comment->id.',"Admin, Ana",admin@example.com,"Gracias por comentar",'.$this->report->id.',"Avance de forestación","Plantar árboles","Reducir emisiones"', $csv);
        $this->assertStringContainsString('Comentario,'.$comment->id.',-,"Vecina, Carla"', $csv);
        $this->assertStringNotContainsString('Falta iluminación', $csv);
    }

    private function createReport(Goal $goal, string $title): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = now();
        $report->author()->associate($this->admin);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }

    private function createComment(Report $report, User $user, string $content): Comment
    {
        $this->travel(1)->minutes();
        $comment = new Comment();
        $comment->content = $content;
        $comment->user()->associate($user);
        $report->comments()->save($comment);

        return $comment;
    }

    private function createReply(Comment $parent, User $user, string $content): Comment
    {
        $this->travel(1)->minutes();
        $reply = new Comment();
        $reply->content = $content;
        $reply->user()->associate($user);
        $parent->replies()->save($reply);

        return $reply;
    }

    private function createUser(string $email, string $name = 'Test', string $surname = 'User'): User
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
