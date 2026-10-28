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
        abort_unless($actor->hasRole('admin') || $actor->isReporterObjective($goal->objective_id) || $actor->isManagerObjective($goal->objective_id), 403);
        $data = $this->validate($request, $goal);
        try {
            return DB::transaction(function () use ($goal, $actor, $request, $data): Report {
                $goal = Goal::whereKey($goal->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->hasRole('admin') || $actor->isReporterObjective($goal->objective_id) || $actor->isManagerObjective($goal->objective_id), 403);
                $report = new Report();
                $report->goal()->associate($goal);
                $report->author()->associate($actor);
                $report->submitted_at = now();
                $this->fillProposal($report, $data);
                $this->validateProposal($report);
                $report->save();
                $this->attachments->store($report, $request->file('photos', []), $request->file('files', []));
                DB::afterCommit(fn () => $this->attachments->committed());
                if ($actor->hasRole('admin')) {
                    $this->apply($report, $actor);
                    $this->publicationNotice($report);
                    $this->log($report, $actor, 'published');
                } else {
                    $this->log($report, $actor, 'submitted');
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
    public function mutate(Report $report, User $actor, \Closure $change, string $action = 'edited'): Report
    {
        return DB::transaction(function () use ($report, $actor, $change, $action): Report {
            $goal = Goal::whereKey($report->goal_id)->lockForUpdate()->firstOrFail();
            $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('goal', $goal);
            if ($locked->goal_period_id) {
                $goal->periods()->whereKey($locked->goal_period_id)->lockForUpdate()->firstOrFail();
            }
            if ($locked->milestone_achieved) {
                $goal->milestones()->whereKey($locked->milestone_achieved)->lockForUpdate()->firstOrFail();
            }
            abort_unless($locked->canManage($actor), 403, 'No autorizado');
            $change($locked);
            $locked->review_version++;
            $locked->save();
            $this->log($locked, $actor, $action);
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
            }, 'attachments-added');
        } catch (Throwable $exception) {
            $this->attachments->cleanup();
            throw $exception;
        }
    }

    public function transition(Report $report, User $actor, Request $request, string $action): Report
    {
        $request->validate([
            'review_version' => 'required|integer|min:1',
            'context' => Rule::requiredIf($action === 'approved'),
            'return_reason' => [Rule::requiredIf($action === 'returned'), 'nullable', 'string', 'max:2000'],
            'notify' => 'nullable|in:true',
        ]);
        return $this->mutate($report, $actor, function (Report $locked) use ($actor, $request, $action): void {
            $this->checkVersion($locked, $request->integer('review_version'));
            $expected = $action === 'resubmitted' ? ReviewStatus::Returned : ReviewStatus::Pending;
            if ($locked->review_status !== $expected) {
                throw ValidationException::withMessages(['review' => 'El estado del reporte cambió. Recargá la página antes de continuar.']);
            }
            if ($action === 'resubmitted') {
                $this->validateProposal($locked);
                $locked->review_status = ReviewStatus::Pending;
                $locked->submitted_at = now();
            } else {
                abort_unless($actor->hasRole('admin'), 403);
                if ($action === 'approved') {
                    if (! hash_equals($this->context($locked), (string) $request->input('context'))) {
                        throw ValidationException::withMessages(['review' => 'La meta o el hito cambió. Recargá y revisá el impacto antes de aprobar.']);
                    }
                    $locked->notify_subscribers = $request->boolean('notify');
                    $this->apply($locked, $actor);
                    $this->publicationNotice($locked);
                } else {
                    $locked->review_status = ReviewStatus::Returned;
                    $locked->reviewed_by = $actor->id;
                    $locked->reviewed_at = now();
                    $locked->return_reason = $request->input('return_reason');
                }
            }
            $locked->save();
            $this->reviewNotice($locked, $action, $actor);
        }, $action);
    }

    public function delete(Report $report, User $actor): void
    {
        $this->mutate($report, $actor, function (Report $locked) use ($actor): void {
            abort_unless($locked->canDelete($actor), 403);
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

    private function apply(Report $report, User $actor): void
    {
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
        $report->review_status = ReviewStatus::Approved;
        $report->reviewed_by = $actor->id;
        $report->reviewed_at = now();
        $report->save();
    }

    private function publicationNotice(Report $report): void
    {
        $goal = $report->goal;
        if (! $goal->objective->hidden && $report->notify_subscribers) {
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
            : collect([$report->author]);
        Notification::send($recipients, (new ReportReviewNotification($report, $action, $actor))->afterCommit());
    }

    private function log(Report $report, User $actor, string $action): void
    {
        Log::channel('mysql')->info("Reporte [{$report->title}]: {$action} por [{$actor->fullname}]", [
            'report_id' => $report->id, 'goal_id' => $report->goal_id,
            'objective_id' => $report->goal->objective_id, 'author_id' => $report->author_id,
            'user_id' => $actor->id, 'action' => $action, 'review_status' => $report->review_status->value,
            'review_version' => $report->review_version, 'return_reason' => $report->return_reason,
        ]);
    }
}
