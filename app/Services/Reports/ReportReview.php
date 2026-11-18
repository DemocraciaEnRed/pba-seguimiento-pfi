<?php

namespace App\Services\Reports;

use App\Goal;
use App\Report;
use App\User;
use App\Notifications\CompletedGoal;
use App\Notifications\NewReport;
use App\Notifications\ReportReviewNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReportReview
{
    public function __construct(private ReportAttachments $attachments) {}

    public function create(Goal $goal, User $actor, Request $request): Report
    {
        Gate::forUser($actor)->authorize('create', [Report::class, $goal]);
        $data = $this->validate($request, $goal);
        try {
            return DB::transaction(function () use ($goal, $actor, $request, $data): Report {
                User::query()->lockForUpdate()->findOrFail($actor->id);
                $goal = Goal::whereKey($goal->id)->lockForUpdate()->firstOrFail();
                $actor->forgetAuthorizationContext();
                Gate::forUser($actor)->authorize('create', [Report::class, $goal]);
                $report = new Report();
                $report->goal()->associate($goal);
                $report->author()->associate($actor);
                $report->attendant()->associate($actor);
                $report->submitted_at = now();
                if ($actor->hasRole('admin') && $goal->visibility === 'internal') {
                    $report->review_status = ReviewStatus::Validated;
                    $report->validated_by = $actor->id;
                    $report->validated_at = now();
                }
                $this->fillProposal($report, $data);
                $changedFields = array_keys($report->getDirty());
                if ($request->hasFile('photos')) {
                    $changedFields[] = 'photos';
                }
                if ($request->hasFile('files')) {
                    $changedFields[] = 'files';
                }
                $this->validateProposal($report);
                $report->save();
                $this->attachments->store($report, $request->file('photos', []), $request->file('files', []));
                DB::afterCommit(fn () => $this->attachments->committed());
                if ($actor->hasRole('admin')) {
                    if ($goal->visibility === 'public') {
                        $this->apply($report, $actor, ReviewStatus::Approved);
                        $changedFields = array_values(array_unique([...$changedFields, ...array_keys($report->getDirty())]));
                        $report->save();
                        $this->publicationNotice($report);
                        $this->log($report, $actor, 'published', $changedFields);
                    } else {
                        $this->log($report, $actor, 'validated', $changedFields);
                    }
                } else {
                    $this->log($report, $actor, 'submitted', $changedFields);
                    $this->reviewNotice($report, 'submitted', $actor);
                }
                return $report;
            });
        } catch (Throwable $exception) {
            $this->attachments->cleanup();
            if ($exception instanceof UniqueConstraintViolationException && str_contains($exception->getMessage(), 'reports_progress_period_key_unique')) {
                throw ValidationException::withMessages(['goal_period_id' => 'El período ya tiene un reporte o una propuesta reservada.']);
            }
            throw $exception;
        }
    }

    public function update(Report $report, User $actor, Request $request): Report
    {
        return $this->mutate($report, $actor, function (Report $locked) use ($request): void {
            $data = $this->validate($request, $locked->goal, $locked);
            $this->checkVersion($locked, $request->integer('review_version'));
            $this->fillProposal($locked, $data);
            $this->validateProposal($locked);
        }, 'edited');
    }

    /** @param \Closure(Report): void $change */
    public function mutate(Report $report, User $actor, \Closure $change, string $action = 'edited', array $changedFields = []): Report
    {
        return DB::transaction(function () use ($report, $actor, $change, $action, $changedFields): Report {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $goal = Goal::whereKey($report->goal_id)->lockForUpdate()->firstOrFail();
            $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('goal', $goal);
            if ($locked->goal_period_id) {
                $goal->periods()->whereKey($locked->goal_period_id)->lockForUpdate()->firstOrFail();
            }
            if ($locked->milestone_achieved) {
                $goal->milestones()->whereKey($locked->milestone_achieved)->lockForUpdate()->firstOrFail();
            }
            $actor->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize('update', $locked);
            $change($locked);
            $changedFields = array_values(array_unique([...$changedFields, ...array_keys($locked->getDirty())]));
            $locked->review_version++;
            $locked->save();
            $this->log($locked, $actor, $action, $changedFields);
            return $locked;
        });
    }

    public function addAttachments(Report $report, User $actor, Request $request): void
    {
        $request->validate([
            'photo' => 'nullable|image|max:102400',
            'file' => 'nullable|file|max:102400',
            'review_version' => 'required|integer|min:1',
        ]);
        try {
            $this->mutate($report, $actor, function (Report $locked) use ($request): void {
                $this->checkVersion($locked, $request->integer('review_version'));
                $this->attachments->store($locked, $request->hasFile('photo') ? [$request->file('photo')] : [], $request->hasFile('file') ? [$request->file('file')] : []);
                DB::afterCommit(fn () => $this->attachments->committed());
            }, 'attachments-added', array_values(array_filter([
                $request->hasFile('photo') ? 'photos' : null,
                $request->hasFile('file') ? 'files' : null,
            ])));
        } catch (Throwable $exception) {
            $this->attachments->cleanup();
            throw $exception;
        }
    }

    public function transition(Report $report, User $actor, Request $request, string $action): Report
    {
        $request->validate([
            'review_version' => 'required|integer|min:1',
            'context' => [Rule::requiredIf(in_array($action, ['validated', 'published', 'closed'], true)), 'nullable', 'string'],
            'return_reason' => [Rule::requiredIf($action === 'returned'), 'nullable', 'string', 'max:2000'],
            'notify' => 'nullable|in:true',
        ]);
        return $this->mutate($report, $actor, function (Report $locked) use ($actor, $request, $action): void {
            $this->checkVersion($locked, $request->integer('review_version'));
            if ($action === 'resubmitted') {
                if ($locked->review_status !== ReviewStatus::Returned) {
                    throw ValidationException::withMessages(['review' => 'El estado del reporte cambió. Recargá la página antes de continuar.']);
                }
                $this->validateProposal($locked);
                $locked->review_status = ReviewStatus::Pending;
                $locked->submitted_at = now();
            } elseif ($action === 'returned') {
                Gate::forUser($actor)->authorize('return', $locked);
                $locked->review_status = ReviewStatus::Returned;
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = now();
                $locked->return_reason = $request->input('return_reason');
            } elseif ($action === 'validated') {
                Gate::forUser($actor)->authorize('validate', $locked);
                $this->assertReviewContext($locked, $request);
                $locked->review_status = ReviewStatus::Validated;
                $locked->validated_by = $actor->id;
                $locked->validated_at = now();
            } elseif ($action === 'published') {
                Gate::forUser($actor)->authorize('publish', $locked);
                $this->assertReviewContext($locked, $request);
                $locked->notify_subscribers = $request->boolean('notify');
                $this->apply($locked, $actor, ReviewStatus::Approved);
                $this->publicationNotice($locked);
            } elseif ($action === 'closed') {
                Gate::forUser($actor)->authorize('close', $locked);
                $this->assertReviewContext($locked, $request);
                $this->apply($locked, $actor, ReviewStatus::Validated);
                $locked->closed_by = $actor->id;
                $locked->closed_at = now();
            } else {
                throw new \InvalidArgumentException("Acción de revisión desconocida: {$action}");
            }
            $locked->save();
            $this->reviewNotice($locked, $action, $actor);
        }, $action);
    }

    public function delete(Report $report, User $actor): void
    {
        $this->mutate($report, $actor, function (Report $locked) use ($actor): void {
            Gate::forUser($actor)->authorize('delete', $locked);
            $locked->delete();
        }, 'deleted');
    }

    public function checkVersion(Report $report, int $version): void
    {
        if ($report->review_version !== $version) {
            throw ValidationException::withMessages(['review' => 'El reporte cambió. Recargá la página para revisar la última versión.']);
        }
    }

    public function context(Report $report): string
    {
        $goal = $report->goal;
        $milestone = $report->milestone;
        $period = $report->period;
        return hash('sha256', json_encode([$goal->getAttributes(), $milestone?->getAttributes(), $period?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function validate(Request $request, Goal $goal, ?Report $report = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:550',
            'content' => 'required|string',
            'date' => 'required|date',
            'type' => [Rule::requiredIf($report === null), Rule::in($goal->acceptsProgressReports() ? ['post', 'progress', 'milestone'] : ['post', 'milestone'])],
            'status' => ['nullable', Rule::in(array_keys(Goal::STATUSES))],
            'progress' => [Rule::requiredIf(($report?->type ?? $request->input('type')) === 'progress' && $goal->isSimple()), 'nullable', 'numeric', 'gt:0'],
            'goal_period_id' => [Rule::requiredIf($report === null && $goal->isPeriodic() && $request->input('type') === 'progress'), 'nullable', 'integer'],
            'measured_value' => [Rule::requiredIf($goal->isPeriodic() && ($report?->type ?? $request->input('type')) === 'progress'), 'nullable', 'numeric', 'min:0'],
            'milestone' => [Rule::requiredIf(($report?->type ?? $request->input('type')) === 'milestone'), 'nullable', 'integer'],
            'milestone_date' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'required|string|max:100',
            'photos' => 'array',
            'photos.*' => 'required|image|max:102400',
            'files' => 'array',
            'files.*' => 'required|file|max:102400',
            'notify' => 'nullable|in:true',
            'review_version' => [Rule::requiredIf($report !== null), 'integer', 'min:1'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fillProposal(Report $report, array $data): void
    {
        foreach (['title', 'content', 'date'] as $field) {
            $report->{$field} = $data[$field];
        }
        $report->type = $report->exists ? $report->type : $data['type'];
        $report->tags = $data['tags'] ?? [];
        $report->status = $data['status'] ?? null;
        $report->notify_subscribers = ($data['notify'] ?? null) === 'true';
        if ($report->type === 'progress') {
            if ($report->goal->isPeriodic()) {
                if (! $report->exists) {
                    $report->goal_period_id = $data['goal_period_id'];
                }
                $report->measured_value = $data['measured_value'];
            } else {
                $report->progress = $data['progress'];
            }
        } elseif ($report->type === 'milestone') {
            $report->milestone_achieved = $data['milestone'];
            $report->proposed_milestone_date = $data['milestone_date'] ?? $data['date'];
        }
        $report->unsetRelation('milestone');
        $report->unsetRelation('period');
    }

    private function validateProposal(Report $report): void
    {
        $goal = $report->goal;
        if ($report->type === 'milestone') {
            $milestone = $goal->milestones()->whereKey($report->milestone_achieved)->lockForUpdate()->first();
            if (! $milestone || (! $report->isApproved() && $milestone->completed !== null)) {
                throw ValidationException::withMessages(['milestone' => 'El hito no pertenece a esta meta o ya fue completado.']);
            }
        }
        if ($report->type === 'progress' && $goal->isPeriodic()) {
            $period = $goal->periods()->whereKey($report->goal_period_id)->lockForUpdate()->first();
            if (! $period) {
                throw ValidationException::withMessages(['goal_period_id' => 'El período seleccionado no pertenece a esta meta.']);
            }
            $reservation = $period->reservedReport;
            if ($reservation && $reservation->id !== $report->id) {
                throw ValidationException::withMessages(['goal_period_id' => \App\Http\Controllers\GoalPanelController::PERIOD_ALREADY_REPORTED_MESSAGE]);
            }
            if (! $report->isApproved() && ! $period->state()->isReportable()) {
                throw ValidationException::withMessages(['goal_period_id' => 'El período no admite reportes de avance ('.$period->state()->label().').']);
            }
        } elseif ($report->type === 'progress' && ! $goal->isSimple()) {
            throw ValidationException::withMessages(['type' => 'La meta no admite este reporte de avance.']);
        }
    }

    private function apply(Report $report, User $actor, ReviewStatus $status): void
    {
        if ($report->isConsolidated()) {
            throw ValidationException::withMessages(['review' => 'El reporte ya fue consolidado.']);
        }
        $this->validateProposal($report);
        $goal = $report->goal;
        if ($report->status !== null) {
            $report->previous_status = $goal->status;
            $goal->status = $report->status;
        }
        if ($report->type === 'progress' && $goal->isSimple()) {
            $report->previous_progress = $goal->indicator_progress;
            $goal->indicator_progress += $report->progress;
        }
        if ($report->type === 'milestone') {
            $milestone = $goal->milestones()->whereKey($report->milestone_achieved)->lockForUpdate()->firstOrFail();
            $milestone->completed = $report->proposed_milestone_date;
            $milestone->save();
        }
        $goal->save();
        $report->review_status = $status;
        if ($status === ReviewStatus::Approved) {
            $report->reviewed_by = $actor->id;
            $report->reviewed_at = now();
        }
    }

    private function assertReviewContext(Report $report, Request $request): void
    {
        if (! hash_equals($this->context($report), (string) $request->input('context'))) {
            throw ValidationException::withMessages(['review' => 'La meta o el hito cambió. Recargá y revisá el impacto antes de continuar.']);
        }
    }

    private function publicationNotice(Report $report): void
    {
        $goal = $report->goal;
        if (! $goal->objective->hidden && $goal->visibility === 'public' && $report->notify_subscribers) {
            $notice = $report->type === 'progress' && $goal->isSimple() && $goal->indicator_progress >= $goal->indicator_goal
                ? new CompletedGoal($goal->objective, $goal, $report)
                : new NewReport($goal->objective, $goal, $report);
            Notification::send($goal->objective->subscribers, $notice->afterCommit());
        }
    }

    private function reviewNotice(Report $report, string $action, User $actor): void
    {
        $recipients = in_array($action, ['submitted', 'resubmitted'], true)
            ? User::whereHas('roles', fn ($roles) => $roles->where('name', 'admin'))->get()
            : collect([$action === 'returned' ? ($report->attendant ?? $report->author) : $report->author])
                ->filter(fn (User $recipient): bool => $report->goal->isVisibleTo($recipient));
        Notification::send($recipients, (new ReportReviewNotification($report, $action, $actor))->afterCommit());
    }

    /** @param list<string> $changedFields */
    private function log(Report $report, User $actor, string $action, array $changedFields = []): void
    {
        Log::channel('mysql')->info("Reporte [{$report->title}]: {$action} por [{$actor->fullname}]", [
            'report_id' => $report->id, 'goal_id' => $report->goal_id,
            'objective_id' => $report->goal->objective_id, 'author_id' => $report->author_id,
            'user_id' => $actor->id, 'action' => $action, 'review_status' => $report->review_status->value,
            'review_version' => $report->review_version, 'return_reason' => $report->return_reason,
            'changed_fields' => $changedFields,
        ]);
    }
}
