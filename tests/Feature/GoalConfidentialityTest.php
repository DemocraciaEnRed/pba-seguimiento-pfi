<?php

namespace Tests\Feature;

use App\Category;
use App\Comment;
use App\Exports\ObjectiveGoalsExport;
use App\File;
use App\Goal;
use App\Objective;
use App\Report;
use App\Role;
use App\Services\Admin\ActivityRange;
use App\Services\Admin\AdminDashboardStats;
use App\Services\Goals\GoalAudience;
use App\Services\Indicators\ObjectiveStats;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\NewCommentReportForTeamObjective;
use App\Notifications\NewReplyReport;
use Tests\TestCase;

class GoalConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $assigned;

    private User $outsider;

    private Objective $objective;

    /** @var list<Goal> */
    private array $goals = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = UserFactory::new()->create(['surname' => 'Admin']);
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $this->assigned = UserFactory::new()->create(['surname' => 'Asignada']);
        $this->outsider = UserFactory::new()->create(['surname' => 'Externa']);

        $category = new Category();
        $category->title = 'Eje confidencial';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-C';
        $strategicObjective->title = 'Objetivo estratégico confidencial';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Objetivo visible';
        $this->objective->content = 'Descripción pública';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        for ($index = 0; $index < 40; $index++) {
            $goal = new Goal();
            $goal->title = ($index < 20 ? 'Meta pública ' : 'Meta interna ').$index;
            $goal->status = 'ongoing';
            $goal->visibility = $index < 20 ? 'public' : 'internal';
            $goal->objective()->associate($this->objective);
            $goal->save();
            $this->goals[] = $goal;
        }

        $this->goals[0]->responsibles()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
        $this->goals[0]->reporters()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
        $this->goals[1]->reporters()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
        $this->goals[20]->responsibles()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
        $this->goals[21]->reporters()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
        $this->goals[22]->reporters()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);
    }

    public function test_public_management_and_workspace_audiences_filter_identifiers_and_totals(): void
    {
        $audience = app(GoalAudience::class);

        $this->assertSame(20, $audience->publicQuery()->count());
        $this->assertSame(23, $audience->managementQuery($this->assigned)->count());
        $this->assertSame(5, $audience->workspaceQuery($this->assigned)->count());
        $this->assertSame(40, $audience->managementQuery($this->admin)->count());

        $this->goals[22]->reporters()->detach($this->assigned->id);
        $this->assertSame(22, $audience->managementQuery($this->assigned)->count());
        $this->assertSame(4, $audience->workspaceQuery($this->assigned)->count());
        $this->goals[22]->reporters()->attach($this->assigned->id, ['assigned_by' => $this->admin->id]);

        $publicStats = (new ObjectiveStats())->compute($this->objective);
        $managementStats = (new ObjectiveStats())->compute($this->objective, $this->assigned);
        $this->assertSame(20, $publicStats['goals_total']);
        $this->assertSame(23, $managementStats['goals_total']);

        $this->assertSame(20, (new ObjectiveGoalsExport($this->objective->id))->collection()->count());
        $this->assertSame(23, (new ObjectiveGoalsExport($this->objective->id, $this->assigned))->collection()->count());
        $this->assertSame(40, (new ObjectiveGoalsExport($this->objective->id, $this->admin))->collection()->count());
        $this->assertSame(40, app(AdminDashboardStats::class)->compute(ActivityRange::AllTime)['structure']['goals']);

        $this->actingAs($this->admin)
            ->getJson(route('apiService.home.stats'))
            ->assertOk()
            ->assertJsonPath('data.goals_total', 20);
        $this->actingAs($this->assigned)
            ->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertJsonPath('data.goals_total', 20);
    }

    public function test_internal_goal_report_and_attachment_require_an_assignment(): void
    {
        Storage::fake('reports_private');
        $goal = $this->goals[20];
        $report = $this->createApprovedReport($goal, 'Reporte interno');
        Storage::disk('reports_private')->put('files/evidence.pdf', 'evidence');
        $file = new File();
        $file->disk = 'reports_private';
        $file->name = 'evidence.pdf';
        $file->path = 'files/evidence.pdf';
        $file->size = 8;
        $file->mime = 'application/pdf';
        $report->files()->save($file);

        $this->get(route('goals.index', ['goalId' => $goal->id]))->assertNotFound();
        $this->actingAs($this->outsider)->get(route('goals.index', ['goalId' => $goal->id]))->assertNotFound();
        $this->get(route('reports.index', ['reportId' => $report->id]))->assertNotFound();
        $this->get($file->path)->assertNotFound();
        $this->getJson(route('apiService.goals.reports', ['goalId' => $goal->id]))->assertNotFound();
        $this->getJson(route('apiService.reports', ['goal' => $goal->id]))->assertJsonPath('meta.total', 0);
        $legacyMember = UserFactory::new()->create(['surname' => 'Equipo']);
        $this->objective->members()->attach($legacyMember, ['role' => 'reporter']);
        $this->actingAs($legacyMember)->get(route('goals.index', ['goalId' => $goal->id]))->assertNotFound();

        $this->actingAs($this->assigned)
            ->get(route('goals.index', ['goalId' => $goal->id]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee($goal->title);
        $this->get(route('reports.index', ['reportId' => $report->id]))
            ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get($file->path)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('apiService.goals.management-reports', ['goalId' => $goal->id]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('meta.total', 1);
        $this->getJson(route('apiService.goals.management-reports', ['goalId' => $goal->id, 'type' => 'progress']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->goals[20]->responsibles()->detach($this->assigned->id);
        $this->actingAs($this->assigned)->get(route('goals.index', ['goalId' => $goal->id]))->assertNotFound();
        $this->get(route('reports.index', ['reportId' => $report->id]))->assertNotFound();
        $this->get($file->path)->assertNotFound();
    }

    public function test_public_objective_and_search_results_do_not_include_internal_goals_or_reports(): void
    {
        $internalReport = $this->createApprovedReport($this->goals[20], 'Titulo irrepetible interno');
        $this->createApprovedReport($this->goals[0], 'Reporte público');

        $this->actingAs($this->assigned)
            ->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertDontSee($this->goals[20]->title);
        $this->getJson(route('apiService.objectives.reports', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertJsonMissing(['id' => $internalReport->id]);
        $this->getJson(route('apiService.objectives.fetch', ['objectiveId' => $this->objective->id, 'with' => 'objective_goals']))
            ->assertOk()
            ->assertJsonMissing(['id' => $this->goals[20]->id]);
        $this->getJson(route('apiService.reports', ['s' => 'Titulo irrepetible interno']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_internal_report_comment_notifications_stay_with_assigned_people(): void
    {
        Notification::fake();
        $goal = $this->goals[20];
        $secondAssigned = UserFactory::new()->create(['surname' => 'Asignado dos']);
        $goal->reporters()->attach($secondAssigned->id, ['assigned_by' => $this->admin->id]);
        $legacyMember = UserFactory::new()->create(['surname' => 'Equipo anterior']);
        $this->objective->members()->attach($legacyMember, ['role' => 'reporter']);
        $report = $this->createApprovedReport($goal, 'Reporte con comentarios');

        $this->actingAs($secondAssigned)
            ->postJson(route('apiService.reports.comments.create', ['reportId' => $report->id]), ['content' => 'Comentario asignado'])
            ->assertOk();
        Notification::assertNotSentTo($legacyMember, NewCommentReportForTeamObjective::class);

        $oldComment = new Comment();
        $oldComment->content = 'Comentario de una persona ya no asignada';
        $oldComment->user()->associate($legacyMember);
        $report->comments()->save($oldComment);

        $this->postJson(route('apiService.reports.comments.reply', [
            'reportId' => $report->id,
            'commentId' => $oldComment->id,
        ]), ['content' => 'Respuesta privada'])->assertOk();
        Notification::assertNotSentTo($legacyMember, NewReplyReport::class);
    }

    private function createApprovedReport(Goal $goal, string $title): Report
    {
        $report = new Report();
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido confidencial';
        $report->date = '2026-08-01';
        $report->author()->associate($this->assigned);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }
}
