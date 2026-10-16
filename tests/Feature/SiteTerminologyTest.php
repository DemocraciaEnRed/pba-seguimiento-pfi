<?php

namespace Tests\Feature;

use App\Category;
use App\Event;
use App\Notifications\DeleteEvent;
use App\Notifications\EditEvent;
use App\Notifications\NewEvent;
use App\Role;
use App\StrategicObjective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteTerminologyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-08-15 10:00:00');

        $this->admin = User::create([
            'name' => 'Ana',
            'surname' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->email_verified_at = now();
        $this->admin->save();

        $role = new Role();
        $role->name = 'admin';
        $role->description = 'Administrador';
        $role->save();
        $this->admin->roles()->attach($role);

        $userRole = new Role();
        $userRole->name = 'user';
        $userRole->description = 'Usuario';
        $userRole->save();
        $this->admin->roles()->attach($userRole);
    }

    public function test_public_agenda_and_empty_states_use_activities_without_changing_urls(): void
    {
        $this->get('/eventos')
            ->assertOk()
            ->assertSee('Actividades')
            ->assertSee('Próximas actividades')
            ->assertSee('No hay próximas actividades anunciadas')
            ->assertSee(route('events.past'), false)
            ->assertDontSee('Eventos');

        $this->get('/eventos/pasados')
            ->assertOk()
            ->assertSee('Actividades celebradas')
            ->assertSee('Aún no se han celebrado actividades en el pasado');
    }

    public function test_plan_uses_strategic_objectives_and_preserves_category_links(): void
    {
        $category = new Category();
        $category->title = 'Eje de prueba';
        $category->icon = 'fas fa-circle';
        $category->color = '#123456';
        $category->order = 1;
        $category->save();

        $strategicObjective = new StrategicObjective();
        $strategicObjective->codigo = 'OE-1';
        $strategicObjective->title = 'Fortalecer equipos';
        $strategicObjective->category()->associate($category);
        $strategicObjective->save();

        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('Objetivos Estratégicos')
            ->assertSee('Objetivos generales')
            ->assertDontSee('Estrategias')
            ->assertSee('eje-'.$category->id, false);
    }

    public function test_admin_and_personal_navigation_use_general_objectives_and_action_logs(): void
    {
        $this->actingAs($this->admin)->get(route('admin.objectives'))
            ->assertOk()
            ->assertSee('Objetivos Generales')
            ->assertSee('Actividades')
            ->assertSee('Bitácora de acciones')
            ->assertDontSee('Objetivos Específicos')
            ->assertDontSee('Bitacora de eventos');

        $this->get(route('admin.objectives.create'))
            ->assertOk()
            ->assertSee('Crear objetivo general');

        $this->get(route('admin.logs'))
            ->assertOk()
            ->assertSee('Bitácora de acciones')
            ->assertDontSee('Bitacora del sistema');

        $this->get(route('panel.objectives'))
            ->assertOk()
            ->assertSee('Mis objetivos generales')
            ->assertDontSee('Mis objetivos específicos');
    }

    public function test_activity_forms_lists_and_detail_use_feminine_labels(): void
    {
        $this->actingAs($this->admin)->get(route('admin.events'))
            ->assertOk()
            ->assertSee('Próximas actividades')
            ->assertSee('Celebradas')
            ->assertSee('No hay actividades creadas');

        $this->get(route('admin.events.create'))
            ->assertOk()
            ->assertSee('Crear actividad')
            ->assertSee('Título de la actividad')
            ->assertSee(route('admin.events.create.form'), false);

        $event = $this->createEvent();

        $this->get(route('admin.events.edit', ['eventId' => $event->id]))
            ->assertOk()
            ->assertSee('Editar actividad')
            ->assertSee('Descripción de la actividad');

        $this->get(route('admin.events.delete', ['eventId' => $event->id]))
            ->assertOk()
            ->assertSee('Eliminar actividad')
            ->assertSee('la actividad ha sido eliminada');

        $this->get(route('events.index', ['eventId' => $event->id]))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('No se han previsto links acerca de la actividad');

        $event->date = now()->subDay();
        $this->assertStringStartsWith('Celebrada el ', $event->moment);
    }

    public function test_activity_actions_keep_their_contract_and_use_new_success_messages(): void
    {
        $payload = [
            'title' => 'Encuentro de equipos',
            'content' => 'Actividad de prueba',
            'date' => '2026-09-01',
            'hour' => 10,
            'minute' => 0,
            'address' => 'Sala de reuniones',
            'objectives' => [],
        ];

        $this->actingAs($this->admin)->post(route('admin.events.create.form'), $payload)
            ->assertRedirect(route('admin.events'))
            ->assertSessionHas('success', '¡Nueva actividad creada!');

        $event = Event::sole();
        $this->assertSame($payload['title'], $event->title);
        $this->assertTrue($event->author->is($this->admin));

        $payload['title'] = 'Encuentro reprogramado';
        $this->put(route('admin.events.edit.form', ['eventId' => $event->id]), $payload)
            ->assertRedirect(route('admin.events'))
            ->assertSessionHas('success', 'La actividad ha sido editada correctamente');
        $this->assertSame($payload['title'], $event->fresh()->title);

        $this->delete(route('admin.events.delete.form', ['eventId' => $event->id]), ['password' => 'password'])
            ->assertRedirect(route('admin.events'))
            ->assertSessionHas('success', 'La actividad ha sido eliminada correctamente');
        $this->assertSoftDeleted($event);
    }

    public function test_activity_notifications_change_only_presentation_not_stored_payloads(): void
    {
        $event = $this->createEvent();
        $cases = [
            [new NewEvent($event), 'new-event', 'newEvent', '¡Nueva actividad en el '.config('app.short_name').'!'],
            [new EditEvent($event), 'edit-event', 'editEvent', 'Han editado una actividad que sigues en el '.config('app.short_name')],
            [new DeleteEvent($event), 'delete-event', 'deleteEvent', 'Han eliminado una actividad que sigues en el '.config('app.short_name')],
        ];

        foreach ($cases as [$notification, $type, $template, $subject]) {
            $payload = $notification->toDatabase($this->admin);
            $this->assertSame([
                'type' => $type,
                'event' => ['id' => $event->id, 'title' => $event->title],
            ], $payload);

            $mail = $notification->toMail($this->admin);
            $this->assertSame($subject, $mail->subject);
            $body = (string) $mail->render();
            $this->assertStringContainsString('actividad', $body);
            $this->assertStringContainsString($event->title, $body);
            $this->assertStringNotContainsString('evento', strip_tags($body));

            $storedNotification = new DatabaseNotification();
            $storedNotification->data = $payload;
            $storedNotification->created_at = now();
            $html = view('utils.notifications.'.$template, ['notification' => $storedNotification])->render();
            $this->assertStringContainsString('actividad', $html);
            $this->assertStringContainsString($event->title, $html);
            $this->assertStringNotContainsString('evento', strip_tags($html));
        }
    }

    private function createEvent(): Event
    {
        $event = new Event();
        $event->title = 'Encuentro de equipos';
        $event->content = 'Intercambio de experiencias';
        $event->date = '2026-09-01 10:00:00';
        $event->address = 'Sala de reuniones';
        $event->author()->associate($this->admin);
        $event->save();

        return $event;
    }
}
