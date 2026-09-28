<?php

namespace Tests\Feature;

use App\Event;
use App\ImageFile;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalHeroTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->author = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'author@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function heroPages(): array
    {
        return [
            'home' => ['home', null],
            'objectives' => ['objectives', 'Catálogo de objetivos específicos'],
            'catalog' => ['catalog', 'Catálogo'],
            'reports' => ['reports', 'Catálogo de reportes'],
            'upcoming events' => ['events.upcoming', 'Eventos'],
            'past events' => ['events.past', 'Eventos'],
            'about general' => ['about.general', '¿Cómo funciona?'],
            'about faq' => ['about.faq', '¿Cómo funciona?'],
            'about legal' => ['about.legal', '¿Cómo funciona?'],
        ];
    }

    #[DataProvider('heroPages')]
    public function test_portal_pages_render_the_hero_instead_of_the_legacy_header(string $routeName, ?string $title): void
    {
        $response = $this->get(route($routeName))
            ->assertOk()
            ->assertSee('class="hero hero--', false)
            ->assertDontSee('app-portal-header', false);

        if ($title) {
            $response->assertSee('<h1 class="hero__title">', false)->assertSee($title);
        }
    }

    public function test_events_hero_marks_the_current_listing_as_active(): void
    {
        $this->get(route('events.past'))
            ->assertOk()
            ->assertSee('href="'.route('events.past').'" class="btn btn-light"', false)
            ->assertSee('href="'.route('events.upcoming').'" class="btn btn-outline-light"', false);
    }

    public function test_event_view_renders_a_plain_hero_without_photos(): void
    {
        $event = $this->createEvent();

        $this->get(route('events.index', ['eventId' => $event->id]))
            ->assertOk()
            ->assertSee('Presentación del plan')
            ->assertSee('class="hero hero--lg text-center"', false)
            ->assertDontSee('hero--has-image', false)
            ->assertDontSee('app-portal-header', false);
    }

    public function test_event_view_uses_the_first_photo_as_hero_background(): void
    {
        $event = $this->createEvent();
        $photo = new ImageFile;
        $photo->name = 'foto.jpg';
        $photo->size = '100';
        $photo->mime = 'image/jpeg';
        $photo->path = 'storage/events/foto.jpg';
        $event->photos()->save($photo);

        $this->get(route('events.index', ['eventId' => $event->id]))
            ->assertOk()
            ->assertSee('hero--has-image', false)
            ->assertSee("background-image: url('".asset('storage/events/foto.jpg')."')");
    }

    public function test_pages_without_a_hero_keep_the_legacy_header(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('app-portal-header', false)
            ->assertDontSee('class="hero hero--', false);
    }

    private function createEvent(): Event
    {
        $event = new Event;
        $event->author_id = $this->author->id;
        $event->date = now()->addDays(3);
        $event->title = 'Presentación del plan';
        $event->content = 'Contenido del evento';
        $event->address = 'Calle 1';
        $event->save();

        return $event;
    }
}
