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

class HomeLastObjectivesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Objective $objective;

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

        $this->objective = new Objective();
        $this->objective->title = 'Objetivo';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->author);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();
    }

    public function test_latest_report_is_the_most_recently_updated_one(): void
    {
        $goal = $this->createGoal();
        $this->createReport($goal, 'Reporte viejo', '2026-08-01 10:00:00');
        $latest = $this->createReport($goal, 'Reporte nuevo', '2026-09-01 10:00:00');

        $this->getJson(route('apiService.objectives', ['with' => 'objective_latest_report']))
            ->assertOk()
            ->assertJsonPath('data.0.latest_report.id', $latest->id)
            ->assertJsonPath('data.0.latest_report.title', 'Reporte nuevo')
            ->assertJsonPath('data.0.latest_report.type_label', 'Novedad')
            ->assertJsonPath('data.0.latest_report.type_icon', 'fas fa-bullhorn')
            ->assertJsonPath('data.0.latest_report.url', route('reports.index', ['reportId' => $latest->id]));
    }

    public function test_latest_report_is_null_without_reports(): void
    {
        $this->createGoal();

        $this->getJson(route('apiService.objectives', ['with' => 'objective_latest_report']))
            ->assertOk()
            ->assertJsonPath('data.0.latest_report', null);
    }

    public function test_latest_report_is_omitted_by_default(): void
    {
        $this->getJson(route('apiService.objectives'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.latest_report');
    }

    public function test_objectives_include_a_human_readable_update_time(): void
    {
        $this->travelTo(now()->addDays(3));

        $this->getJson(route('apiService.objectives'))
            ->assertOk()
            ->assertJsonPath('data.0.updated_when', 'hace 3 días');
    }

    private function createGoal(): Goal
    {
        $goal = new Goal();
        $goal->title = 'Meta';
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);
        $goal->save();

        return $goal;
    }

    private function createReport(Goal $goal, string $title, string $updatedAt): Report
    {
        $report = new Report();
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = '2026-08-01';
        $report->author()->associate($this->author);
        $report->goal()->associate($goal);
        $report->save();
        $report->forceFill(['updated_at' => $updatedAt])->saveQuietly();

        return $report;
    }
}

