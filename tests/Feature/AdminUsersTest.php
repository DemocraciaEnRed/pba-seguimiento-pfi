<?php

namespace Tests\Feature;

use App\ActionLog;
use App\Category;
use App\Comment;
use App\Goal;
use App\Notifications\RemoveObjectiveTeam;
use App\Objective;
use App\Report;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private User $admin;

    private User $member;

    private User $pending;

    private Objective $objective;

    private Objective $otherObjective;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->adminRole = new Role();
        $this->adminRole->name = 'admin';
        $this->adminRole->description = 'Administrador';
        $this->adminRole->save();

        $this->admin = $this->createUser('Ana', 'Admin', 'admin@example.com');
        $this->admin->roles()->attach($this->adminRole);
        $this->travel(1)->minutes();
        $this->member = $this->createUser('Marta', 'Coordinadora', 'marta@example.com', 'Fundación Verde');
        $this->travel(1)->minutes();
        $this->pending = $this->createUser('Pedro', 'Pendiente', 'pedro@example.com', null, verified: false);

        $this->objective = $this->createObjective('Reducir emisiones');
        $this->otherObjective = $this->createObjective('Sumar ciclovías');
        $this->objective->members()->attach($this->member, ['role' => 'manager']);
        $this->otherObjective->members()->attach($this->member, ['role' => 'reporter']);
        $this->objective->subscribers()->attach($this->member);

        $goal = new Goal();
        $goal->title = 'Plantar árboles';
        $goal->status = 'ongoing';
        $goal->objective()->associate($this->objective);
        $goal->save();

        $report = new Report();
        $report->title = 'Avance de forestación';
        $report->type = 'post';
        $report->content = 'Contenido';
        $report->date = now();
        $report->author()->associate($this->member);
        $report->goal()->associate($goal);
        $report->save();

        $comment = new Comment();
        $comment->content = 'Muy buen avance';
        $comment->user()->associate($this->member);
        $report->comments()->save($comment);
    }

    public function test_only_admins_can_access_the_users_section(): void
    {
        $this->get(route('admin.users'))->assertRedirect(route('login'));
        $this->actingAs($this->member)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($this->member)->get(route('admin.users.show', ['userId' => $this->pending->id]))->assertForbidden();
        $this->actingAs($this->member)
            ->post(route('admin.users.admin.grant.form', ['userId' => $this->member->id]))
            ->assertForbidden();

        $this->assertFalse($this->member->fresh()->hasRole('admin'));
    }

    public function test_list_shows_users_with_their_objectives_count(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('3 resultado(s)')
            ->assertSeeInOrder(['Pendiente, Pedro', 'Coordinadora, Marta', 'Admin, Ana'])
            ->assertSee('2 objetivos')
            ->assertSee('Fundación Verde')
            ->assertSee('No verificado')
            ->assertSee(route('admin.users.show', ['userId' => $this->member->id]));
    }

    public function test_list_searches_by_name_surname_and_email(): void
    {
        foreach (['Marta', 'Coordinadora', 'marta@example', 'Marta Coordinadora'] as $search) {
            $this->actingAs($this->admin)
                ->get(route('admin.users', ['q' => $search]))
                ->assertSee('1 resultado(s)')
                ->assertSee('Coordinadora, Marta')
                ->assertDontSee('Pendiente, Pedro');
        }
    }

    public function test_list_filters_by_verification_role_and_team(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users', ['verificado' => 'no']))
            ->assertSee('1 resultado(s)')->assertSee('Pendiente, Pedro');
        $this->actingAs($this->admin)->get(route('admin.users', ['verificado' => 'si']))
            ->assertSee('2 resultado(s)')->assertDontSee('Pendiente, Pedro');
        $this->actingAs($this->admin)->get(route('admin.users', ['rol' => 'admin']))
            ->assertSee('1 resultado(s)')->assertSee('Admin, Ana');
        $this->actingAs($this->admin)->get(route('admin.users', ['rol' => 'usuario']))
            ->assertSee('2 resultado(s)')->assertDontSee('Admin, Ana');
        $this->actingAs($this->admin)->get(route('admin.users', ['equipo' => 'con']))
            ->assertSee('1 resultado(s)')->assertSee('Coordinadora, Marta');
        $this->actingAs($this->admin)->get(route('admin.users', ['equipo' => 'sin']))
            ->assertSee('2 resultado(s)')->assertDontSee('Coordinadora, Marta');
    }

    public function test_list_can_be_sorted_by_name_and_objectives(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users', ['orden' => 'nombre']))
            ->assertSeeInOrder(['Admin, Ana', 'Coordinadora, Marta', 'Pendiente, Pedro']);
        $this->actingAs($this->admin)->get(route('admin.users', ['orden' => 'objetivos']))
            ->assertSeeInOrder(['Coordinadora, Marta', 'Admin, Ana', 'Pendiente, Pedro']);
    }

    public function test_csv_export_respects_the_filters(): void
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('admin.users.download', ['equipo' => 'con']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('marta@example.com', $csv);
        $this->assertStringContainsString('Fundación Verde', $csv);
        $this->assertStringNotContainsString('pedro@example.com', $csv);
        $this->assertMatchesRegularExpression('/marta@example\.com.*;?Sí.*No.*2.*1.*1.*1/', $csv);
    }

    public function test_profile_shows_counts_and_latest_actions(): void
    {
        ActionLog::create([
            'message' => '[Coordinadora, Marta] ha creado un reporte',
            'context' => json_encode(['user_id' => $this->member->id]),
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'mysql',
            'record_datetime' => now()->toDateTimeString(),
            'extra' => '[]',
            'formatted' => '',
        ]);
        ActionLog::create([
            'message' => 'Se envio una notificación a Marta',
            'context' => json_encode(['user_id' => $this->member->id, 'type' => 'notifications']),
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'mysql',
            'record_datetime' => now()->toDateTimeString(),
            'extra' => '[]',
            'formatted' => '',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', ['userId' => $this->member->id]))
            ->assertOk()
            ->assertSee('Coordinadora, Marta')
            ->assertSeeInOrder(['Coordina', '1', 'Reporta', '1', 'Suscripciones', '1', 'Comentarios', '1', 'Reportes', '1'])
            ->assertSee('ha creado un reporte')
            ->assertDontSee('Se envio una notificación a Marta')
            ->assertSee('value="Fundación Verde"', false);
    }

    public function test_admin_can_update_the_user_data(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update.form', ['userId' => $this->member->id]), [
                'name' => 'Martina',
                'surname' => 'Coordinadora',
                'email' => 'martina@example.com',
                'organization' => '',
            ])
            ->assertRedirect(route('admin.users.show', ['userId' => $this->member->id]))
            ->assertSessionHas('success');

        $member = $this->member->fresh();
        $this->assertSame('Martina', $member->name);
        $this->assertSame('martina@example.com', $member->email);
        $this->assertNull($member->organization);
        $this->assertSame('database', $member->notification_preferences);
        $this->assertTrue($member->hasVerifiedEmail());
        $this->assertStringContainsString('[Admin, Ana] ha editado los datos del usuario [Coordinadora, Martina]', ActionLog::latest('id')->first()->message);
    }

    public function test_update_validates_a_unique_email_and_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.users.show', ['userId' => $this->member->id]))
            ->put(route('admin.users.update.form', ['userId' => $this->member->id]), [
                'name' => '',
                'surname' => 'Coordinadora',
                'email' => 'pedro@example.com',
                'mailable' => 'true',
            ])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame('marta@example.com', $this->member->fresh()->email);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update.form', ['userId' => $this->member->id]), [
                'name' => 'Marta',
                'surname' => 'Coordinadora',
                'email' => 'marta@example.com',
                'mailable' => 'true',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('database,mail', $this->member->fresh()->notification_preferences);
    }

    public function test_admin_can_verify_and_resend_verification(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.users.verification.form', ['userId' => $this->pending->id]))
            ->assertSessionHas('success');
        Notification::assertSentTo($this->pending, VerifyEmail::class);

        $this->actingAs($this->admin)
            ->post(route('admin.users.verify.form', ['userId' => $this->pending->id]))
            ->assertSessionHas('success');
        $this->assertTrue($this->pending->fresh()->hasVerifiedEmail());

        $this->actingAs($this->admin)
            ->post(route('admin.users.verification.form', ['userId' => $this->pending->id]))
            ->assertSessionHas('error');
        Notification::assertSentToTimes($this->pending, VerifyEmail::class, 1);
    }

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.users.password.form', ['userId' => $this->member->id]))
            ->assertSessionHas('success');

        Notification::assertSentTo($this->member, ResetPassword::class);
        $this->assertTrue(Hash::check('password', $this->member->fresh()->password));
    }

    public function test_admin_can_grant_and_revoke_the_admin_role(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.admin.grant.form', ['userId' => $this->member->id]))
            ->assertSessionHas('success');
        $this->assertTrue($this->member->fresh()->hasRole('admin'));

        $this->actingAs($this->admin)
            ->delete(route('admin.users.admin.revoke.form', ['userId' => $this->member->id]))
            ->assertSessionHas('success');
        $this->assertFalse($this->member->fresh()->hasRole('admin'));
    }

    public function test_admin_cannot_revoke_their_own_admin_role(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.admin.revoke.form', ['userId' => $this->admin->id]))
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', ['userId' => $this->admin->id]))
            ->assertSee('No podés quitarte el rol de administrador a vos mismo.')
            ->assertDontSee('Quitar rol de administrador');
    }

    public function test_objectives_page_lists_memberships_and_subscriptions(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.objectives', ['userId' => $this->member->id]))
            ->assertOk()
            ->assertSee('Reducir emisiones')
            ->assertSee('Sumar ciclovías')
            ->assertSee('<option value="manager" selected>', false)
            ->assertSee(route('objectives.manage.index', ['objectiveId' => $this->objective->id]))
            ->assertSee('Suscripciones');
    }

    public function test_admin_can_change_the_role_in_an_objective(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.objectives.role.form', ['userId' => $this->member->id, 'objectiveId' => $this->otherObjective->id]), ['role' => 'manager'])
            ->assertRedirect(route('admin.users.objectives', ['userId' => $this->member->id]));

        $this->assertTrue($this->member->isManagerObjective($this->otherObjective->id));

        $this->actingAs($this->admin)
            ->put(route('admin.users.objectives.role.form', ['userId' => $this->member->id, 'objectiveId' => $this->otherObjective->id]), ['role' => 'owner'])
            ->assertSessionHasErrors('role');
        $this->actingAs($this->admin)
            ->put(route('admin.users.objectives.role.form', ['userId' => $this->pending->id, 'objectiveId' => $this->objective->id]), ['role' => 'manager'])
            ->assertNotFound();
    }

    public function test_admin_can_remove_the_user_from_an_objective_team(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.objectives.remove.form', ['userId' => $this->member->id, 'objectiveId' => $this->objective->id]))
            ->assertSessionHas('success');

        $this->assertFalse($this->member->isMemberObjective($this->objective->id));
        $this->assertTrue($this->member->isMemberObjective($this->otherObjective->id));
        Notification::assertSentTo($this->member, RemoveObjectiveTeam::class);
        $this->assertStringContainsString('ha quitado a [Coordinadora, Marta] del equipo del objetivo [Reducir emisiones]', ActionLog::latest('id')->first()->message);
    }

    public function test_old_administrators_routes_were_removed(): void
    {
        $this->actingAs($this->admin)->get('/admin/administradores')->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('admin.index'))
            ->assertSee(route('admin.users', ['rol' => 'admin']))
            ->assertSee(route('admin.users'));
    }

    private function createObjective(string $title): Objective
    {
        $category = Category::first() ?? tap(new Category(), function (Category $category): void {
            $category->title = 'Eje sostenible';
            $category->icon = 'observatorio-sostenible';
            $category->color = '#12a24f';
            $category->order = 1;
            $category->save();
        });
        $strategicObjective = StrategicObjective::first() ?? tap(new StrategicObjective(), function (StrategicObjective $strategicObjective) use ($category): void {
            $strategicObjective->codigo = 'OE1';
            $strategicObjective->title = 'Objetivo estratégico verde';
            $strategicObjective->category()->associate($category);
            $strategicObjective->save();
        });

        $objective = new Objective();
        $objective->title = $title;
        $objective->content = 'Descripción';
        $objective->hidden = false;
        $objective->author()->associate($this->admin);
        $objective->strategicObjective()->associate($strategicObjective);
        $objective->save();

        return $objective;
    }

    private function createUser(string $name, string $surname, string $email, ?string $organization = null, bool $verified = true): User
    {
        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make('password'),
            'organization' => $organization,
        ]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
