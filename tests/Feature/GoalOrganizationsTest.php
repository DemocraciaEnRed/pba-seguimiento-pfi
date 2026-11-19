<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\Organization;
use App\Report;
use App\Role;
use App\Services\Reports\ReportReview;
use App\Services\Reports\ReviewStatus;
use App\StrategicObjective;
use App\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GoalOrganizationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Objective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withoutVite();
        $this->admin = UserFactory::new()->create(['surname' => 'Admin']);
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin->roles()->attach($adminRole);

        $category = new Category();
        $category->title = 'Eje';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-1';
        $strategicObjective->title = 'Objetivo estratégico';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->objective = new Objective();
        $this->objective->title = 'Objetivo con organizaciones';
        $this->objective->content = 'Descripción';
        $this->objective->hidden = false;
        $this->objective->author()->associate($this->admin);
        $this->objective->strategicObjective()->associate($strategicObjective);
        $this->objective->save();
    }

    public function test_admin_sets_one_responsible_and_several_participant_organizations(): void
    {
        $alfa = $this->createOrganization('Alfa Ministerio');
        $beta = $this->createOrganization('Beta Instituto');
        $gamma = $this->createOrganization('Gamma Agencia');
        $this->createOrganization('Delta Inactiva', isActive: false);

        $this->actingAs($this->admin)
            ->get(route('objectives.manage.goals.add', ['objectiveId' => $this->objective->id]))
            ->assertOk()
            ->assertSee('Organización responsable')
            ->assertSee(['Alfa Ministerio', 'Beta Instituto', 'Gamma Agencia'])
            ->assertDontSee('Delta Inactiva');

        $this->actingAs($this->admin)
            ->post(route('objectives.manage.goals.add.form', ['objectiveId' => $this->objective->id]), [
                ...$this->goalPayload(),
                'visibility' => 'public',
                'responsible_organization' => (string) $alfa->id,
                'participant_organizations' => [(string) $beta->id, (string) $gamma->id],
            ])
            ->assertSessionHasNoErrors();

        $goal = Goal::sole();
        $this->assertSame($alfa->id, $goal->responsible_organization_id);
        $this->assertEqualsCanonicalizing([$beta->id, $gamma->id], $goal->participantOrganizations->modelKeys());

        $this->editGoal($goal, ['responsible_organization' => (string) $beta->id, 'participant_organizations' => [(string) $alfa->id]])
            ->assertSessionHasNoErrors();
        $goal->refresh();
        $this->assertSame($beta->id, $goal->responsible_organization_id);
        $this->assertSame([$alfa->id], $goal->participantOrganizations->modelKeys());

        $this->editGoal($goal, ['responsible_organization' => (string) $gamma->id, 'participant_organizations' => [(string) $gamma->id]])
            ->assertSessionHasErrors(['participant_organizations.0' => 'La organización responsable no puede figurar también como participante.']);
        $this->assertSame($beta->id, $goal->fresh()->responsible_organization_id);
    }

    public function test_deactivated_organization_is_not_offered_but_goals_keep_it(): void
    {
        $kept = $this->createOrganization('Organismo histórico');
        $goal = $this->createGoal('Meta con organismo histórico', 'public', $kept);
        $otherGoal = $this->createGoal('Otra meta', 'public');

        $this->actingAs($this->admin)
            ->from(route('admin.organizations.edit', ['organizationId' => $kept->id]))
            ->followingRedirects()
            ->put(route('admin.organizations.edit.form', ['organizationId' => $kept->id]), ['name' => '', 'description' => 'Descripción'])
            ->assertOk()
            ->assertSee('id="organization-active" name="is_active" value="1" >', false);
        $this->assertTrue($kept->fresh()->is_active);

        $this->actingAs($this->admin)
            ->put(route('admin.organizations.edit.form', ['organizationId' => $kept->id]), [
                'name' => 'Organismo histórico',
                'description' => 'Descripción',
            ])
            ->assertRedirect(route('admin.organizations'));
        $this->assertFalse($kept->fresh()->is_active);

        $this->actingAs($this->admin)->get(route('admin.organizations'))
            ->assertOk()
            ->assertSeeInOrder(['Organismo histórico', 'Inactiva', '1 como responsable']);

        $this->actingAs($this->admin)
            ->get(route('objectives.manage.goals.edit', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]))
            ->assertOk()
            ->assertSee('Organismo histórico (inactiva)');
        $this->editGoal($goal, ['responsible_organization' => (string) $kept->id])->assertSessionHasNoErrors();
        $this->assertSame($kept->id, $goal->fresh()->responsible_organization_id);

        $this->actingAs($this->admin)
            ->get(route('objectives.manage.goals.edit', ['objectiveId' => $this->objective->id, 'goalId' => $otherGoal->id]))
            ->assertOk()
            ->assertDontSee('Organismo histórico');
        $this->editGoal($otherGoal, ['participant_organizations' => [(string) $kept->id]])
            ->assertSessionHasErrors('participant_organizations.0');
        $this->assertSame([], $otherGoal->participantOrganizations()->get()->modelKeys());
    }

    public function test_portal_shows_goal_organizations_and_objective_union_within_the_public_audience(): void
    {
        $responsible = $this->createOrganization('Ministerio Responsable');
        $participant = $this->createOrganization('Instituto Participante');
        $internalOnly = $this->createOrganization('Organismo Reservado');
        $publicGoal = $this->createGoal('Meta pública', 'public', $responsible, [$participant]);
        $this->createGoal('Meta interna', 'internal', $internalOnly);

        $this->get(route('goals.index', ['goalId' => $publicGoal->id]))
            ->assertOk()
            ->assertSeeInOrder(['Organización responsable', 'Ministerio Responsable', 'Organizaciones participantes', 'Instituto Participante']);

        foreach ([null, $this->admin] as $viewer) {
            $response = $viewer ? $this->actingAs($viewer)->get(route('objectives.index', ['objectiveId' => $this->objective->id])) : $this->get(route('objectives.index', ['objectiveId' => $this->objective->id]));
            $response->assertOk()
                ->assertSee(['Ministerio Responsable', 'Instituto Participante'])
                ->assertDontSee('Organismo Reservado');
        }
    }

    public function test_published_reports_keep_the_organizations_the_goal_had_at_publication(): void
    {
        $original = $this->createOrganization('Secretaría Original');
        $participant = $this->createOrganization('Consejo Participante');
        $later = $this->createOrganization('Dirección Posterior');
        $goal = $this->createGoal('Meta pública', 'public', $original, [$participant]);

        $validated = $this->createReport($goal, 'Reporte validado', ReviewStatus::Validated);
        $this->actingAs($this->admin)
            ->post(route('reports.review.publish', $validated->id), $this->reviewData($validated))
            ->assertSessionHasNoErrors();
        $this->assertSame([
            'responsible' => ['id' => $original->id, 'name' => 'Secretaría Original'],
            'participants' => [['id' => $participant->id, 'name' => 'Consejo Participante']],
        ], $validated->fresh()->organizations_snapshot);

        $original->name = 'Secretaría Renombrada';
        $original->is_active = false;
        $original->save();
        $goal->responsible_organization_id = $later->id;
        $goal->save();
        $goal->participantOrganizations()->detach();

        $this->get(route('reports.index', ['reportId' => $validated->id]))
            ->assertOk()
            ->assertSee(['Secretaría Original', 'Consejo Participante'])
            ->assertDontSee('Dirección Posterior');

        $this->actingAs($this->admin)
            ->post(route('objectives.manage.goals.reports.add.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]), [
                'type' => 'post', 'title' => 'Carga directa', 'content' => 'Contenido', 'date' => '2026-08-01', 'tags' => [],
            ])
            ->assertSessionHasNoErrors();
        $direct = Report::query()->where('title', 'Carga directa')->sole();
        $this->assertSame(ReviewStatus::Approved, $direct->review_status);
        $this->assertSame(['responsible' => ['id' => $later->id, 'name' => 'Dirección Posterior'], 'participants' => []], $direct->organizations_snapshot);

        $legacy = $this->createReport($goal, 'Reporte anterior', ReviewStatus::Approved);
        $this->get(route('reports.index', ['reportId' => $legacy->id]))
            ->assertOk()
            ->assertDontSee('Organización responsable');
    }

    public function test_closing_an_internal_report_does_not_record_organizations(): void
    {
        $goal = $this->createGoal('Meta interna', 'internal', $this->createOrganization('Organismo Interno'));
        $report = $this->createReport($goal, 'Reporte interno', ReviewStatus::Validated);

        $this->actingAs($this->admin)
            ->post(route('reports.review.close', $report->id), $this->reviewData($report))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($report->fresh()->closed_at);
        $this->assertNull($report->fresh()->organizations_snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    private function goalPayload(): array
    {
        return ['title' => 'Meta con organizaciones', 'status' => 'ongoing', 'measurement_mode' => 'none'];
    }

    /**
     * @param  array<string, mixed>  $organizations
     */
    private function editGoal(Goal $goal, array $organizations): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->put(route('objectives.manage.goals.edit.form', ['objectiveId' => $this->objective->id, 'goalId' => $goal->id]), [
                ...$this->goalPayload(),
                'title' => $goal->title,
                ...$organizations,
            ]);
    }

    /**
     * @return array{review_version: int, context: string}
     */
    private function reviewData(Report $report): array
    {
        $report = $report->fresh();

        return ['review_version' => $report->review_version, 'context' => app(ReportReview::class)->context($report)];
    }

    private function createOrganization(string $name, bool $isActive = true): Organization
    {
        $organization = new Organization();
        $organization->name = $name;
        $organization->description = "Descripción de {$name}";
        $organization->is_active = $isActive;
        $organization->save();

        return $organization;
    }

    /**
     * @param  list<Organization>  $participants
     */
    private function createGoal(string $title, string $visibility, ?Organization $responsible = null, array $participants = []): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->visibility = $visibility;
        $goal->responsible_organization_id = $responsible?->id;
        $goal->objective()->associate($this->objective);
        $goal->save();
        $goal->participantOrganizations()->attach(array_map(fn (Organization $organization): int => $organization->id, $participants));

        return $goal;
    }

    private function createReport(Goal $goal, string $title, ReviewStatus $status): Report
    {
        $report = new Report();
        $report->review_status = $status;
        $report->title = $title;
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = '2026-08-01';
        $report->author()->associate($this->admin);
        $report->attendant()->associate($this->admin);
        $report->goal()->associate($goal);
        $report->save();

        return $report;
    }
}
