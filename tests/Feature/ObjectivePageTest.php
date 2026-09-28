<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObjectivePageTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Objective $objective;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = $this->createUser('author@example.com');

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
        $this->objective->tags = ['clima'];
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->author);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();

        $this->goal = $this->createGoal('Plantar árboles', 'ongoing');
    }

    public function test_objective_page_renders_the_hierarchy_in_a_hero_with_the_axis_color(): void
    {
        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('hero--accent', false)
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Eje sostenible', 'Objetivo estratégico verde', 'Reducir emisiones', '#clima'])
            ->assertDontSee('app-portal-header', false);
    }

    public function test_objective_page_lists_reports_with_the_fixed_objective_search(): void
    {
        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('<search-reports', false)
            ->assertSee(':fixed-objective="'.$this->objective->id.'"', false)
            ->assertDontSee('<report-list', false)
            ->assertDontSee('Estados de las metas');
    }

    public function test_subscription_call_to_action_is_not_shown(): void
    {
        $user = $this->createUser('fan@example.com');

        $this->actingAs($user)
            ->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertDontSee('¡Recibí notificaciones del objetivo, las metas y los reportes!');

        $this->actingAs($user)
            ->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertDontSee('¡Recibí notificaciones del objetivo, las metas y los reportes!');
    }

    public function test_goal_page_renders_the_goal_in_a_hero_with_the_axis_color(): void
    {
        $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Eje sostenible', 'Reducir emisiones', 'Meta En progreso', 'Plantar árboles'])
            ->assertDontSee('#clima')
            ->assertDontSee('Nuevo reporte');
    }

    public function test_goal_page_offers_new_report_to_members(): void
    {
        $member = $this->createUser('member@example.com');
        $this->objective->members()->attach($member, ['role' => 'reporter']);

        $this->actingAs($member)
            ->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('Nuevo reporte');
    }

    public function test_hidden_objective_pages_are_not_found_for_the_public(): void
    {
        $this->hideObjective();
        $outsider = $this->createUser('outsider@example.com');

        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->get(route('goals.index', ['goalId' => $this->goal->id]))->assertNotFound();
        $this->actingAs($outsider)->get(route('objectives.index', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.objectives.reports', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.goals.reports', ['goalId' => $this->goal->id]))->assertNotFound();
    }

    public function test_hidden_objective_pages_are_visible_to_members(): void
    {
        $this->hideObjective();
        $member = $this->createUser('member@example.com');
        $this->objective->members()->attach($member, ['role' => 'reporter']);

        $this->actingAs($member)->get(route('objectives.index', ['objectiveId' => $this->objective->id]))->assertOk();
        $this->actingAs($member)->get(route('goals.index', ['goalId' => $this->goal->id]))->assertOk();
        $this->actingAs($member)->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))->assertOk();
    }

    public function test_stats_summarize_goals_reports_and_last_report_date(): void
    {
        $this->createGoal('Meta inactiva', 'inactive');
        $this->createReport('2026-03-10');
        $this->createReport('2026-08-20');

        $this->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Ok',
                'data' => [
                    'goals_total' => 2,
                    'goals_reached' => 0,
                    'goals_ongoing' => 1,
                    'goals_delayed' => 0,
                    'goals_inactive' => 1,
                    'reports_total' => 2,
                    'last_report_date' => '2026-08-20',
                    'traffic_lights' => [
                        'green' => 0,
                        'yellow' => 0,
                        'red' => 0,
                        'measured' => 0,
                        'unmeasured' => 1,
                    ],
                ],
            ]);
    }

    public function test_stats_without_reports_have_no_last_report_date(): void
    {
        $this->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertJsonPath('data.reports_total', 0)
            ->assertJsonPath('data.last_report_date', null);
    }

    private function hideObjective(): void
    {
        $this->objective->hidden = true;
        $this->objective->save();
    }

    private function createGoal(string $title, string $status): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = $status;
        $goal->objective()->associate($this->objective);
        $goal->save();

        return $goal;
    }

    private function createReport(string $date): Report
    {
        $report = new Report();
        $report->title = 'Reporte';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = $date;
        $report->author()->associate($this->author);
        $report->goal()->associate($this->goal);
        $report->save();

        return $report;
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }
}
