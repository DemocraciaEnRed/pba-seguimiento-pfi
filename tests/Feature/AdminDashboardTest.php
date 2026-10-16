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
use App\Testimony;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Objective $objective;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-16 12:00:00');

        $this->admin = $this->createUser('admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

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

        $this->objective = new Objective();
        $this->objective->title = 'Reducir emisiones';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $hiddenObjective = $this->objective->replicate();
        $hiddenObjective->title = 'Objetivo oculto';
        $hiddenObjective->hidden = true;
        $hiddenObjective->save();

        $this->goal = new Goal();
        $this->goal->title = 'Plantar árboles';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($this->objective);
        $this->goal->save();
    }

    public function test_only_admins_can_see_the_dashboard(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));

        $this->actingAs($this->createUser('user@example.com'))
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_dashboard_shows_structure_and_user_stats(): void
    {
        $this->createUser('unverified@example.com', verified: false);

        $response = $this->actingAs($this->admin)->get(route('admin.index'))->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame([
            'categories' => 1,
            'strategic_objectives' => 1,
            'objectives_published' => 1,
            'objectives_hidden' => 1,
            'goals' => 1,
        ], $stats['structure']);
        $this->assertSame(['total' => 2, 'verified' => 1, 'unverified' => 1, 'administrators' => 1], $stats['users']);
        $this->assertSame(1, $stats['traffic_lights']['unmeasured']);
        $response->assertSee('Obj. publicados')->assertSee('Semáforo de cumplimiento')->assertSee('Administradores');
    }

    /**
     * @return array<string, array{0: ?string, 1: array{reports: int, comments: int, users: int, likes: int}}>
     */
    public static function activityRanges(): array
    {
        return [
            'default (30 days)' => [null, ['reports' => 2, 'comments' => 2, 'users' => 1, 'likes' => 1]],
            'invalid falls back to 30 days' => ['nope', ['reports' => 2, 'comments' => 2, 'users' => 1, 'likes' => 1]],
            '7 days' => ['7d', ['reports' => 1, 'comments' => 1, 'users' => 1, 'likes' => 1]],
            '30 days' => ['30d', ['reports' => 2, 'comments' => 2, 'users' => 1, 'likes' => 1]],
            'last calendar month' => ['last_month', ['reports' => 1, 'comments' => 1, 'users' => 0, 'likes' => 0]],
            '3 months' => ['3m', ['reports' => 3, 'comments' => 3, 'users' => 1, 'likes' => 1]],
            '6 months' => ['6m', ['reports' => 3, 'comments' => 3, 'users' => 1, 'likes' => 1]],
            'all time' => ['all', ['reports' => 5, 'comments' => 4, 'users' => 2, 'likes' => 2]],
        ];
    }

    /**
     * @param  array{reports: int, comments: int, users: int, likes: int}  $expected
     */
    #[DataProvider('activityRanges')]
    public function test_activity_is_counted_for_the_selected_range(?string $range, array $expected): void
    {
        foreach (['2026-10-14 10:00:00', '2026-09-20 10:00:00', '2026-08-01 10:00:00', '2025-01-01 10:00:00'] as $date) {
            $this->travelTo($date, function (): void {
                $report = $this->createReport();
                $this->createComment($report);
            });
        }
        $this->travelTo('2025-01-01 10:00:00', fn () => $this->createLike($this->createReport(), $this->createUser('old@example.com')));
        $recentReport = Report::orderBy('date', 'desc')->first();
        $this->createLike($recentReport, $this->admin);
        $this->createLike($recentReport, User::where('email', 'old@example.com')->first(), value: false);
        $this->createComment($recentReport, 'Comentario borrado')->delete();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.index', array_filter(['rango' => $range])))
            ->assertOk();

        $this->assertSame($expected, $response->viewData('stats')['activity']);
    }

    public function test_dashboard_lists_only_the_last_three_logs_and_five_comments(): void
    {
        foreach (range(1, 4) as $number) {
            $log = new ActionLog();
            $log->message = "Acción número {$number}";
            $log->context = json_encode(['n' => $number]);
            $log->level = 200;
            $log->level_name = 'INFO';
            $log->channel = 'mysql';
            $log->record_datetime = now()->addMinutes($number);
            $log->extra = '[]';
            $log->formatted = '';
            $log->save();
        }
        $report = $this->createReport();
        foreach (range(1, 6) as $number) {
            $this->travel(1)->minutes();
            $this->createComment($report, "Comentario número {$number}");
        }

        $response = $this->actingAs($this->admin)->get(route('admin.index'))->assertOk();

        $response->assertSee('Acción número 4')->assertSee('Acción número 2')->assertDontSee('Acción número 1');
        $response->assertSee('Comentario número 6')->assertSee('Comentario número 2')->assertDontSee('Comentario número 1');
        $response->assertSee(route('admin.comments'))->assertSee(route('admin.logs'));
    }

    private function createReport(): Report
    {
        $report = new Report();
        $report->title = 'Reporte';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = now();
        $report->author()->associate($this->admin);
        $report->goal()->associate($this->goal);
        $report->save();

        return $report;
    }

    private function createComment(Report $report, string $content = 'Un comentario'): Comment
    {
        $comment = new Comment();
        $comment->content = $content;
        $comment->user()->associate($this->admin);
        $report->comments()->save($comment);

        return $comment;
    }

    private function createLike(Report $report, User $user, bool $value = true): void
    {
        $like = new Testimony();
        $like->value = $value;
        $like->report()->associate($report);
        $like->user()->associate($user);
        $like->save();
    }

    private function createUser(string $email, bool $verified = true): User
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
