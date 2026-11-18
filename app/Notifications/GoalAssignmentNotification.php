<?php

namespace App\Notifications;

use App\Goal;
use App\Jobs\Middleware\NotificationRateLimited;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class GoalAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array{
     *     type: string,
     *     title: string,
     *     objective_title: string,
     *     message: string,
     *     url: string,
     *     goal_id: int,
     *     objective_id: int,
     *     roles: list<string>,
     *     action: string,
     *     actor_id: int
     * }
     */
    private array $data;

    /** @param list<string> $roles */
    public function __construct(Goal $goal, array $roles, string $action, User $actor)
    {
        if (! in_array($action, ['assigned', 'removed'], true) || $roles === []) {
            throw new InvalidArgumentException('La notificación requiere una acción y al menos un rol válidos.');
        }

        $roles = array_values(array_unique($roles));
        foreach ($roles as $role) {
            if (! in_array($role, ['responsible', 'reporter'], true)) {
                throw new InvalidArgumentException("Rol de asignación desconocido: {$role}");
            }
        }

        $roles = array_values(array_intersect(['responsible', 'reporter'], $roles));
        $roleLabels = array_map(
            static fn (string $role): string => match ($role) {
                'responsible' => 'Responsable',
                'reporter' => 'Reportero',
            },
            $roles,
        );
        $goal->loadMissing('objective');

        $this->data = [
            'type' => 'goal-assignment',
            'title' => $goal->title,
            'objective_title' => $goal->objective->title,
            'message' => $action === 'assigned'
                ? 'Te asignaron como '.implode(' y ', $roleLabels).'.'
                : 'Se retiró tu asignación como '.implode(' y ', $roleLabels).'.',
            'url' => $action === 'assigned'
                ? route('objectives.manage.goals.index', ['objectiveId' => $goal->objective_id, 'goalId' => $goal->id])
                : ($goal->visibility === 'public' && ! $goal->objective->hidden
                    ? route('goals.index', $goal->id)
                    : route('panel.notifications')),
            'goal_id' => $goal->id,
            'objective_id' => $goal->objective_id,
            'roles' => $roles,
            'action' => $action,
            'actor_id' => $actor->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return in_array('mail', explode(',', $notifiable->notification_preferences ?? ''), true)
            ? ['database', 'mail']
            : ['database'];
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
        return (new MailMessage())
            ->subject('Asignación a una meta - '.config('app.short_name'))
            ->line($this->data['message'])
            ->line('Meta: '.$this->data['title'])
            ->line('Objetivo: '.$this->data['objective_title'])
            ->action('Ver asignación', $this->data['url']);
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     objective_title: string,
     *     message: string,
     *     url: string,
     *     goal_id: int,
     *     objective_id: int,
     *     roles: list<string>,
     *     action: string,
     *     actor_id: int
     * }
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
