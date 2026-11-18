<?php

namespace Tests\Feature;

use App\Category;
use App\Goal;
use App\Objective;
use App\PfiCategory;
use App\Report;
use App\Role;
use App\Services\Pfi\PfiMemberships;
use App\Services\Reports\ReportReview;
use App\Services\Reports\ReviewStatus;
use App\Services\Workspace\WorkDesk;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkDeskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $responsible;

    private User $reporter;

    private User $otherReporter;

    private Role $userRole;

    private PfiCategory $pfiCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();

        $this->userRole = new Role();
        $this->userRole->name = 'user';
        $this->userRole->description = 'Usuario';
        $this->userRole->save();
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin = $this->createUser('Ana', 'Admin');
        $this->admin->roles()->attach($adminRole);
        $this->pfiCategory = PfiCategory::create(['name' => 'Territorio']);
        $this->responsible = $this->member('Rita', 'Responsable');
        $this->reporter = $this->member('Rosa', 'Reportera');
        $this->otherReporter = $this->member('Omar', 'Reportero');
    }

    public function test_access_is_limited_to_pfi_members_and_administrators(): void
    {
        $this->get(route('panel.desk'))->assertRedirect(route('login'));

        $outsider = $this->createUser('Nora', 'Externa');
        $this->actingAs($outsider)->get(route('panel.desk'))->assertForbidden();
        $this->get(route('panel.index'))->assertOk()->assertDontSee('Mesa de trabajo');

        $this->actingAs($this->reporter)->get(route('panel.index'))
            ->assertOk()
            ->assertSee('href="'.route('panel.desk').'"', false);
        $this->get(route('panel.desk'))
            ->assertOk()
            ->assertSee('Todavía no tenés metas asignadas')
            ->assertSee('Sin metas asignadas no tenés tareas propias')
            ->assertDontSee('Pendientes de Administración')
            ->assertDontSee('Alertas de continuidad')
            ->assertDontSee('Vista pública');

        $this->actingAs($this->admin)->get(route('panel.desk'))
            ->assertOk()
            ->assertSee('Pendientes de Administración')
            ->assertSee('Alertas de continuidad')
            ->assertSee('Todas las metas en progreso tienen Responsable y Reporteros.')
            ->assertSee('No hay reportes pendientes sin una persona que pueda atenderlos.')
            ->assertSee('Abrir el portal público')
            ->assertSee('href="'.route('home').'" target="_blank"', false);
    }

    public function test_goals_are_listed_once_with_roles_internal_mark_and_own_tasks(): void
    {
        $internal = $this->goal($this->objective('Salud'), 'Meta interna compartida', 'internal');
        $internal->responsibles()->attach($this->reporter);
        $internal->reporters()->attach([$this->reporter->id, $this->otherReporter->id]);
        $public = $this->goal($this->objective('Ambiente'), 'Meta pública');
        $public->reporters()->attach($this->reporter);
        $this->report($internal, $this->otherReporter, ReviewStatus::Pending, 'Pendiente para revisar');
        $this->report($internal, $this->reporter, ReviewStatus::Approved, 'Ya publicado');

        $goals = app(WorkDesk::class)->goals($this->reporter)->get();

        $this->assertSame([$public->id, $internal->id], $goals->modelKeys());
        $this->assertTrue($goals->find($internal->id)->is_responsible);
        $this->assertTrue($goals->find($internal->id)->is_reporter);
        $this->assertSame(1, (int) $goals->find($internal->id)->tasks_count);
        $this->assertFalse($goals->find($public->id)->is_responsible);
        $this->assertSame(0, (int) $goals->find($public->id)->tasks_count);

        $this->actingAs($this->reporter)->get(route('panel.desk'))
            ->assertOk()
            ->assertSee('Meta interna compartida')
            ->assertSee('<span class="badge badge-dark">Interna</span>', false)
            ->assertSee('1 tarea')
            ->assertSee(route('panel.goal-assignments.goal', ['goalId' => $internal->id]))
            ->assertDontSee(route('panel.goal-assignments.goal', ['goalId' => $public->id]));
    }

    public function test_responsibles_review_attendants_correct_and_resolved_reviews_disappear_for_everyone(): void
    {
        $secondResponsible = $this->member('Raúl', 'Responsable');
        $goal = $this->goal($this->objective('Salud'), 'Meta de salud');
        $goal->responsibles()->attach([$this->responsible->id, $secondResponsible->id]);
        $goal->reporters()->attach([$this->reporter->id, $this->otherReporter->id]);
        $pending = $this->report($goal, $this->reporter, ReviewStatus::Pending, 'Reporte enviado');
        $returned = $this->report($goal, $this->reporter, ReviewStatus::Returned, 'Reporte devuelto', ['return_reason' => 'Falta la evidencia']);
        $desk = app(WorkDesk::class);

        foreach ([$this->responsible, $secondResponsible] as $responsible) {
            $this->assertSame([$pending->id], $desk->actionRequired($responsible)->pluck('id')->all());
            $this->assertSame([$returned->id], $desk->waiting($responsible)->pluck('id')->all());
        }
        $this->assertSame([$returned->id], $desk->actionRequired($this->reporter)->pluck('id')->all());
        $this->assertSame([$pending->id], $desk->waiting($this->reporter)->pluck('id')->all());
        $this->assertSame(0, $desk->actionRequired($this->otherReporter)->count() + $desk->waiting($this->otherReporter)->count());

        $this->actingAs($this->reporter)->get(route('panel.desk'))
            ->assertOk()
            ->assertSee(route('objectives.manage.goals.reports.edit', ['objectiveId' => $goal->objective_id, 'goalId' => $goal->id, 'reportId' => $returned->id]))
            ->assertSee('Motivo de la devolución: Falta la evidencia')
            ->assertSee('<p class="mb-0 text-smaller">Meta: Meta de salud</p>', false)
            ->assertSee('Espera la revisión de un Responsable.');
        $this->actingAs($this->responsible)->get(route('panel.desk'))
            ->assertOk()
            ->assertSee('Validalo y envialo a Administración')
            ->assertSee('Espera la corrección de Reportera, Rosa.');

        $this->actingAs($secondResponsible)->post(route('reports.review.validate', $pending->id), [
            'review_version' => $pending->review_version,
            'context' => app(ReportReview::class)->context($pending),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $desk->actionRequired($this->responsible)->count());
        $this->assertSame([$pending->id, $returned->id], $desk->waiting($this->responsible)->pluck('id')->sort()->values()->all());
        $this->actingAs($this->reporter)->get(route('panel.desk'))
            ->assertSee('Espera que Administración lo publique.');
    }

    public function test_reassigned_pending_waits_while_reassigned_return_requires_its_attendant(): void
    {
        $goal = $this->goal($this->objective('Salud'), 'Meta de salud');
        $goal->responsibles()->attach($this->responsible);
        $goal->reporters()->attach([$this->reporter->id, $this->otherReporter->id]);
        $pending = $this->report($goal, $this->reporter, ReviewStatus::Pending, 'Pendiente reasignado', ['attendant_id' => $this->otherReporter->id]);
        $returned = $this->report($goal, $this->reporter, ReviewStatus::Returned, 'Devuelto reasignado', ['attendant_id' => $this->otherReporter->id]);
        $desk = app(WorkDesk::class);

        $this->assertSame([$returned->id], $desk->actionRequired($this->otherReporter)->pluck('id')->all());
        $this->assertSame([$pending->id], $desk->waiting($this->otherReporter)->pluck('id')->all());
        $this->assertSame(0, $desk->actionRequired($this->reporter)->count());
        $this->assertEqualsCanonicalizing([$pending->id, $returned->id], $desk->waiting($this->reporter)->pluck('id')->all());

        $this->actingAs($this->reporter)->get(route('panel.desk'))
            ->assertSee('Espera la corrección de Reportero, Omar.');
    }

    public function test_administration_tasks_split_publish_and_close_and_include_goals_without_responsible(): void
    {
        $public = $this->goal($this->objective('Salud'), 'Meta pública');
        $public->responsibles()->attach($this->responsible);
        $public->reporters()->attach($this->reporter);
        $internal = $this->goal($this->objective('Ambiente'), 'Meta interna', 'internal');
        $internal->responsibles()->attach($this->responsible);
        $internal->reporters()->attach($this->reporter);
        $uncovered = $this->goal($this->objective('Trabajo'), 'Meta sin Responsable');
        $uncovered->reporters()->attach([$this->reporter->id, $this->admin->id]);
        $toPublish = $this->report($public, $this->reporter, ReviewStatus::Validated, 'Validado público');
        $toClose = $this->report($internal, $this->reporter, ReviewStatus::Validated, 'Validado interno');
        $this->report($internal, $this->reporter, ReviewStatus::Validated, 'Ya cerrado', ['closed_at' => now()]);
        $this->report($public, $this->reporter, ReviewStatus::Pending, 'Pendiente con Responsable');
        $withoutResponsible = $this->report($uncovered, $this->reporter, ReviewStatus::Pending, 'Pendiente sin Responsable');
        $byAdministration = $this->report($uncovered, $this->admin, ReviewStatus::Pending, 'Pendiente propio de Administración');
        $desk = app(WorkDesk::class);

        $this->assertEqualsCanonicalizing([$toPublish->id, $toClose->id, $withoutResponsible->id, $byAdministration->id], $desk->administrationTasks()->pluck('id')->all());
        $this->assertSame([$toPublish->id], $desk->administrationTasks(['tipo' => WorkDesk::TASK_PUBLISH])->pluck('id')->all());
        $this->assertSame([$toClose->id], $desk->administrationTasks(['tipo' => WorkDesk::TASK_CLOSE])->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$withoutResponsible->id, $byAdministration->id], $desk->administrationTasks(['tipo' => WorkDesk::TASK_REVIEW])->pluck('id')->all());
        $this->assertSame(0, $desk->waiting($this->admin)->count());
        $this->assertSame(['administration' => 4, 'alerts' => 1], $desk->administrationSummary());

        $this->actingAs($this->admin)->get(route('panel.desk'))
            ->assertOk()
            ->assertSee('Validado. Publicalo para aplicar sus cambios en el portal.')
            ->assertSee('Validado. Cerralo y consolidalo para el seguimiento interno.')
            ->assertSee('La meta no tiene Responsable: Administración puede validarlo.')
            ->assertDontSee('Ya cerrado');
        $this->actingAs($this->responsible)->get(route('panel.desk'))
            ->assertOk()
            ->assertDontSee('Pendientes de Administración')
            ->assertSee('Espera que Administración lo cierre y consolide.');
        $this->actingAs($this->reporter)->get(route('panel.desk'))
            ->assertSee('Pendiente sin Responsable')
            ->assertSee('Espera la revisión de Administración porque la meta no tiene Responsable.')
            ->assertDontSee('Pendiente propio de Administración');
    }

    public function test_continuity_alerts_list_uncovered_ongoing_goals_and_reports_nobody_can_attend(): void
    {
        $objective = $this->objective('Salud');
        $withoutResponsible = $this->goal($objective, 'Meta sin Responsable');
        $withoutResponsible->reporters()->attach($this->reporter);
        $withoutReporters = $this->goal($objective, 'Meta sin Reporteros');
        $withoutReporters->responsibles()->attach($this->responsible);
        $this->goal($objective, 'Meta alcanzada vacía', 'public', 'reached');
        $covered = $this->goal($objective, 'Meta cubierta');
        $covered->responsibles()->attach($this->responsible);
        $covered->reporters()->attach([$this->reporter->id, $this->otherReporter->id]);
        $orphaned = $this->report($covered, $this->otherReporter, ReviewStatus::Returned, 'Devuelto sin persona a cargo');
        $covered->reporters()->detach($this->otherReporter);
        $this->report($covered, $this->admin, ReviewStatus::Pending, 'Pendiente de Administración');
        $this->report($covered, $this->reporter, ReviewStatus::Pending, 'Pendiente atendible');
        $desk = app(WorkDesk::class);

        $uncovered = $desk->uncoveredGoals()->get();
        $this->assertSame([$withoutReporters->id, $withoutResponsible->id], $uncovered->modelKeys());
        $this->assertSame([$orphaned->id], $desk->orphanedReports()->pluck('id')->all());
        $this->assertSame(3, $desk->administrationSummary()['alerts']);

        $this->actingAs($this->admin)->get(route('panel.desk'))
            ->assertOk()
            ->assertSeeInOrder(['Sin Reporteros', 'Meta sin Reporteros', 'Sin Responsable', 'Meta sin Responsable'])
            ->assertSee(route('admin.goal-assignments.goal', ['goalId' => $withoutResponsible->id]))
            ->assertDontSee('Meta alcanzada vacía')
            ->assertSee('Reportero, Omar ya no puede atenderlo porque no está asignado a la meta.');

        $withoutAlerts = $this->objective('Sin alertas');
        $this->get(route('panel.desk', ['objetivo' => $withoutAlerts->id]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['alerts'] === 3)
            ->assertSee('No hay metas sin cobertura que coincidan con los filtros.')
            ->assertSee('No hay reportes sin atención que coincidan con los filtros.')
            ->assertDontSee('Todas las metas en progreso tienen Responsable y Reporteros.')
            ->assertDontSee('No hay reportes pendientes sin una persona que pueda atenderlos.');

        $this->get(route('panel.desk', ['tipo' => WorkDesk::TASK_PUBLISH]))
            ->assertOk()
            ->assertSee('Meta sin Responsable')
            ->assertSee('No hay reportes sin atención que coincidan con los filtros.');
    }

    public function test_filters_by_role_hierarchy_and_task_type_and_tasks_are_sorted_by_age(): void
    {
        $health = $this->objective('Salud');
        $environment = $this->objective('Ambiente');
        $managed = $this->goal($health, 'Meta que coordino');
        $managed->responsibles()->attach($this->reporter);
        $managed->reporters()->attach($this->otherReporter);
        $reported = $this->goal($environment, 'Meta que reporto');
        $reported->responsibles()->attach($this->responsible);
        $reported->reporters()->attach($this->reporter);
        $newer = $this->report($managed, $this->otherReporter, ReviewStatus::Pending, 'Pendiente reciente', ['submitted_at' => now()->subDay()]);
        $older = $this->report($managed, $this->otherReporter, ReviewStatus::Pending, 'Pendiente antiguo', ['submitted_at' => now()->subDays(5)]);
        $returned = $this->report($reported, $this->reporter, ReviewStatus::Returned, 'Devuelto propio', ['reviewed_at' => now()->subDays(3)]);
        $desk = app(WorkDesk::class);
        $environmentCategory = $environment->strategicObjective->category_id;
        $ids = fn (array $filters): array => $desk->actionRequired($this->reporter, $desk->normalize($filters))->pluck('id')->all();

        $this->assertSame([$older->id, $returned->id, $newer->id], $ids([]));
        $this->assertSame([$older->id, $newer->id], $ids(['rol' => WorkDesk::ROLE_RESPONSIBLE]));
        $this->assertSame([$returned->id], $ids(['rol' => WorkDesk::ROLE_REPORTER]));
        $this->assertSame([$returned->id], $ids(['eje' => $environmentCategory]));
        $this->assertSame([$older->id, $newer->id], $ids(['objetivo' => $health->id]));
        $this->assertSame([$returned->id], $ids(['tipo' => WorkDesk::TASK_CORRECT]));
        $this->assertSame(
            ['rol' => null, 'eje' => $environmentCategory, 'objetivo' => null, 'tipo' => null],
            $desk->normalize(['rol' => ['x'], 'eje' => (string) $environmentCategory, 'objetivo' => $health->id, 'tipo' => 'otro']),
        );
        $this->assertSame([$reported->id], $desk->goals($this->reporter, $desk->normalize(['rol' => WorkDesk::ROLE_REPORTER]))->pluck('id')->all());

        $this->actingAs($this->reporter)->get(route('panel.desk', ['tipo' => WorkDesk::TASK_CORRECT]))
            ->assertOk()
            ->assertSee('Devuelto propio')
            ->assertDontSee('Pendiente antiguo');
        $this->get(route('panel.desk'))->assertSeeInOrder(['Pendiente antiguo', 'Devuelto propio', 'Pendiente reciente']);
    }

    public function test_panel_summary_and_administration_dashboard_show_desk_counters(): void
    {
        $goal = $this->goal($this->objective('Salud'), 'Meta de salud', 'internal');
        $goal->responsibles()->attach($this->responsible);
        $goal->reporters()->attach($this->reporter);
        $this->report($goal, $this->reporter, ReviewStatus::Pending, 'Pendiente');
        $this->report($goal, $this->reporter, ReviewStatus::Validated, 'Validado');

        $this->assertSame(
            ['goals' => 1, 'action' => 1, 'waiting' => 1, 'administration' => null, 'alerts' => null],
            app(WorkDesk::class)->summary($this->responsible),
        );
        $this->actingAs($this->responsible)->get(route('panel.index'))
            ->assertOk()
            ->assertSeeInOrder(['Mesa de trabajo', 'Requiere mi acción', '1', 'Esperando a otra persona', '1', 'Mis metas', '1'])
            ->assertSee(route('panel.desk').'#accion')
            ->assertDontSee('Pendientes de Administración');

        $this->actingAs($this->admin)->get(route('panel.index'))
            ->assertOk()
            ->assertSee('Pendientes de Administración');
        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSeeInOrder(['Mesa de trabajo', 'Pendientes de Administración', '1', 'Alertas de continuidad', '0'])
            ->assertSee(route('panel.desk').'#administracion');
    }

    private function member(string $name, string $surname): User
    {
        $user = $this->createUser($name, $surname);
        app(PfiMemberships::class)->admit($user, [$this->pfiCategory->id], $this->admin);

        return $user;
    }

    private function createUser(string $name, string $surname): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => strtolower($name.'.'.$surname).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach($this->userRole);

        return $user;
    }

    private function objective(string $title): Objective
    {
        $category = new Category();
        $category->forceFill(['title' => 'Eje '.$title, 'icon' => 'fas fa-circle', 'color' => '#123456', 'order' => Category::count() + 1])->save();
        $strategic = new StrategicObjective();
        $strategic->forceFill(['codigo' => 'OE'.$category->id, 'title' => 'Estratégico '.$title, 'category_id' => $category->id])->save();
        $objective = new Objective();
        $objective->forceFill(['title' => 'Objetivo '.$title, 'content' => 'Contenido', 'hidden' => false,
            'author_id' => $this->admin->id, 'strategic_objective_id' => $strategic->id])->save();

        return $objective;
    }

    private function goal(Objective $objective, string $title, string $visibility = 'public', string $status = 'ongoing'): Goal
    {
        $goal = new Goal();
        $goal->forceFill(['title' => $title, 'status' => $status, 'objective_id' => $objective->id, 'visibility' => $visibility])->save();

        return $goal;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function report(Goal $goal, User $author, ReviewStatus $status, string $title, array $overrides = []): Report
    {
        $report = new Report();
        $report->forceFill(array_replace([
            'title' => $title,
            'type' => 'post',
            'content' => 'Contenido',
            'date' => now(),
            'goal_id' => $goal->id,
            'author_id' => $author->id,
            'attendant_id' => $author->id,
            'review_status' => $status,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => $status === ReviewStatus::Returned ? now()->subDay() : null,
            'validated_at' => $status === ReviewStatus::Validated ? now()->subDay() : null,
        ], $overrides))->save();

        return $report;
    }
}
