<?php

namespace App\Services\Admin;

use App\Category;
use App\Goal;
use App\Objective;
use App\StrategicObjective;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ObjectiveDirectory
{
    public const string VISIBLE = 'publico';

    public const string HIDDEN = 'oculto';

    public const string GOALS_DELAYED = 'con-no-cumplidas';

    public const string GOALS_NONE = 'sin-metas';

    public const string REPORTS_NONE = 'sin-reportes';

    public const string SORT_TITLE = 'titulo';

    public const string SORT_RECENT = 'recientes';

    public const string SORT_LAST_REPORT = 'ultimo-reporte';

    /**
     * @return array<string, string>
     */
    public static function goalOptions(): array
    {
        return [
            self::GOALS_DELAYED => 'Con metas no cumplidas',
            self::GOALS_NONE => 'Sin metas',
            self::REPORTS_NONE => 'Sin reportes',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_TITLE => 'Título',
            self::SORT_RECENT => 'Más recientes',
            self::SORT_LAST_REPORT => 'Último reporte',
        ];
    }

    /**
     * Drops the strategic objective when it does not belong to the chosen category.
     *
     * @param  array{q?: ?string, eje?: mixed, estrategico?: mixed, visibilidad?: ?string, metas?: ?string, orden?: ?string}  $filters
     * @return array{q: ?string, eje: ?int, estrategico: ?int, visibilidad: ?string, metas: ?string, orden: ?string}
     */
    public function normalize(array $filters): array
    {
        $categoryId = $this->positiveInt($filters['eje'] ?? null);
        $strategicObjectiveId = $this->positiveInt($filters['estrategico'] ?? null);

        if ($strategicObjectiveId && $categoryId && ! StrategicObjective::whereKey($strategicObjectiveId)->where('category_id', $categoryId)->exists()) {
            $strategicObjectiveId = null;
        }
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';

        return [
            'q' => $search === '' ? null : $search,
            'eje' => $categoryId,
            'estrategico' => $strategicObjectiveId,
            'visibilidad' => in_array($filters['visibilidad'] ?? null, [self::VISIBLE, self::HIDDEN], true) ? $filters['visibilidad'] : null,
            'metas' => array_key_exists($filters['metas'] ?? '', self::goalOptions()) ? $filters['metas'] : null,
            'orden' => array_key_exists($filters['orden'] ?? '', self::sortOptions()) ? $filters['orden'] : null,
        ];
    }

    /**
     * @param  array{q?: ?string, eje?: mixed, estrategico?: mixed, visibilidad?: ?string, metas?: ?string, orden?: ?string}  $filters
     */
    public function search(array $filters = []): Builder
    {
        $filters = $this->normalize($filters);
        $sort = $filters['orden'];

        return Objective::query()
            ->with('strategicObjective.category')
            ->withCount(['goals', 'reports'])
            ->addSelect(['members_count' => Goal::query()->join('goal_user', 'goal_user.goal_id', '=', 'goals.id')
                ->whereColumn('goals.objective_id', 'objectives.id')->selectRaw('count(distinct goal_user.user_id)')])
            ->withMax('reports as last_report_date', 'reports.date')
            ->when($filters['q'], fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['estrategico'], fn (Builder $query, int $strategicObjectiveId) => $query->where('strategic_objective_id', $strategicObjectiveId))
            ->when($filters['eje'], fn (Builder $query, int $categoryId) => $query->whereHas('strategicObjective', fn (Builder $strategic) => $strategic->where('category_id', $categoryId)))
            ->when($filters['visibilidad'] === self::VISIBLE, fn (Builder $query) => $query->where('hidden', false))
            ->when($filters['visibilidad'] === self::HIDDEN, fn (Builder $query) => $query->where('hidden', true))
            ->when($filters['metas'] === self::GOALS_DELAYED, fn (Builder $query) => $query->whereHas('goals', fn (Builder $goals) => $goals->where('status', 'delayed')))
            ->when($filters['metas'] === self::GOALS_NONE, fn (Builder $query) => $query->doesntHave('goals'))
            ->when($filters['metas'] === self::REPORTS_NONE, fn (Builder $query) => $query->doesntHave('reports'))
            ->when($sort === self::SORT_RECENT, fn (Builder $query) => $query->latest()->orderByDesc('id'))
            ->when($sort === self::SORT_LAST_REPORT, fn (Builder $query) => $query->orderByRaw('last_report_date IS NULL')->orderByDesc('last_report_date'))
            ->orderBy('title')
            ->orderBy('id');
    }

    /**
     * @param  array{eje: ?int}  $filters
     * @return array{categories: Collection<int, Category>, strategicObjectives: Collection<int, StrategicObjective>}
     */
    public function hierarchyOptions(array $filters): array
    {
        return [
            'categories' => Category::orderBy('order')->get(['id', 'title']),
            'strategicObjectives' => StrategicObjective::query()
                ->when($filters['eje'], fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'title']),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? null : $value;
    }
}
