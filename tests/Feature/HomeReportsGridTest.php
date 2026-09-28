<?php

namespace Tests\Feature;

use App\Category;
use App\Comment;
use App\Goal;
use App\GoalPeriod;
use App\ImageFile;
use App\Milestone;
use App\Objective;
use App\Report;
use App\Services\Indicators\MeasurementMode;
use App\StrategicObjective;
use App\Testimony;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeReportsGridTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Goal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'observatorio-integridad';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE1';
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

        $this->goal = new Goal();
        $this->goal->title = 'Meta';
        $this->goal->status = 'ongoing';
        $this->goal->objective()->associate($objective);
        $this->goal->save();
    }

    public function test_cover_is_the_first_photo_of_the_report(): void
    {
        $report = $this->createReport();
        $this->attachPhoto($report, 'first');
        $this->attachPhoto($report, 'second');

        $this->getJson(route('apiService.reports', ['with' => 'report_cover']))
            ->assertOk()
            ->assertJsonPath('data.0.cover', [
                'thumbnail_url' => asset('/storage/reports/photos/first-thumbnail.jpg'),
                'url' => asset('/storage/reports/photos/first.jpg'),
            ]);
    }

    public function test_cover_is_null_without_photos(): void
    {
        $this->createReport();

        $this->getJson(route('apiService.reports', ['with' => 'report_cover']))
            ->assertOk()
            ->assertJsonPath('data.0.cover', null);
    }

    public function test_excerpt_strips_html_and_is_truncated(): void
    {
        $this->createReport(['content' => '<p>Primer párrafo</p><p>'.str_repeat('palabra ', 40).'</p>']);

        $excerpt = $this->getJson(route('apiService.reports', ['with' => 'report_excerpt']))
            ->assertOk()
            ->json('data.0.excerpt');

        $this->assertStringStartsWith('Primer párrafo palabra', $excerpt);
        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertStringEndsWith('...', $excerpt);
        $this->assertLessThanOrEqual(163, mb_strlen($excerpt));
    }

    public function test_highlights_show_the_simple_indicator_change(): void
    {
        $this->goal->measurement_mode = MeasurementMode::Simple;
        $this->goal->indicator_unit = 'personas';
        $this->goal->save();
        $this->createReport(['type' => 'progress', 'previous_progress' => 45, 'progress' => 15.5]);

        $this->getJson(route('apiService.reports', ['with' => 'report_highlights']))
            ->assertOk()
            ->assertJsonPath('data.0.highlights.indicator', [
                'kind' => 'simple',
                'from' => '45',
                'to' => '60,5',
                'increment' => '+15,5',
                'is_decrease' => false,
                'unit' => 'personas',
            ]);
    }

    public function test_highlights_show_the_periodic_measurement(): void
    {
        $this->goal->measurement_mode = MeasurementMode::Periodic;
        $this->goal->indicator_unit = 'días';
        $this->goal->save();
        $period = new GoalPeriod(['number' => 2, 'starts_on' => '2026-04-01', 'ends_on' => '2026-06-30', 'target_value' => 27]);
        $this->goal->periods()->save($period);
        $this->createReport(['type' => 'progress', 'goal_period_id' => $period->id, 'measured_value' => 25]);

        $this->getJson(route('apiService.reports', ['with' => 'report_highlights']))
            ->assertOk()
            ->assertJsonPath('data.0.highlights.indicator', [
                'kind' => 'periodic',
                'period_label' => 'Período 2',
                'measured_value' => '25',
                'unit' => 'días',
            ]);
    }

    public function test_highlights_show_the_status_change(): void
    {
        $this->createReport(['previous_status' => 'ongoing', 'status' => 'reached']);

        $this->getJson(route('apiService.reports', ['with' => 'report_highlights']))
            ->assertOk()
            ->assertJsonPath('data.0.highlights.status_change', [
                'from' => 'ongoing',
                'from_label' => 'En progreso',
                'to' => 'reached',
                'to_label' => 'Alcanzada',
            ])
            ->assertJsonPath('data.0.highlights.indicator', null)
            ->assertJsonPath('data.0.highlights.milestone', null);
    }

    public function test_highlights_show_the_achieved_milestone(): void
    {
        $milestone = new Milestone();
        $milestone->title = 'Licitación adjudicada';
        $milestone->order = 3;
        $milestone->goal()->associate($this->goal);
        $milestone->save();
        $this->createReport(['type' => 'milestone', 'milestone_achieved' => $milestone->id]);

        $this->getJson(route('apiService.reports', ['with' => 'report_highlights']))
            ->assertOk()
            ->assertJsonPath('data.0.highlights.milestone', ['order' => 3, 'title' => 'Licitación adjudicada'])
            ->assertJsonPath('data.0.highlights.status_change', null);
    }

    public function test_new_fields_are_omitted_by_default(): void
    {
        $this->createReport();

        $this->getJson(route('apiService.reports'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.cover')
            ->assertJsonMissingPath('data.0.excerpt')
            ->assertJsonMissingPath('data.0.highlights');
    }

    public function test_counts_are_preloaded_instead_of_queried_per_report(): void
    {
        $report = $this->createReport();
        $this->createReport();
        $comment = $this->addComment($report);
        $this->addComment($report, $comment);
        $this->addTestimony($report, true);
        $this->addTestimony($report, false);

        DB::enableQueryLog();
        $response = $this->getJson(route('apiService.reports', ['order_by' => 'id,ASC']));
        $reportQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'comments') || str_contains($query['query'], 'testimonies'));

        $response->assertOk()
            ->assertJsonPath('data.0.comments_count', 1)
            ->assertJsonPath('data.0.positive_testimonies_count', 1)
            ->assertJsonPath('data.0.negative_testimonies_count', 1)
            ->assertJsonPath('data.1.comments_count', 0);
        $this->assertCount(1, $reportQueries);
    }

    public function test_objective_timeline_returns_the_tile_fields_with_preloaded_counts(): void
    {
        $report = $this->createReport(['content' => '<p>Avance de la obra</p>']);
        $this->attachPhoto($report, 'first');
        $this->addComment($report);
        $this->createReport();

        DB::enableQueryLog();
        $response = $this->getJson(route('apiService.objectives.reports', [
            'objectiveId' => $this->goal->objective_id,
            'with' => 'report_goal,report_hierarchy,report_cover,report_excerpt,report_highlights',
            'order_by' => 'id,ASC',
        ]));
        $countQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'comments'));

        $response->assertOk()
            ->assertJsonPath('data.0.goal.title', 'Meta')
            ->assertJsonPath('data.0.hierarchy.category.title', 'Eje')
            ->assertJsonPath('data.0.cover.url', asset('/storage/reports/photos/first.jpg'))
            ->assertJsonPath('data.0.excerpt', 'Avance de la obra')
            ->assertJsonPath('data.0.highlights.milestone', null)
            ->assertJsonPath('data.0.comments_count', 1)
            ->assertJsonPath('data.1.cover', null)
            ->assertJsonMissingPath('data.0.latest_comments');
        $this->assertCount(1, $countQueries);
    }

    public function test_goal_timeline_returns_the_tile_fields_with_preloaded_counts(): void
    {
        $report = $this->createReport(['content' => '<p>Avance de la obra</p>']);
        $this->attachPhoto($report, 'first');
        $this->addComment($report);
        $this->createReport();

        DB::enableQueryLog();
        $response = $this->getJson(route('apiService.goals.reports', [
            'goalId' => $this->goal->id,
            'with' => 'report_hierarchy,report_cover,report_excerpt,report_highlights',
            'order_by' => 'id,ASC',
        ]));
        $countQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'comments'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.hierarchy.category.title', 'Eje')
            ->assertJsonPath('data.0.cover.url', asset('/storage/reports/photos/first.jpg'))
            ->assertJsonPath('data.0.excerpt', 'Avance de la obra')
            ->assertJsonPath('data.0.comments_count', 1)
            ->assertJsonMissingPath('data.0.goal');
        $this->assertCount(1, $countQueries);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createReport(array $attributes = []): Report
    {
        $report = new Report();
        $report->title = 'Reporte';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = '2026-08-01';
        $report->forceFill($attributes);
        $report->author()->associate($this->author);
        $report->goal()->associate($this->goal);
        $report->save();

        return $report;
    }

    private function attachPhoto(Report $report, string $name): void
    {
        $photo = new ImageFile();
        $photo->name = "{$name}.jpg";
        $photo->size = 1;
        $photo->mime = 'image/jpeg';
        $photo->path = "/storage/reports/photos/{$name}.jpg";
        $photo->thumbnail_path = "/storage/reports/photos/{$name}-thumbnail.jpg";
        $report->photos()->save($photo);
    }

    private function addComment(Report $report, ?Comment $parent = null): Comment
    {
        $comment = new Comment();
        $comment->content = 'Comentario';
        $comment->parent_id = $parent?->id;
        $comment->user()->associate($this->author);
        $comment->commentable()->associate($report);
        $comment->save();

        return $comment;
    }

    private function addTestimony(Report $report, bool $value): void
    {
        $testimony = new Testimony();
        $testimony->value = $value;
        $testimony->user_id = $this->author->id;
        $testimony->report_id = $report->id;
        $testimony->save();
    }
}
