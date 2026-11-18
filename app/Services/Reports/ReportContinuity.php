<?php

namespace App\Services\Reports;

use App\Goal;
use App\Report;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReportContinuity
{
    public function pendingForRemoval(User $member, ?Goal $goal = null, ?string $role = null): Builder
    {
        return Report::query()
            ->whereIn('review_status', [ReviewStatus::Pending, ReviewStatus::Returned])
            ->whereHas('goal')
            ->where(fn (Builder $reports) => $reports
                ->where('attendant_id', $member->id)
                ->orWhere(fn (Builder $unassigned) => $unassigned->whereNull('attendant_id')->where('author_id', $member->id)))
            ->when($member->hasRole('admin'), fn (Builder $reports) => $reports->whereKey([]))
            ->when($goal !== null, fn (Builder $reports) => $reports->where('goal_id', $goal->id))
            ->when($role === 'reporter', fn (Builder $reports) => $reports
                ->whereDoesntHave('goal.responsibles', fn (Builder $users) => $users->whereKey($member->id)))
            ->when($role === 'responsible', fn (Builder $reports) => $reports
                ->whereDoesntHave('goal.reporters', fn (Builder $users) => $users->whereKey($member->id)));
    }

    /**
     * The caller locks participants (including destinations) by ID, then goals, and removes assignments in this transaction.
     *
     * @param array<int, int> $destinations
     */
    public function transferForRemoval(User $member, User $actor, array $destinations, ?Goal $goal = null, ?string $role = null): void
    {
        if ($goal !== null && $role === 'reporter') {
            Gate::forUser($actor)->authorize('manageReporters', $goal);
        } else {
            Gate::forUser($actor)->authorize('isAdmin');
        }

        $reports = $this->pendingForRemoval($member, $goal, $role)->orderBy('id')->lockForUpdate()->get();
        $reportIds = $reports->modelKeys();
        if (array_diff($reportIds, array_keys($destinations)) !== [] || array_diff(array_keys($destinations), $reportIds) !== []) {
            throw ValidationException::withMessages([
                'destinations' => 'Elegí quién continuará cada pendiente. Si los pendientes cambiaron, recargá la confirmación de baja.',
            ]);
        }

        foreach ($reports as $report) {
            $this->changeAttendant($report, $destinations[$report->id], $actor, $member);
        }
    }

    public function reassign(Report $report, User $actor, int $attendantId, int $version): void
    {
        DB::transaction(function () use ($report, $actor, $attendantId, $version): void {
            User::query()->whereKey([$actor->id, $attendantId])->orderBy('id')->lockForUpdate()->get();
            $goal = Goal::query()->lockForUpdate()->findOrFail($report->goal_id);
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            $actor->forgetAuthorizationContext();
            Gate::forUser($actor)->authorize('manageReporters', $goal);
            app(ReportReview::class)->checkVersion($locked, $version);
            $this->changeAttendant($locked, $attendantId, $actor);
        });
    }

    private function changeAttendant(Report $report, int $attendantId, User $actor, ?User $departing = null): void
    {
        if (! in_array($report->review_status, [ReviewStatus::Pending, ReviewStatus::Returned], true)) {
            throw ValidationException::withMessages(['destinations' => 'Solo se pueden reasignar reportes pendientes no validados.']);
        }

        $eligible = $attendantId !== $departing?->id && User::query()->pfiMembers()->whereKey($attendantId)
            ->whereHas('reporterGoals', fn (Builder $goals) => $goals->whereKey($report->goal_id))->exists();
        if (! $eligible) {
            throw ValidationException::withMessages([
                "destinations.{$report->id}" => 'El destino debe ser otro Reportero vigente de esta meta e integrante PFI.',
            ]);
        }

        $previousAttendantId = $report->attendant_id;
        $report->attendant_id = $attendantId;
        $report->review_version++;
        $report->save();
        Log::channel('mysql')->info("Reporte [{$report->title}]: atención reasignada por [{$actor->fullname}]", [
            'report_id' => $report->id, 'goal_id' => $report->goal_id, 'author_id' => $report->author_id,
            'previous_attendant_id' => $previousAttendantId, 'attendant_id' => $attendantId,
            'user_id' => $actor->id, 'action' => 'attendant-reassigned', 'review_version' => $report->review_version,
            'changed_fields' => ['attendant_id'],
        ]);
    }
}
