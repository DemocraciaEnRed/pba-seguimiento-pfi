<?php

namespace Tests\Feature;

use App\Category;
use App\Comment;
use App\Goal;
use App\Objective;
use App\Report;
use App\StrategicObjective;
use App\Testimony;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $firstCategory;

    private Category $secondCategory;

    private Goal $firstGoal;

    private Goal $secondGoal;

    private Goal $hiddenGoal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-28 12:00:00');

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->firstCategory = $this->createCategory('Eje uno', 1);
        $this->secondCategory = $this->createCategory('Eje dos', 2);
        $this->firstGoal = $this->createGoal($this->createObjective($this->firstCategory, 'Objetivo uno'));
        $this->secondGoal = $this->createGoal($this->createObjective($this->secondCategory, 'Objetivo dos'));
        $this->hiddenGoal = $this->createGoal($this->createObjective($this->firstCategory, 'Objetivo oculto', hidden: true));
    }

    public function test_reports_of_hidden_objectives_are_not_listed(): void
    {
        $visible = $this->createReport($this->firstGoal);
        $this->createReport($this->hiddenGoal);

        $this->assertSame([$visible->id], $this->fetchIds());
    }

    public function test_filters_by_category(): void
    {
        $first = $this->createReport($this->firstGoal);
        $this->createReport($this->secondGoal);

        $this->assertSame([$first->id], $this->fetchIds(['category' => $this->firstCategory->id]));
    }

    public function test_filters_by_objective(): void
    {
        $this->createReport($this->firstGoal);
        $second = $this->createReport($this->secondGoal);

        $this->assertSame([$second->id], $this->fetchIds(['objective' => $this->secondGoal->objective_id]));
    }

    public function test_members_see_reports_of_their_hidden_objective_when_filtering_by_it(): void
    {
        $hidden = $this->createReport($this->hiddenGoal);
        $this->createReport($this->firstGoal);
        $this->hiddenGoal->objective->members()->attach($this->author, ['role' => 'reporter']);

        $this->assertSame([], $this->fetchIds(['objective' => $this->hiddenGoal->objective_id]));

        $this->actingAs($this->author);
        $this->assertSame([$hidden->id], $this->fetchIds(['objective' => $this->hiddenGoal->objective_id]));
        $this->assertNotContains($hidden->id, $this->fetchIds());
    }

    public function test_filters_by_date_range(): void
    {
        $lastWeek = $this->createReport($this->firstGoal, ['date' => '2026-09-21']);
        $twoMonthsAgo = $this->createReport($this->firstGoal, ['date' => '2026-07-28']);
        $eightMonthsAgo = $this->createReport($this->firstGoal, ['date' => '2026-01-28']);
        $this->createReport($this->firstGoal, ['date' => '2025-06-01']);

        $this->assertSame([$lastWeek->id], $this->fetchIds(['date_range' => 'last_30_days', 'sort' => 'recent']));
        $this->assertSame([$lastWeek->id, $twoMonthsAgo->id], $this->fetchIds(['date_range' => 'last_3_months', 'sort' => 'recent']));
        $this->assertSame([$lastWeek->id, $twoMonthsAgo->id, $eightMonthsAgo->id], $this->fetchIds(['date_range' => 'last_year', 'sort' => 'recent']));
    }

    public function test_sorts_by_report_date(): void
    {
        $older = $this->createReport($this->firstGoal, ['date' => '2026-05-01']);
        $newer = $this->createReport($this->firstGoal, ['date' => '2026-09-01']);

        $this->assertSame([$newer->id, $older->id], $this->fetchIds(['sort' => 'recent']));
        $this->assertSame([$older->id, $newer->id], $this->fetchIds(['sort' => 'oldest']));
    }

    public function test_sorts_by_most_commented(): void
    {
        $quiet = $this->createReport($this->firstGoal);
        $popular = $this->createReport($this->firstGoal);
        $this->addComment($popular);
        $this->addComment($popular);
        $this->addComment($quiet);

        $this->assertSame([$popular->id, $quiet->id], $this->fetchIds(['sort' => 'most_commented']));
    }

    public function test_sorts_by_most_liked(): void
    {
        $liked = $this->createReport($this->firstGoal);
        $this->createReport($this->firstGoal);
        $this->addLike($liked);

        $this->assertSame($liked->id, $this->fetchIds(['sort' => 'most_liked'])[0]);
    }

    public function test_rejects_unknown_filter_values(): void
    {
        $this->getJson(route('apiService.reports', ['sort' => 'title']))->assertUnprocessable();
        $this->getJson(route('apiService.reports', ['date_range' => 'forever']))->assertUnprocessable();
        $this->getJson(route('apiService.reports', ['category' => 'abc']))->assertUnprocessable();
    }

    public function test_page_offers_categories_with_their_visible_objectives(): void
    {
        $this->get(route('reports'))
            ->assertOk()
            ->assertViewHas('categories', fn (Collection $categories): bool => $categories->pluck('title')->all() === ['Eje uno', 'Eje dos']
                && $categories[0]['objectives']->pluck('title')->all() === ['Objetivo uno']
                && $categories[1]['objectives']->pluck('title')->all() === ['Objetivo dos']);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function fetchIds(array $query = []): array
    {
        return $this->getJson(route('apiService.reports', $query))
            ->assertOk()
            ->json('data.*.id');
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

    private function createObjective(Category $category, string $title, bool $hidden = false): Objective
    {
        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = "OE{$category->order}";
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = $hidden;
        $objective->author()->associate($this->author);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective): Goal
    {
        $goal = new Goal();
        $goal->title = 'Meta';
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createReport(Goal $goal, array $attributes = []): Report
    {
        $report = new Report();
        $report->title = 'Reporte';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = '2026-09-01';
        $report->forceFill($attributes);
        $report->author()->associate($this->author);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }

    private function addComment(Report $report): void
    {
        $comment = new Comment();
        $comment->content = 'Comentario';
        $comment->user()->associate($this->author);
        $comment->commentable()->associate($report);
        $comment->save();
    }

    private function addLike(Report $report): void
    {
        $testimony = new Testimony();
        $testimony->value = true;
        $testimony->user_id = $this->author->id;
        $testimony->report_id = $report->id;
        $testimony->save();
    }
}
