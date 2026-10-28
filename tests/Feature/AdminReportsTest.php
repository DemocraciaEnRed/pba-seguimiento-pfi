<?php

namespace Tests\Feature;

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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reporter;

    private Category $mobility;

    private Category $health;

    private Objective $bikeLanes;

    private Objective $hospitals;

    private Goal $northLanes;

    private Goal $southLanes;

    private Report $northReport;

    private Report $southReport;

    private Report $hospitalReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = $this->createUser('Ana', 'Admin', 'admin@example.com');
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);
        $this->reporter = $this->createUser('Rita', 'Reportera', 'rita@example.com');

        $this->mobility = $this->createCategory('Movilidad', 1);
        $this->health = $this->createCategory('Salud', 2);
        $this->bikeLanes = $this->createObjective('Sumar ciclovías', $this->mobility);
        $this->hospitals = $this->createObjective('Ampliar hospitales', $this->health, hidden: true);
        $this->northLanes = $this->createGoal('Ciclovías zona norte', $this->bikeLanes);
        $this->southLanes = $this->createGoal('Ciclovías zona sur', $this->bikeLanes);
        $hospitalGoal = $this->createGoal('Nuevas guardias', $this->hospitals);

        $this->northReport = $this->createReport('Avance norte', $this->northLanes, $this->reporter, '2026-03-10', '2026-03-12 10:00:00');
        $this->southReport = $this->createReport('Avance sur', $this->southLanes, $this->admin, '2026-05-01', '2026-05-02 10:00:00');
        $this->hospitalReport = $this->createReport('Guardia inaugurada', $hospitalGoal, $this->reporter, '2026-04-15', '2026-04-20 10:00:00');

        foreach (['Bien', 'Excelente'] as $content) {
            $comment = new Comment();
            $comment->content = $content;
            $comment->user()->associate($this->admin);
            $this->southReport->comments()->save($comment);
        }
        $testimony = new Testimony();
        $testimony->value = true;
        $testimony->user_id = $this->admin->id;
        $testimony->report_id = $this->southReport->id;
        $testimony->save();
    }

    public function test_only_admins_can_access_the_reports_section(): void
    {
        $this->get(route('admin.reports'))->assertRedirect(route('login'));
        $this->actingAs($this->reporter)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($this->reporter)->get(route('admin.reports.download'))->assertForbidden();
    }

    public function test_list_shows_every_report_with_its_hierarchy_and_counts(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('3 resultado(s)')
            ->assertSeeInOrder(['Avance sur', 'Guardia inaugurada', 'Avance norte'])
            ->assertSee('Sumar ciclovías')
            ->assertSee('Ciclovías zona sur')
            ->assertSee('Objetivo oculto')
            ->assertSee('Por Reportera, Rita')
            ->assertSee('2<i class="far fa-comments fa-fw ml-1"></i>', false)
            ->assertSee('1<i class="far fa-thumbs-up fa-fw ml-1"></i>', false)
            ->assertSee(route('objectives.manage.goals.reports.index', ['objectiveId' => $this->bikeLanes->id, 'goalId' => $this->southLanes->id, 'reportId' => $this->southReport->id]))
            ->assertSee(route('reports.index', ['reportId' => $this->southReport->id]));
    }

    public function test_list_searches_by_title(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['q' => 'avance']))
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Guardia inaugurada');
    }

    public function test_list_filters_by_category_objective_and_goal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['eje' => $this->health->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Guardia inaugurada')
            ->assertDontSee('id="meta"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['eje' => $this->mobility->id, 'objetivo' => $this->bikeLanes->id]))
            ->assertSee('2 resultado(s)')
            ->assertSee('id="meta"', false)
            ->assertSee('Ciclovías zona norte');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['objetivo' => $this->bikeLanes->id, 'meta' => $this->northLanes->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Avance norte');
    }

    public function test_chained_filters_that_do_not_match_are_ignored(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['eje' => $this->health->id, 'objetivo' => $this->bikeLanes->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Guardia inaugurada');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['meta' => $this->northLanes->id]))
            ->assertSee('3 resultado(s)');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['objetivo' => $this->hospitals->id, 'meta' => $this->northLanes->id]))
            ->assertSee('1 resultado(s)')
            ->assertSee('Guardia inaugurada');
    }

    public function test_list_filters_by_upload_dates_author_and_comments(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['desde' => '2026-04-01', 'hasta' => '2026-04-20']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Guardia inaugurada');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['desde' => 'no-es-fecha']))
            ->assertSee('3 resultado(s)');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['autor' => 'Rita Reportera']))
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Avance sur');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['comentarios' => 'con']))
            ->assertSee('1 resultado(s)')
            ->assertSee('Avance sur');

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['comentarios' => 'sin']))
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Avance sur');
    }

    public function test_list_can_be_sorted(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['orden' => 'antiguos']))
            ->assertSeeInOrder(['Avance norte', 'Guardia inaugurada', 'Avance sur']);

        $this->actingAs($this->admin)
            ->get(route('admin.reports', ['orden' => 'comentados']))
            ->assertSeeInOrder(['Avance sur', 'Guardia inaugurada', 'Avance norte']);
    }

    public function test_reports_of_deleted_goals_are_not_listed(): void
    {
        $this->northLanes->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('2 resultado(s)')
            ->assertDontSee('Avance norte');
    }

    public function test_csv_export_respects_the_filters(): void
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.download', ['eje' => $this->mobility->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Avance sur', $csv);
        $this->assertStringContainsString('Avance norte', $csv);
        $this->assertStringContainsString('Ciclovías zona sur', $csv);
        $this->assertStringContainsString('admin@example.com', $csv);
        $this->assertStringNotContainsString('Guardia inaugurada', $csv);
    }

    public function test_menu_and_dashboard_link_to_the_reports_section(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->actingAs($this->admin)
            ->get(route('admin.index', ['rango' => 'last_month']))
            ->assertSee(route('admin.reports'))
            ->assertSee(e(route('admin.reports', ['desde' => '2026-05-01', 'hasta' => '2026-05-31'])), false);

        $this->actingAs($this->admin)
            ->get(route('admin.index', ['rango' => 'all']))
            ->assertDontSee('admin/reportes?', false);
    }

    private function createUser(string $name, string $surname, string $email): User
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

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createObjective(string $title, Category $category, bool $hidden = false): Objective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-'.$category->id;
        $strategicObjective->title = 'Estratégico de '.$category->title;
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(string $title, Objective $objective): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    private function createReport(string $title, Goal $goal, User $author, string $date, string $createdAt): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = $date;
        $report->author()->associate($author);
        $report->goal()->associate($goal);
        $report->created_at = $createdAt;
        $report->save();

        return $report;
    }
}
