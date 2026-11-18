<?php

namespace App\Notifications;

use App\Jobs\Middleware\NotificationRateLimited;
use App\Report;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array{
     *     type: string,
     *     title: string,
     *     message: string,
     *     url: string,
     *     report_id: int,
     *     goal_id: int,
     *     objective_id: int,
     *     action: string,
     *     actor_id: int
     * }
     */
    private array $data;

    public function __construct(Report $report, string $action, User $actor)
    {
        $report->loadMissing('goal.objective');
        $goal = $report->goal;
        $message = match ($action) {
            'submitted' => 'Hay un nuevo reporte para revisar.',
            'resubmitted' => 'Un reporte corregido fue reenviado para revisión.',
            'validated' => 'Un reporte fue validado y requiere resolución de Administración. Todavía no fue publicado ni aplicado.',
            'published' => 'Un reporte fue publicado y sus cambios fueron aplicados.',
            'closed' => 'Tu reporte interno fue cerrado y consolidado para el seguimiento.',
            'returned' => 'Tu reporte fue devuelto para corrección: '.$report->return_reason,
            default => throw new \InvalidArgumentException("Acción de notificación de revisión desconocida: {$action}"),
        };
        $url = $action === 'published' && $goal->visibility === 'public' && ! $goal->objective->hidden
            ? route('reports.index', $report->getKey())
            : route('objectives.manage.goals.reports.index', [
                'objectiveId' => $goal->objective_id,
                'goalId' => $goal->getKey(),
                'reportId' => $report->getKey(),
            ]);

        $this->data = [
            'type' => 'report-review', 'title' => $report->title, 'message' => $message,
            'url' => $url,
            'report_id' => $report->getKey(),
            'goal_id' => $goal->getKey(),
            'objective_id' => $goal->objective_id,
            'action' => $action,
            'actor_id' => $actor->getKey(),
        ];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return in_array('mail', explode(',', $notifiable->notification_preferences ?? ''), true)
            ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'mailer', 'database' => 'default'];
    }

    /** @return list<NotificationRateLimited> */
    public function middleware(): array
    {
        return [new NotificationRateLimited()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject('Revisión de reporte - '.config('app.short_name'))
            ->line($this->data['message'])->line($this->data['title'])
            ->action('Ver reporte', $this->data['url']);
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     message: string,
     *     url: string,
     *     report_id: int,
     *     goal_id: int,
     *     objective_id: int,
     *     action: string,
     *     actor_id: int
     * }
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
