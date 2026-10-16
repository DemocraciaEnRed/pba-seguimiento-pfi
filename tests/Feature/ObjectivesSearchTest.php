<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObjectivesSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_hidden_objectives_are_excluded(): void
    {
        $category = $this->createCategory('Eje', 1);
        $strategicObjective = $this->createStrategicObjective($category, 'OE.1.1');
        $visible = $this->createObjective($strategicObjective, 'Visible');
        $this->createObjective($strategicObjective, 'Oculto', hidden: true);

        $this->assertSame([$visible->id], $this->fetchIds());
    }

    public function test_filters_by_category_and_strategic_objective(): void
    {
        $firstCategory = $this->createCategory('Eje 1', 1);
        $secondCategory = $this->createCategory('Eje 2', 2);
        $firstStrategicObjective = $this->createStrategicObjective($firstCategory, 'OE.1.1');
        $secondStrategicObjective = $this->createStrategicObjective($firstCategory, 'OE.1.2');
        $thirdStrategicObjective = $this->createStrategicObjective($secondCategory, 'OE.2.1');
        $first = $this->createObjective($firstStrategicObjective, 'Primero');
        $second = $this->createObjective($secondStrategicObjective, 'Segundo');
        $this->createObjective($thirdStrategicObjective, 'Tercero');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->fetchIds(['category' => $firstCategory->id]));
        $this->assertSame([$second->id], $this->fetchIds(['strategic_objective' => $secondStrategicObjective->id]));
        $this->assertSame([], $this->fetchIds(['category' => $secondCategory->id, 'strategic_objective' => $firstStrategicObjective->id]));
    }

    public function test_sorts_by_title_last_report_reports_and_reached_goals(): void
    {
        $strategicObjective = $this->createStrategicObjective($this->createCategory('Eje', 1), 'OE.1.1');
        $alpha = $this->createObjective($strategicObjective, 'Alfa');
        $beta = $this->createObjective($strategicObjective, 'Beta');
        $gamma = $this->createObjective($strategicObjective, 'Gamma');

        $alphaGoal = $this->createGoal($alpha, 'reached');
        $this->createGoal($alpha, 'reached');
        $this->createReport($alphaGoal, '2026-01-10');

        $betaGoal = $this->createGoal($beta, 'reached');
        $this->createReport($betaGoal, '2026-03-10');
        $this->createReport($betaGoal, '2026-02-10');
        $this->createReport($betaGoal, '2026-02-01');

        $gammaGoal = $this->createGoal($gamma, 'ongoing');
        $this->createReport($gammaGoal, '2026-02-15');
        $this->createReport($gammaGoal, '2026-02-16');

        $this->assertSame([$alpha->id, $beta->id, $gamma->id], $this->fetchIds(['sort' => 'title']));
        $this->assertSame([$beta->id, $gamma->id, $alpha->id], $this->fetchIds(['sort' => 'last_report']));
        $this->assertSame([$beta->id, $gamma->id, $alpha->id], $this->fetchIds(['sort' => 'most_reports']));
        $this->assertSame([$alpha->id, $beta->id, $gamma->id], $this->fetchIds(['sort' => 'most_reached']));
    }

    public function test_stats_come_from_preloaded_aggregates(): void
    {
        $this->travelTo('2026-05-10 12:00:00');
        $strategicObjective = $this->createStrategicObjective($this->createCategory('Eje', 1), 'OE.1.1');
        $objective = $this->createObjective($strategicObjective, 'Objetivo');
        $reachedGoal = $this->createGoal($objective, 'reached');
        $this->createGoal($objective, 'delayed');
        $this->createReport($reachedGoal, '2026-05-07');

        $this->getJson(route('apiService.objectives', ['with' => 'objective_stats,objective_strategic_objective']))
            ->assertOk()
            ->assertJsonPath('data.0.goals_count', 2)
            ->assertJsonPath('data.0.reports_count', 1)
            ->assertJsonPath('data.0.goals_status.reached', 1)
            ->assertJsonPath('data.0.goals_status.delayed', 1)
            ->assertJsonPath('data.0.goals_status.ongoing', 0)
            ->assertJsonPath('data.0.last_report_when', 'hace 3 días')
            ->assertJsonPath('data.0.strategic_objective.codigo', 'OE.1.1');
    }

    public function test_stats_do_not_query_per_objective(): void
    {
        $strategicObjective = $this->createStrategicObjective($this->createCategory('Eje', 1), 'OE.1.1');
        foreach (range(1, 3) as $index) {
            $this->createReport($this->createGoal($this->createObjective($strategicObjective, "Objetivo {$index}"), 'ongoing'), '2026-01-01');
        }

        $queryCount = fn (): int => $this->countQueries(fn () => $this->getJson(route('apiService.objectives', ['with' => 'objective_stats,objective_strategic_objective']))->assertOk());
        $baseline = $queryCount();

        $this->createReport($this->createGoal($this->createObjective($strategicObjective, 'Objetivo 4'), 'reached'), '2026-01-01');

        $this->assertSame($baseline, $queryCount());
    }

    public function test_legacy_order_by_is_whitelisted(): void
    {
        $strategicObjective = $this->createStrategicObjective($this->createCategory('Eje', 1), 'OE.1.1');
        $this->createObjective($strategicObjective, 'Objetivo');

        $this->getJson(route('apiService.objectives', ['order_by' => 'updated_at,DESC']))->assertOk();
        $this->getJson(route('apiService.objectives', ['order_by' => 'password,desc']))->assertUnprocessable();
        $this->getJson(route('apiService.objectives', ['order_by' => 'title,sideways']))->assertUnprocessable();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson(route('apiService.objectives', ['sort' => 'random']))->assertUnprocessable();
        $this->getJson(route('apiService.objectives', ['category' => 'abc']))->assertUnprocessable();
        $this->getJson(route('apiService.objectives', ['strategic_objective' => 'abc']))->assertUnprocessable();
    }

    public function test_list_page_exposes_strategic_objectives_per_category(): void
    {
        $category = $this->createCategory('Eje', 1);
        $this->createStrategicObjective($category, 'OE.1.2');
        $this->createStrategicObjective($category, 'OE.1.1');

        $this->withoutVite()
            ->get(route('objectives'))
            ->assertOk()
            ->assertSee('search-objectives', false)
            ->assertSeeInOrder(['OE.1.1', 'OE.1.2']);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, int>
     */
    private function fetchIds(array $query = []): array
    {
        return $this->getJson(route('apiService.objectives', $query))
            ->assertOk()
            ->json('data.*.id');
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function createCategory(string $title, int $order): Category
    {
        $category = new Category();
        $category->title = $title;
        $category->icon = 'observatorio-integridad';
        $category->color = '#123456';
        $category->order = $order;
        $category->save();

        return $category;
    }

    private function createStrategicObjective(Category $category, string $codigo): StrategicObjective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = $codigo;
        $strategicObjective->title = "Objetivo estratégico {$codigo}";
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        return $strategicObjective;
    }

    private function createObjective(StrategicObjective $strategicObjective, string $title, bool $hidden = false): Objective
    {
        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->author);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective, string $status): Goal
    {
        $goal = new Goal();
        $goal->title = 'Meta';
        $goal->status = $status;
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    private function createReport(Goal $goal, string $date): Report
    {
        $report = new Report();
        $report->title = 'Reporte';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = $date;
        $report->author()->associate($this->author);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }
}
