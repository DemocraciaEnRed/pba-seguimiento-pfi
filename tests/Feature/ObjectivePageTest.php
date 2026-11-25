<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Milestone;
use App\Objective;
use App\Organization;
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

    public function test_objective_page_uses_full_width_sections_and_keeps_the_menu_only_on_goal_pages(): void
    {
        $organization = new Organization();
        $organization->name = 'Organización participante';
        $organization->description = 'Trabaja en las metas del objetivo.';
        $organization->save();
        $this->goal->participantOrganizations()->attach($organization);

        $html = $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSeeInOrder(['Acerca del objetivo general', 'Indicadores claves', 'id="objective-goals"', 'id="objective-organizations"', 'id="reportes"'], false)
            ->assertSee('Organización participante')
            ->assertDontSee('objective-menu', false)
            ->assertDontSee('col-md-8', false)
            ->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//ul[@id="objective-goals"]/li/article')->length);
        $this->assertSame(1, $xpath->query('//ul[@id="objective-organizations"]/li/article')->length);
        $this->assertSame(1, $xpath->query('//ul[@id="objective-organizations"]/ancestor::section[1]/following-sibling::*[1][self::section and @id="reportes"]')->length);

        $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('objective-menu', false);
    }

    public function test_goal_cards_show_indicators_and_only_the_latest_published_report_per_goal(): void
    {
        $this->goal->measurement_mode = \App\Services\Indicators\MeasurementMode::Simple;
        $this->goal->indicator = 'Árboles plantados';
        $this->goal->indicator_unit = 'árboles';
        $this->goal->indicator_goal = 200;
        $this->goal->indicator_progress = 50;
        $this->goal->save();
        $older = $this->createReport('2026-01-10');
        $older->title = 'Reporte anterior';
        $older->save();
        $latest = $this->createReport('2026-03-10');
        $latest->title = 'Último reporte visible';
        $latest->save();
        $pending = $this->createReport('2026-04-10');
        $pending->title = 'Propuesta privada';
        $pending->review_status = \App\Services\Reports\ReviewStatus::Pending;
        $pending->save();
        $secondGoal = $this->createGoal('Segunda meta', 'reached');
        $secondReport = $this->createReport('2026-02-10');
        $secondReport->title = 'Reporte de segunda meta';
        $secondReport->goal()->associate($secondGoal);
        $secondReport->save();
        $internalGoal = $this->createGoal('Meta interna secreta', 'ongoing');
        $internalGoal->visibility = 'internal';
        $internalGoal->save();

        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('En progreso')
            ->assertSee('Alcanzada')
            ->assertSee('Árboles plantados')
            ->assertSee('50 / 200')
            ->assertSee('25%')
            ->assertSee('2 reportes publicados')
            ->assertSee('Último reporte visible')
            ->assertSee('Reporte de segunda meta')
            ->assertDontSee('Reporte anterior')
            ->assertDontSee('Propuesta privada')
            ->assertDontSee('Meta interna secreta');
    }

    public function test_objective_without_goals_shows_an_empty_state(): void
    {
        $this->goal->delete();

        $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('No hay metas disponibles.')
            ->assertDontSee('id="objective-goals"', false)
            ->assertDontSee('id="objective-organizations"', false)
            ->assertSee('<search-reports', false);
    }

    public function test_periodic_goal_cards_show_measured_compliance_and_missing_measurements(): void
    {
        $this->travelTo('2026-02-15 10:00:00');
        $settings = [
            'measurement_mode' => 'periodic',
            'indicator' => 'Personas capacitadas',
            'indicator_unit' => 'personas',
            'indicator_direction' => 'higher_is_better',
            'indicator_nature' => 'extensive',
            'period_type' => 'monthly',
            'period_start' => '2026-01',
            'period_count' => 1,
            'period_targets' => [1 => 100],
        ];
        $configurator = app(\App\Services\Indicators\GoalIndicatorConfigurator::class);
        $configurator->apply($this->goal, $settings);
        $unmeasured = $this->createGoal('Meta sin mediciones', 'ongoing');
        $configurator->apply($unmeasured, array_replace($settings, ['period_start' => '2026-02']));
        $report = $this->createReport('2026-01-31');
        $report->type = 'progress';
        $report->measured_value = 95;
        $report->period()->associate($this->goal->periods()->firstOrFail());
        $report->save();

        $response = $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('Cumplimiento a la fecha')
            ->assertSee('95%')
            ->assertSee('Levemente por debajo')
            ->assertSee('95 / 100')
            ->assertSee('Meta sin mediciones')
            ->assertSee('Sin medición');

        foreach ($response->viewData('objective')->goals as $goal) {
            $this->assertTrue($goal->relationLoaded('periods'));
            foreach ($goal->periods as $period) {
                $this->assertTrue($period->relationLoaded('progressReport'));
            }
        }
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

    public function test_hidden_objectives_cannot_be_subscribed_to_by_identifier(): void
    {
        $this->hideObjective();
        $user = $this->createUser('subscriber@example.com');

        $this->actingAs($user)
            ->post(route('objectives.subscribers.form', ['objectiveId' => $this->objective->id]))
            ->assertNotFound();

        $this->assertSame(0, $this->objective->subscribers()->count());
    }

    public function test_goal_page_renders_the_goal_in_a_hero_with_the_axis_color(): void
    {
        $html = $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee('--hero-accent: #12a24f', false)
            ->assertSeeInOrder(['Acerca de la meta', 'Estado declarado', 'En progreso', 'Modo de seguimiento'])
            ->assertSee('Esta meta se sigue mediante novedades e hitos, sin un indicador numérico.')
            ->assertDontSee('#clima')
            ->assertDontSee('Nuevo reporte')
            ->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $hero = $xpath->query('//section[contains(@class, "goal-hero")]')->item(0);
        $about = $xpath->query('//section[@aria-labelledby="goal-about"]')->item(0);
        $this->assertStringContainsString('Eje sostenible', $hero->textContent);
        $this->assertStringContainsString('Plantar árboles', $hero->textContent);
        $this->assertStringNotContainsString('En progreso', $hero->textContent);
        $this->assertStringNotContainsString('Reducir emisiones', $hero->textContent);
        $this->assertStringNotContainsString('Objetivo estratégico verde', $hero->textContent);
        $this->assertStringContainsString('Reducir emisiones', $about->textContent);
        $this->assertStringContainsString('Objetivo estratégico verde', $about->textContent);

        foreach (['goal-about', 'goal-indicator'] as $sectionId) {
            $heading = $xpath->query('//section[@aria-labelledby="'.$sectionId.'"]/h2[@id="'.$sectionId.'"]')->item(0);
            $this->assertNotNull($heading, "The {$sectionId} title must sit outside the cards.");
            $this->assertSame('h3 is-700 mb-3', $heading->getAttribute('class'));
        }

        $aboutFactCards = $xpath->query('//section[@aria-labelledby="goal-about"]/div[@class="row"]//div[contains(@class, "card")]//h3');
        $this->assertSame(['Estado declarado', 'Modo de seguimiento'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($aboutFactCards)));
    }

    public function test_goal_body_preserves_the_simple_indicator_and_source(): void
    {
        $this->goal->measurement_mode = \App\Services\Indicators\MeasurementMode::Simple;
        $this->goal->indicator = 'Árboles plantados';
        $this->goal->indicator_unit = 'árboles';
        $this->goal->indicator_frequency = 'Mensual';
        $this->goal->indicator_goal = 200;
        $this->goal->indicator_progress = 50;
        $this->goal->source = 'Registro municipal';
        $this->goal->save();

        $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSeeInOrder(['Acerca de la meta', 'Valor acumulado', 'Indicador y seguimiento', 'Árboles plantados'])
            ->assertSee('25%')
            ->assertSee('Mensual')
            ->assertSee('Registro municipal')
            ->assertSee(':fixed-goal="'.$this->goal->id.'"', false)
            ->assertDontSee('Esta meta se sigue mediante novedades e hitos');
    }

    public function test_completed_milestones_only_link_to_available_consolidated_reports(): void
    {
        $milestones = [];
        foreach (['Hito sin reporte', 'Hito con propuesta privada', 'Hito publicado'] as $order => $title) {
            $milestone = new Milestone();
            $milestone->title = $title;
            $milestone->order = $order + 1;
            $milestone->completed = '2026-10-10';
            $milestone->goal()->associate($this->goal);
            $milestone->save();
            $milestones[] = $milestone;
        }
        $pending = $this->createReport('2026-10-10');
        $pending->type = 'milestone';
        $pending->review_status = \App\Services\Reports\ReviewStatus::Pending;
        $pending->milestone()->associate($milestones[1]);
        $pending->save();
        $published = $this->createReport('2026-10-10');
        $published->type = 'milestone';
        $published->milestone()->associate($milestones[2]);
        $published->save();

        $html = $this->get(route('goals.index', ['goalId' => $this->goal->id]))
            ->assertOk()
            ->assertSee(['Hito sin reporte', 'Hito con propuesta privada', 'Hito publicado'])
            ->assertSee(route('reports.index', ['reportId' => $published->id]), false)
            ->assertDontSee(route('reports.index', ['reportId' => $pending->id]), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Ver el reporte'));
    }

    public function test_goal_page_offers_new_report_to_assigned_reporters(): void
    {
        $member = $this->createUser('member@example.com');
        $this->goal->reporters()->attach($member);

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
        $this->actingAs($outsider)->getJson(route('apiService.objectives.fetch', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.objectives.reports', ['objectiveId' => $this->objective->id]))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('apiService.goals.reports', ['goalId' => $this->goal->id]))->assertNotFound();
    }

    public function test_hidden_objective_pages_are_visible_to_assigned_users(): void
    {
        $this->hideObjective();
        $assigned = $this->createUser('assigned@example.com');
        $this->goal->reporters()->attach($assigned->id, ['assigned_by' => $this->author->id]);

        $this->actingAs($assigned)->get(route('objectives.index', ['objectiveId' => $this->objective->id]))->assertOk();
        $this->actingAs($assigned)->get(route('goals.index', ['goalId' => $this->goal->id]))->assertOk();
        $this->actingAs($assigned)->getJson(route('apiService.objectives.stats', ['objectiveId' => $this->objective->id]))->assertOk();
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
        $report->review_status = \App\Services\Reports\ReviewStatus::Approved;
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
