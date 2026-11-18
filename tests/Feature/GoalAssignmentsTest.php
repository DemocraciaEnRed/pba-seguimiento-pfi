<?php

namespace Tests\Feature;

use App\ActionLog;
use App\Category;
use App\Goal;
use App\Objective;
use App\PfiCategory;
use App\Role;
use App\Services\Goals\GoalAssignments;
use App\Services\Pfi\PfiMemberships;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GoalAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $responsible;

    private User $reporter;

    private User $other;

    private PfiCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->description = 'Administrador';
        $adminRole->save();
        $this->admin = $this->createUser('Ana', 'Admin', 'admin@example.com');
        $this->admin->roles()->attach($adminRole);
        $this->responsible = $this->createUser('Rita', 'Responsable', 'responsable@example.com');
        $this->reporter = $this->createUser('Rosa', 'Reportera', 'reportera@example.com');
        $this->other = $this->createUser('Omar', 'Otro', 'otro@example.com');
        $this->category = PfiCategory::create(['name' => 'Territorio']);
    }

    public function test_admin_can_assign_both_roles_and_remove_them_independently(): void
    {
        $objective = $this->createObjective();
        $goal = $this->createGoal($objective);
        $this->admit($this->responsible);
        $this->actingAs($this->admin);

        $this->get(route('admin.goal-assignments'))
            ->assertOk()
            ->assertSee('Equipos por meta')
            ->assertSee('Estas asignaciones delimitan los permisos sobre cada meta.');
        $this->get(route('admin.goal-assignments.objective', ['objectiveId' => $objective->id]))
            ->assertOk()
            ->assertSee('Estas asignaciones delimitan los permisos sobre cada meta.')
            ->assertSee('Equipo anterior por objetivo');
        $this->post(route('admin.goal-assignments.responsibles.store', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]))->assertRedirect(route('admin.goal-assignments.goal', ['goalId' => $goal->id]));

        $this->assertDatabaseHas('goal_user', [
            'goal_id' => $goal->id,
            'user_id' => $this->responsible->id,
            'role' => 'responsible',
            'assigned_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('action_logs', ['message' => '[Admin, Ana] ha asignado a [Responsable, Rita] como Responsable a la meta [Meta 1]']);

        $this->post(route('admin.goal-assignments.responsibles.store', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]))->assertSessionHas('warning');
        $this->assertSame(1, $goal->responsibles()->count());

        $this->post(route('admin.goal-assignments.reporters.store', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $goal->reporters()->count());
        $this->assertSame(1, $this->responsible->assignedGoals()->get()->count());

        $this->delete(route('admin.goal-assignments.responsibles.delete', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]))->assertSessionHas('success', 'Se quitó a la persona como Responsable. La meta quedó sin Responsables; considerá asignar a alguien para mantener la cobertura.');
        $this->assertSame(0, $goal->responsibles()->count());
        $this->assertSame(1, $goal->reporters()->count());
        $this->delete(route('admin.goal-assignments.reporters.delete', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, $goal->reporters()->count());
    }

    public function test_assignments_are_admin_only_except_reporters_on_a_responsible_users_own_goal(): void
    {
        $objective = $this->createObjective();
        $goal = $this->createGoal($objective);
        $otherGoal = $this->createGoal($objective, 'Meta ajena');
        $this->admit($this->responsible);
        $this->admit($this->reporter);
        $this->actingAs($this->admin);
        app(GoalAssignments::class)->assignResponsible($goal, $this->responsible, $this->admin);
        $this->actingAs($this->responsible);

        $this->get(route('panel.goal-assignments.index'))
            ->assertOk()
            ->assertSee('Reporteros de mis metas')
            ->assertSee('Meta 1');
        $this->get(route('panel.goal-assignments.goal', ['goalId' => $goal->id]))
            ->assertOk()
            ->assertSee('Agregar integrantes PFI');
        $this->get(route('panel.goal-assignments.goal', ['goalId' => $otherGoal->id]))->assertForbidden();
        $this->get(route('admin.goal-assignments'))->assertForbidden();

        $this->post(route('panel.goal-assignments.reporters.store', [
            'goalId' => $goal->id,
            'userId' => $this->reporter->id,
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('goal_user', [
            'goal_id' => $goal->id,
            'user_id' => $this->reporter->id,
            'role' => 'reporter',
        ]);
        $this->actingAs($this->admin)->get(route('admin.goal-assignments.goal', ['goalId' => $goal->id, 'q' => 'Reportera']))
            ->assertOk()
            ->assertSee('Asignar Responsable')
            ->assertSee('Reportero');
        $this->actingAs($this->responsible);
        $this->delete(route('panel.goal-assignments.reporters.delete', [
            'goalId' => $goal->id,
            'userId' => $this->reporter->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, $goal->reporters()->count());

        $this->post(route('admin.goal-assignments.responsibles.store', [
            'goalId' => $goal->id,
            'userId' => $this->reporter->id,
        ]))->assertForbidden();
        $this->post(route('panel.goal-assignments.reporters.store', [
            'goalId' => $otherGoal->id,
            'userId' => $this->reporter->id,
        ]))->assertForbidden();

        $this->actingAs($this->reporter)->get(route('panel.goal-assignments.index'))->assertOk()
            ->assertDontSee('Meta 1');
        $this->actingAs($this->responsible)->post(route('panel.goal-assignments.reporters.store', [
            'goalId' => $goal->id,
            'userId' => $this->other->id,
        ]))->assertSessionHasErrors('user_id');
    }

    public function test_reporter_assignment_to_an_objective_only_affects_current_goals(): void
    {
        $objective = $this->createObjective();
        $firstGoal = $this->createGoal($objective);
        $secondGoal = $this->createGoal($objective, 'Meta 2');
        $legacyMember = $this->createUser('Luz', 'Legado', 'legado@example.com');
        $objective->members()->attach($legacyMember, ['role' => 'manager']);
        $this->admit($this->reporter);
        $this->actingAs($this->admin);

        $this->post(route('admin.goal-assignments.objective.reporters.store', [
            'objectiveId' => $objective->id,
            'userId' => $this->reporter->id,
        ]))->assertRedirect(route('admin.goal-assignments.objective', ['objectiveId' => $objective->id]))
            ->assertSessionHas('success', 'Se asignó como Reportero en 2 metas existentes. Las metas futuras no se modifican.');

        $this->assertSame(1, $firstGoal->reporters()->count());
        $this->assertSame(1, $secondGoal->reporters()->count());
        $this->post(route('admin.goal-assignments.objective.reporters.store', [
            'objectiveId' => $objective->id,
            'userId' => $this->reporter->id,
        ]))->assertSessionHas('warning');
        $futureGoal = $this->createGoal($objective, 'Meta futura');
        $this->assertSame(0, $futureGoal->reporters()->count());
        $this->assertDatabaseHas('objective_user', [
            'objective_id' => $objective->id,
            'user_id' => $legacyMember->id,
            'role' => 'manager',
        ]);
        $this->actingAs($this->admin)->get(route('admin.goal-assignments.objective', ['objectiveId' => $objective->id]))
            ->assertSee('Legado, Luz')
            ->assertSee('Coordinaba');

        $this->assertSame(1, ActionLog::where('message', 'like', '%ha asignado a [Reportera, Rosa] como Reportero en 2 metas existentes%')->count());
    }

    public function test_bulk_reporter_assignment_requires_at_least_one_existing_goal(): void
    {
        $objective = $this->createObjective();
        $this->admit($this->reporter);
        $this->actingAs($this->admin)->post(route('admin.goal-assignments.objective.reporters.store', [
            'objectiveId' => $objective->id,
            'userId' => $this->reporter->id,
        ]))->assertSessionHas('warning', 'Este objetivo todavía no tiene metas para asignar.');

        $this->assertDatabaseCount('goal_user', 0);
    }

    public function test_candidate_search_is_global_for_admin_and_limited_to_responsible_goals_for_coordinators(): void
    {
        $objective = $this->createObjective();
        $goal = $this->createGoal($objective);
        $otherGoal = $this->createGoal($this->createObjective('Otro objetivo'), 'Meta ajena');
        $this->admit($this->responsible);
        $this->admit($this->reporter);
        $url = route('apiService.pfi.candidates', ['name' => 'Reportera']);

        $this->actingAs($this->admin)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->reporter->id);

        $this->actingAs($this->responsible)->getJson($url)->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.goal-assignments.responsibles.store', [
            'goalId' => $goal->id,
            'userId' => $this->responsible->id,
        ]));
        $this->actingAs($this->responsible)->getJson(route('apiService.pfi.candidates', [
            'name' => 'Reportera',
            'goal_id' => $goal->id,
        ]))->assertOk()->assertJsonPath('data.0.id', $this->reporter->id);

        $this->actingAs($this->responsible)->getJson(route('apiService.pfi.candidates', [
            'name' => 'Reportera',
            'goal_id' => $otherGoal->id,
        ]))->assertForbidden();
        $this->assertFalse($this->responsible->can('update', $goal));
    }

    public function test_goal_deletion_cascades_and_assignment_unique_key_allows_different_roles(): void
    {
        $goal = $this->createGoal($this->createObjective());
        $this->admit($this->reporter);
        $this->actingAs($this->admin);
        $assignments = app(GoalAssignments::class);
        $assignments->assignResponsible($goal, $this->reporter, $this->admin);
        $assignments->assignReporter($goal, $this->reporter, $this->admin);

        $this->assertSame(2, $goal->responsibles()->count() + $goal->reporters()->count());
        $this->assertSame(1, $this->reporter->assignedGoals()->get()->count());
        $goal->forceDelete();
        $this->assertDatabaseMissing('goal_user', ['goal_id' => $goal->id]);
    }

    private function admit(User $user): void
    {
        app(PfiMemberships::class)->admit($user, [$this->category->id], $this->admin);
    }

    private function createUser(string $name, string $surname, string $email): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function createObjective(string $title = 'Objetivo'): Objective
    {
        $category = new Category();
        $category->title = 'Eje '.$title;
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();
        $strategicObjective = new StrategicObjective();
        $strategicObjective->title = 'Objetivo estratégico '.$title;
        $strategicObjective->codigo = 'OE'.Category::count();
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Contenido';
        $objective->hidden = false;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createGoal(Objective $objective, string $title = 'Meta 1'): Goal
    {
        $goal = new Goal();
        $goal->title = $title;
        $goal->status = 'ongoing';
        $goal->objective()->associate($objective);
        $goal->save();

        return $goal;
    }
}
