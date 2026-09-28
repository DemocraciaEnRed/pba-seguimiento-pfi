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

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Objective $objective;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = $this->createUser('author@example.com');

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

        $goal = new Goal();
        $goal->title = 'Meta del reporte';
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);
        $goal->save();

        $this->report = new Report();
        $this->report->title = 'Inauguramos la obra';
        $this->report->type = 'post';
        $this->report->content = 'Contenido del reporte';
        $this->report->date = '2026-09-01';
        $this->report->tags = ['obras'];
        $this->report->author()->associate($this->author);
        $this->report->goal()->associate($goal);
        $this->report->save();
    }

    public function test_page_shows_the_report_header_in_the_hero(): void
    {
        $this->get(route('reports.index', ['reportId' => $this->report->id]))
            ->assertOk()
            ->assertSee('report-hero', false)
            ->assertSee('Inauguramos la obra')
            ->assertSee('Reporte de Novedad')
            ->assertSee('#obras')
            ->assertSee('<report-like-button', false)
            ->assertDontSee('Dejanos tu feedback');
    }

    public function test_report_of_a_hidden_objective_is_not_found_for_the_public(): void
    {
        $this->hideObjective();
        $outsider = $this->createUser('outsider@example.com');

        $this->get(route('reports.index', ['reportId' => $this->report->id]))->assertNotFound();
        $this->actingAs($outsider)->get(route('reports.index', ['reportId' => $this->report->id]))->assertNotFound();
        $this->getJson(route('apiService.reports.comments', ['reportId' => $this->report->id]))->assertNotFound();
    }

    public function test_report_of_a_hidden_objective_is_visible_to_its_members(): void
    {
        $this->hideObjective();
        $member = $this->createUser('member@example.com');
        $this->objective->members()->attach($member, ['role' => 'reporter']);

        $this->actingAs($member)
            ->get(route('reports.index', ['reportId' => $this->report->id]))
            ->assertOk()
            ->assertSee('Inauguramos la obra');
    }

    public function test_guests_cannot_like_a_report(): void
    {
        $this->postJson($this->likeUrl())->assertForbidden();

        $this->assertSame(0, $this->report->testimonies()->count());
    }

    public function test_unverified_users_cannot_like_a_report(): void
    {
        $unverified = $this->createUser('unverified@example.com', verified: false);

        $this->actingAs($unverified)->postJson($this->likeUrl())->assertForbidden();

        $this->assertSame(0, $this->report->testimonies()->count());
    }

    public function test_verified_users_toggle_their_like(): void
    {
        $user = $this->createUser('fan@example.com');

        $this->actingAs($user)->postJson($this->likeUrl())
            ->assertOk()
            ->assertJson(['liked' => true, 'count' => 1]);

        $this->actingAs($user)->postJson($this->likeUrl())
            ->assertOk()
            ->assertJson(['liked' => false, 'count' => 0]);
    }

    public function test_reports_of_hidden_objectives_cannot_be_liked_by_outsiders(): void
    {
        $this->hideObjective();
        $outsider = $this->createUser('outsider@example.com');

        $this->actingAs($outsider)->postJson($this->likeUrl())->assertNotFound();

        $this->assertSame(0, $this->report->testimonies()->count());
    }

    private function likeUrl(): string
    {
        return route('apiService.reports.testimonies.run', ['reportId' => $this->report->id]);
    }

    private function hideObjective(): void
    {
        $this->objective->hidden = true;
        $this->objective->save();
    }

    private function createUser(string $email, bool $verified = true): User
    {
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $user;
    }
}
