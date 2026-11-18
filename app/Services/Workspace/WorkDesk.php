<?php

namespace App\Services\Workspace;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use App\Services\Goals\GoalAudience;
use App\Services\Reports\ReviewStatus;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Derives the work desk from goal assignments and report states; it never grants access by itself.
 */
class WorkDesk
{
    public const string ROLE_RESPONSIBLE = 'responsable';

    public const string ROLE_REPORTER = 'reportero';

    public const string TASK_REVIEW = 'revisar';

    public const string TASK_CORRECT = 'corregir';

    public const string TASK_PUBLISH = 'publicar';

    public const string TASK_CLOSE = 'cerrar';

    private const string WAITING_SINCE = "COALESCE(CASE reports.review_status WHEN 'pending' THEN reports.submitted_at WHEN 'returned' THEN reports.reviewed_at WHEN 'validated' THEN reports.validated_at END, reports.created_at)";

    public function __construct(private GoalAudience $goalAudience) {}

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_RESPONSIBLE => 'Responsable',
            self::ROLE_REPORTER => 'Reportero',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function taskOptions(): array
    {
        return [
            self::TASK_REVIEW => 'Revisar',
            self::TASK_CORRECT => 'Corregir',
            self::TASK_PUBLISH => 'Publicar',
            self::TASK_CLOSE => 'Cerrar y consolidar',
        ];
    }

    /**
     * The next step a report needs, regardless of who can take it.
     */
    public static function taskType(Report $report): string
    {
        return match ($report->review_status) {
            ReviewStatus::Returned => self::TASK_CORRECT,
            ReviewStatus::Validated => $report->goal->visibility === 'internal' ? self::TASK_CLOSE : self::TASK_PUBLISH,
            default => self::TASK_REVIEW,
        };
    }

    /**
     * Drops the objective when it does not belong to the chosen category.
     *
     * @param  array{rol?: mixed, eje?: mixed, objetivo?: mixed, tipo?: mixed}  $filters
     * @return array{rol: ?string, eje: ?int, objetivo: ?int, tipo: ?string}
     */
    public function normalize(array $filters): array
    {
        $categoryId = $this->positiveInt($filters['eje'] ?? null);
        $objectiveId = $this->positiveInt($filters['objetivo'] ?? null);

        if ($objectiveId && $categoryId && ! Objective::whereKey($objectiveId)->whereHas('strategicObjective', fn (Builder $query) => $query->where('category_id', $categoryId))->exists()) {
            $objectiveId = null;
        }

        $role = $filters['rol'] ?? null;
        $type = $filters['tipo'] ?? null;

        return [
            'rol' => is_string($role) && array_key_exists($role, self::roleOptions()) ? $role : null,
            'eje' => $categoryId,
            'objetivo' => $objectiveId,
            'tipo' => is_string($type) && array_key_exists($type, self::taskOptions()) ? $type : null,
        ];
    }

    /**
     * Assigned goals listed once, with the roles held and the number of tasks that require the user's action.
     *
     * @param  array{rol?: ?string, eje?: ?int, objetivo?: ?int}  $filters
     */
    public function goals(User $user, array $filters = []): Builder
    {
        return $this->goalAudience->workspaceQuery($user)
            ->with('objective.strategicObjective.category')
            ->withExists([
                'responsibles as is_responsible' => fn (Builder $users) => $users->whereKey($user->getKey()),
                'reporters as is_reporter' => fn (Builder $users) => $users->whereKey($user->getKey()),
            ])
            ->withCount(['reports as tasks_count' => fn (Builder $reports) => $this->whereRequiresActionFrom($reports, $user)])
            ->tap(fn (Builder $goals) => $this->whereInHierarchy($goals, $filters))
            ->when(($filters['rol'] ?? null) === self::ROLE_RESPONSIBLE, fn (Builder $goals) => $goals->whereIn('goals.id', $this->assignedGoalIds($user, 'responsible')))
            ->when(($filters['rol'] ?? null) === self::ROLE_REPORTER, fn (Builder $goals) => $goals->whereIn('goals.id', $this->assignedGoalIds($user, 'reporter')))
            ->orderBy(Objective::select('title')->whereColumn('objectives.id', 'goals.objective_id'))
            ->orderBy('goals.title')
            ->orderBy('goals.id');
    }

    /**
     * Pending reviews for Responsables and returned reports the user attends.
     *
     * @param  array{rol?: ?string, eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    public function actionRequired(User $user, array $filters = []): Builder
    {
        return $this->personalTasks($user, $filters)
            ->where(fn (Builder $reports) => $this->whereRequiresActionFrom($reports, $user));
    }

    /**
     * Reports the user follows whose next step belongs to someone else.
     *
     * @param  array{rol?: ?string, eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    public function waiting(User $user, array $filters = []): Builder
    {
        $isAdmin = $user->hasRole('admin');

        return $this->personalTasks($user, $filters)->where(function (Builder $reports) use ($user, $isAdmin): void {
            $reports
                ->where(fn (Builder $pending) => $pending
                    ->where('reports.review_status', ReviewStatus::Pending)
                    ->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'reporter'))
                    ->whereNotIn('reports.goal_id', $this->assignedGoalIds($user, 'responsible'))
                    ->where(fn (Builder $own) => $own->where('reports.author_id', $user->id)->orWhere('reports.attendant_id', $user->id))
                    ->when($isAdmin, fn (Builder $pending) => $pending->whereHas('goal.responsibles')))
                ->orWhere(fn (Builder $returned) => $returned
                    ->where('reports.review_status', ReviewStatus::Returned)
                    ->where(fn (Builder $followed) => $this->whereFollowedBy($followed, $user))
                    ->whereRaw('COALESCE(reports.attendant_id, reports.author_id) <> ?', [$user->id]))
                ->when(! $isAdmin, fn (Builder $reports) => $reports->orWhere(fn (Builder $validated) => $validated
                    ->where('reports.review_status', ReviewStatus::Validated)
                    ->whereNull('reports.closed_at')
                    ->where(fn (Builder $followed) => $this->whereFollowedBy($followed, $user))));
        });
    }

    /**
     * Validated reports to publish or close, and pending reports of goals without Responsables.
     *
     * @param  array{eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    public function administrationTasks(array $filters = []): Builder
    {
        return $this->tasks($filters)->where(fn (Builder $reports) => $reports
            ->where(fn (Builder $validated) => $validated
                ->where('reports.review_status', ReviewStatus::Validated)
                ->whereNull('reports.closed_at'))
            ->orWhere(fn (Builder $pending) => $pending
                ->where('reports.review_status', ReviewStatus::Pending)
                ->whereDoesntHave('goal.responsibles')));
    }

    /**
     * Ongoing goals without Responsables or without Reporteros.
     *
     * @param  array{eje?: ?int, objetivo?: ?int}  $filters
     */
    public function uncoveredGoals(array $filters = []): Builder
    {
        return Goal::query()
            ->where('goals.status', 'ongoing')
            ->where(fn (Builder $goals) => $goals->doesntHave('responsibles')->orDoesntHave('reporters'))
            ->with('objective.strategicObjective.category')
            ->withCount(['responsibles', 'reporters'])
            ->tap(fn (Builder $goals) => $this->whereInHierarchy($goals, $filters))
            ->orderBy(Objective::select('title')->whereColumn('objectives.id', 'goals.objective_id'))
            ->orderBy('goals.title')
            ->orderBy('goals.id');
    }

    /**
     * Pending or returned reports whose attendant is neither assigned to the goal nor an administrator.
     *
     * @param  array{eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    public function orphanedReports(array $filters = []): Builder
    {
        return $this->tasks($filters)
            ->whereIn('reports.review_status', [ReviewStatus::Pending, ReviewStatus::Returned])
            ->whereNotExists(fn (QueryBuilder $assignments) => $assignments
                ->selectRaw('1')
                ->from('goal_user')
                ->whereColumn('goal_user.goal_id', 'reports.goal_id')
                ->whereRaw('goal_user.user_id = COALESCE(reports.attendant_id, reports.author_id)'))
            ->whereNotExists(fn (QueryBuilder $administrators) => $administrators
                ->selectRaw('1')
                ->from('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.name', 'admin')
                ->whereRaw('role_user.user_id = COALESCE(reports.attendant_id, reports.author_id)'));
    }

    /**
     * Unfiltered counters for the panel summary and the administration dashboard.
     *
     * @return array{goals: int, action: int, waiting: int, administration: ?int, alerts: ?int}
     */
    public function summary(User $user): array
    {
        return [
            'goals' => $this->goalAudience->workspaceQuery($user)->whereHas('objective')->count(),
            'action' => $this->actionRequired($user)->count(),
            'waiting' => $this->waiting($user)->count(),
            ...($user->hasRole('admin') ? $this->administrationSummary() : ['administration' => null, 'alerts' => null]),
        ];
    }

    /**
     * @return array{administration: int, alerts: int}
     */
    public function administrationSummary(): array
    {
        return [
            'administration' => $this->administrationTasks()->count(),
            'alerts' => $this->uncoveredGoals()->count() + $this->orphanedReports()->count(),
        ];
    }

    /**
     * Options for the Eje → Objetivo selects; non-administrators only see their assigned hierarchy.
     *
     * @param  array{eje: ?int}  $filters
     * @return array{categories: Collection<int, Category>, objectives: Collection<int, Objective>}
     */
    public function hierarchyOptions(User $user, array $filters): array
    {
        $scoped = ! $user->hasRole('admin');
        $workspaceGoalIds = fn () => $this->goalAudience->workspaceQuery($user)->select('goals.id');

        return [
            'categories' => Category::query()
                ->when($scoped, fn (Builder $categories) => $categories->whereHas('strategicObjectives.objectives.goals', fn (Builder $goals) => $goals->whereIn('goals.id', $workspaceGoalIds())))
                ->orderBy('order')
                ->get(['id', 'title']),
            'objectives' => Objective::query()
                ->when($scoped, fn (Builder $objectives) => $objectives->whereHas('goals', fn (Builder $goals) => $goals->whereIn('goals.id', $workspaceGoalIds())))
                ->when($filters['eje'], fn (Builder $objectives, int $categoryId) => $objectives->whereHas('strategicObjective', fn (Builder $strategic) => $strategic->where('category_id', $categoryId)))
                ->orderBy('title')
                ->get(['id', 'title']),
        ];
    }

    /**
     * @param  array{rol?: ?string, eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    private function personalTasks(User $user, array $filters): Builder
    {
        return $this->tasks($filters)
            ->when(($filters['rol'] ?? null) === self::ROLE_RESPONSIBLE, fn (Builder $reports) => $reports->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'responsible')))
            ->when(($filters['rol'] ?? null) === self::ROLE_REPORTER, fn (Builder $reports) => $reports->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'reporter')));
    }

    /**
     * Reports of existing goals and objectives, oldest waiting first.
     *
     * @param  array{eje?: ?int, objetivo?: ?int, tipo?: ?string}  $filters
     */
    private function tasks(array $filters): Builder
    {
        $type = $filters['tipo'] ?? null;

        return Report::query()
            ->select('reports.*')
            ->selectRaw(self::WAITING_SINCE.' as waiting_since')
            ->withCasts(['waiting_since' => 'datetime'])
            ->with([
                'goal' => fn ($goals) => $goals->withCount('responsibles'),
                'goal.objective.strategicObjective.category',
                'author',
                'attendant',
            ])
            ->whereHas('goal', fn (Builder $goals) => $this->whereInHierarchy($goals, $filters))
            ->when($type === self::TASK_REVIEW, fn (Builder $reports) => $reports->where('reports.review_status', ReviewStatus::Pending))
            ->when($type === self::TASK_CORRECT, fn (Builder $reports) => $reports->where('reports.review_status', ReviewStatus::Returned))
            ->when(in_array($type, [self::TASK_PUBLISH, self::TASK_CLOSE], true), fn (Builder $reports) => $reports
                ->where('reports.review_status', ReviewStatus::Validated)
                ->whereNull('reports.closed_at')
                ->whereHas('goal', fn (Builder $goals) => $goals->where('visibility', $type === self::TASK_PUBLISH ? 'public' : 'internal')))
            ->orderByRaw(self::WAITING_SINCE)
            ->orderBy('reports.id');
    }

    /**
     * Pending reviews in goals the user is Responsable for, and returned reports the user attends.
     */
    private function whereRequiresActionFrom(Builder $reports, User $user): void
    {
        $reports
            ->where(fn (Builder $pending) => $pending
                ->where('reports.review_status', ReviewStatus::Pending)
                ->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'responsible')))
            ->orWhere(fn (Builder $returned) => $returned
                ->where('reports.review_status', ReviewStatus::Returned)
                ->whereIn('reports.goal_id', $this->assignedGoalIds($user))
                ->whereRaw('COALESCE(reports.attendant_id, reports.author_id) = ?', [$user->id]));
    }

    /**
     * Mirrors who can follow a report in management: its goal's Responsables, or the Reportero who wrote or attends it.
     */
    private function whereFollowedBy(Builder $reports, User $user): void
    {
        $reports
            ->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'responsible'))
            ->orWhere(fn (Builder $own) => $own
                ->whereIn('reports.goal_id', $this->assignedGoalIds($user, 'reporter'))
                ->where(fn (Builder $mine) => $mine->where('reports.author_id', $user->id)->orWhere('reports.attendant_id', $user->id)));
    }

    /**
     * @param  array{eje?: ?int, objetivo?: ?int}  $filters
     */
    private function whereInHierarchy(Builder $goals, array $filters): void
    {
        $goals
            ->whereHas('objective')
            ->when($filters['objetivo'] ?? null, fn (Builder $goals, int $objectiveId) => $goals->where('goals.objective_id', $objectiveId))
            ->when($filters['eje'] ?? null, fn (Builder $goals, int $categoryId) => $goals->whereHas('objective.strategicObjective', fn (Builder $strategic) => $strategic->where('category_id', $categoryId)));
    }

    private function assignedGoalIds(User $user, ?string $role = null): QueryBuilder
    {
        return DB::table('goal_user')
            ->select('goal_id')
            ->where('user_id', $user->id)
            ->when($role !== null, fn (QueryBuilder $assignments) => $assignments->where('role', $role));
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? null : $value;
    }
}
