<?php

namespace Tests\Feature;

use App\Notifications\DeleteObjective;
use App\Objective;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class PlatformNameInNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_emails_use_default_short_name(): void
    {
        $mail = $this->buildDeleteObjectiveMail();
        $body = (string) $mail->render();

        $this->assertSame('Han eliminado un objetivo que sigues en el Mapa de Fortalecimiento Institucional', $mail->subject);
        $this->assertStringContainsString('en el Mapa de Fortalecimiento Institucional.', $body);
        $this->assertStringNotContainsStringIgnoringCase('participes', $body);
    }

    public function test_emails_use_configured_short_name(): void
    {
        config(['app.short_name' => 'Portal PBA']);

        $mail = $this->buildDeleteObjectiveMail();

        $this->assertSame('Han eliminado un objetivo que sigues en el Portal PBA', $mail->subject);
        $this->assertStringContainsString('en el Portal PBA.', (string) $mail->render());
    }

    private function buildDeleteObjectiveMail(): MailMessage
    {
        $objective = new Objective();
        $objective->title = 'Objetivo de prueba';

        $user = new User();
        $user->name = 'Ana';

        return (new DeleteObjective($objective))->toMail($user);
    }
}
