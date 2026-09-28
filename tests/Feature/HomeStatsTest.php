<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\Services\Indicators\GoalIndicatorConfigurator;
use App\Services\Indicators\MeasurementMode;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $firstCategory;

    private Category $secondCategory;

    private Category $emptyCategory;

    private Objective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        // Periods starting 2026-01 are overdue unless reported; 2027-01 periods are upcoming.
        $this->travelTo('2026-08-15 10:00:00');

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->firstCategory = $this->createCategory('Eje uno', 1);
        $this->secondCategory = $this->createCategory('Eje dos', 2);
        $this->emptyCategory = $this->createCategory('Eje vacío', 3);

        $this->objective = $this->createObjective($this->firstCategory);
    }

    public function test_it_counts_goals_by_status_and_traffic_light(): void
    {
        $this->seedPortfolio();

        $response = $this->getJson(route('apiService.home.stats'));

        $response->assertOk()
            ->assertJsonPath('data.categories_total', 3)
            ->assertJsonPath('data.strategic_objectives_total', 2)
            ->assertJsonPath('data.objectives_total', 2)
            ->assertJsonPath('data.goals_total', 8)
            ->assertJsonPath('data.goals_reached', 2)
            ->assertJsonPath('data.goals_ongoing', 4)
            ->assertJsonPath('data.goals_delayed', 1)
            ->assertJsonPath('data.goals_inactive', 1)
            ->assertJsonPath('data.traffic_lights', [
                'green' => 1,
                'yellow' => 1,
                'red' => 1,
                'measured' => 3,
                'unmeasured' => 4,
            ])
            ->assertJsonMissingPath('data.reports_total')
            ->assertJsonMissingPath('data.reports_data');
    }

    public function test_it_breaks_down_progress_by_category(): void
    {
        $this->seedPortfolio();

        $categories = collect($this->getJson(route('apiService.home.stats'))->json('data.categories'))->keyBy('id');

        $this->assertSame(
            ['goals_total' => 7, 'goals_reached' => 1, 'measured' => 3, 'green' => 1],
            collect($categories[$this->firstCategory->id])->only(['goals_total', 'goals_reached', 'measured', 'green'])->all(),
        );
        $this->assertSame(
            ['goals_total' => 1, 'goals_reached' => 1, 'measured' => 0, 'green' => 0],
            collect($categories[$this->secondCategory->id])->only(['goals_total', 'goals_reached', 'measured', 'green'])->all(),
        );
        $this->assertSame(
            ['goals_total' => 0, 'goals_reached' => 0, 'measured' => 0, 'green' => 0],
            collect($categories[$this->emptyCategory->id])->only(['goals_total', 'goals_reached', 'measured', 'green'])->all(),
        );
        $this->assertSame([$this->firstCategory->id, $this->secondCategory->id, $this->emptyCategory->id], $categories->keys()->all());
    }

    public function test_it_counts_strategic_and_specific_objectives_by_category_skipping_hidden_ones(): void
    {
        $hiddenObjective = $this->createObjective($this->firstCategory);
        $hiddenObjective->hidden = true;
        $hiddenObjective->save();
        $this->createGoal($hiddenObjective, 'ongoing');
        $this->createGoal($this->objective, 'ongoing');
        $this->createGoal($this->objective, 'reached');

        $categories = collect($this->getJson(route('apiService.home.stats'))->json('data.categories'))->keyBy('id');

        $this->assertSame(
            ['strategic_objectives_count' => 2, 'objectives_count' => 1, 'goals_total' => 2],
            collect($categories[$this->firstCategory->id])->only(['strategic_objectives_count', 'objectives_count', 'goals_total'])->all(),
        );
        $this->assertSame(
            ['strategic_objectives_count' => 0, 'objectives_count' => 0, 'goals_total' => 0],
            collect($categories[$this->emptyCategory->id])->only(['strategic_objectives_count', 'objectives_count', 'goals_total'])->all(),
        );
    }

    public function test_inactive_goals_are_left_out_of_traffic_lights(): void
    {
        $this->createPeriodicGoal($this->objective, 'inactive');

        $this->getJson(route('apiService.home.stats'))
            ->assertOk()
            ->assertJsonPath('data.goals_inactive', 1)
            ->assertJsonPath('data.traffic_lights.red', 0)
            ->assertJsonPath('data.traffic_lights.measured', 0)
            ->assertJsonPath('data.traffic_lights.unmeasured', 0);
    }

    public function test_goals_of_hidden_objectives_are_ignored(): void
    {
        $hiddenObjective = $this->createObjective($this->firstCategory);
        $hiddenObjective->hidden = true;
        $hiddenObjective->save();
        $this->createPeriodicGoal($hiddenObjective, 'ongoing');
        $this->createGoal($hiddenObjective, 'reached');

        $this->getJson(route('apiService.home.stats'))
            ->assertOk()
            ->assertJsonPath('data.objectives_total', 1)
            ->assertJsonPath('data.goals_total', 0)
            ->assertJsonPath('data.traffic_lights.measured', 0)
            ->assertJsonPath('data.categories.0.goals_total', 0);
    }

    public function test_stats_are_cached(): void
    {
        $this->getJson(route('apiService.home.stats'))->assertJsonPath('data.goals_total', 0);

        $this->createGoal($this->objective, 'ongoing');

        $this->getJson(route('apiService.home.stats'))->assertJsonPath('data.goals_total', 0);

        $this->travel(11)->minutes();

        $this->getJson(route('apiService.home.stats'))->assertJsonPath('data.goals_total', 1);
    }

    public function test_the_home_page_renders_the_stats_component(): void
    {
        $this->withoutVite();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('portal-home-stats', false)
            ->assertDontSee('show-reports-graph', false);
    }

    /**
     * Seven goals in the first category and one in the second, covering every status and traffic light.
     */
    private function seedPortfolio(): void
    {
        $this->createProgressReport($this->createPeriodicGoal($this->objective, 'ongoing'), 20);
        $this->createProgressReport($this->createPeriodicGoal($this->objective, 'ongoing'), 29);
        $this->createPeriodicGoal($this->objective, 'delayed');
        $this->createPeriodicGoal($this->objective, 'inactive');
        $this->createPeriodicGoal($this->objective, 'ongoing', '2027-01');
        $this->createGoal($this->objective, 'reached');

        $simple = $this->createGoal($this->objective, 'ongoing', save: false);
        $simple->measurement_mode = MeasurementMode::Simple;
        $simple->indicator = 'Personas';
        $simple->indicator_unit = 'personas';
        $simple->indicator_goal = 100;
        $simple->indicator_progress = 40;
        $simple->save();

        $this->createGoal($this->createObjective($this->secondCategory), 'reached');
    }

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'fas fa-circle';
        $category->color = '#000000';
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createObjective(Category $category): Objective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = "OE-{$category->order}";
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = 'Objetivo';
        $objective->content = 'Descripción';
        $objective->hidden = false;
        $objective->author()->associate($this->author);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective, string $status, bool $save = true): Goal
    {
        $goal = new Goal();
        $goal->title = 'Meta';
        $goal->status = $status;
        $goal->objective()->associate($objective);

        if ($save) {
            $goal->save();
        }

        return $goal;
    }

    /**
     * A single quarterly lower-is-better period with a target of 27.
     */
    private function createPeriodicGoal(Objective $objective, string $status, string $periodStart = '2026-01'): Goal
    {
        $goal = $this->createGoal($objective, $status, save: false);

        app(GoalIndicatorConfigurator::class)->apply($goal, [
            'measurement_mode' => 'periodic',
            'indicator' => 'Días promedio de emisión',
            'indicator_unit' => 'días',
            'indicator_direction' => 'lower_is_better',
            'indicator_nature' => 'intensive',
            'period_type' => 'quarterly',
            'period_start' => $periodStart,
            'period_count' => 1,
            'period_targets' => [1 => 27],
        ]);

        return $goal->load('periods');
    }

    private function createProgressReport(Goal $goal, float $value): void
    {
        $report = new Report();
        $report->title = 'Avance';
        $report->type = 'progress';
        $report->content = 'Contenido';
        $report->date = '2026-04-05';
        $report->measured_value = $value;
        $report->author()->associate($this->author);
        $report->goal()->associate($goal);
        $report->period()->associate($goal->periods->first());
        $report->save();
    }
}
