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

    /** @var array{type: string, title: string, message: string, url: string, report_id: int, actor_id: int} */
    private array $data;

    public function __construct(Report $report, string $action, User $actor)
    {
        $message = match ($action) {
            'submitted' => 'Hay un nuevo reporte pendiente de revisión.',
            'resubmitted' => 'Un reporte corregido fue reenviado a revisión.',
            'approved' => 'Tu reporte fue aprobado y sus cambios fueron aplicados.',
            'returned' => 'Tu reporte fue devuelto para corrección: '.$report->return_reason,
        };
        $this->data = [
            'type' => 'report-review', 'title' => $report->title, 'message' => $message,
            'url' => route('objectives.manage.goals.reports.index', ['objectiveId' => $report->goal->objective_id, 'goalId' => $report->goal_id, 'reportId' => $report->id]),
            'report_id' => $report->id, 'actor_id' => $actor->id,
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

    /** @return array{type: string, title: string, message: string, url: string, report_id: int, actor_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
