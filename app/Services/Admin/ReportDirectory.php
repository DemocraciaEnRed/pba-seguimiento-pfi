<?php

namespace App\Services\Admin;

use App\Category;
use App\Goal;
use App\Objective;
use App\Report;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class ReportDirectory
{
    public const string WITH_COMMENTS = 'con';

    public const string WITHOUT_COMMENTS = 'sin';

    public const string SORT_RECENT = 'recientes';

    public const string SORT_OLDEST = 'antiguos';

    public const string SORT_COMMENTED = 'comentados';

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_RECENT => 'Más recientes',
            self::SORT_OLDEST => 'Más antiguos',
            self::SORT_COMMENTED => 'Más comentados',
        ];
    }

    /**
     * Drops the objective when it does not belong to the chosen category, and the goal when it does not belong to the chosen objective.
     *
     * @param  array{q?: ?string, eje?: mixed, objetivo?: mixed, meta?: mixed, desde?: ?string, hasta?: ?string, autor?: ?string, comentarios?: ?string, orden?: ?string}  $filters
     * @return array{q: ?string, eje: ?int, objetivo: ?int, meta: ?int, desde: ?string, hasta: ?string, autor: ?string, comentarios: ?string, orden: ?string}
     */
    public function normalize(array $filters): array
    {
        $categoryId = $this->positiveInt($filters['eje'] ?? null);
        $objectiveId = $this->positiveInt($filters['objetivo'] ?? null);
        $goalId = $this->positiveInt($filters['meta'] ?? null);

        if ($objectiveId && $categoryId && ! Objective::whereKey($objectiveId)->whereHas('strategicObjective', fn (Builder $query) => $query->where('category_id', $categoryId))->exists()) {
            $objectiveId = null;
        }
        if ($goalId && (! $objectiveId || ! Goal::whereKey($goalId)->where('objective_id', $objectiveId)->exists())) {
            $goalId = null;
        }

        return [
            'q' => $this->text($filters['q'] ?? null),
            'eje' => $categoryId,
            'objetivo' => $objectiveId,
            'meta' => $goalId,
            'desde' => $this->date($filters['desde'] ?? null),
            'hasta' => $this->date($filters['hasta'] ?? null),
            'autor' => $this->text($filters['autor'] ?? null),
            'comentarios' => in_array($filters['comentarios'] ?? null, [self::WITH_COMMENTS, self::WITHOUT_COMMENTS], true) ? $filters['comentarios'] : null,
            'orden' => array_key_exists($filters['orden'] ?? '', self::sortOptions()) ? $filters['orden'] : null,
        ];
    }

    /**
     * Reports of existing goals and objectives (hidden objectives included) filtered by title, hierarchy, upload date, author and comments.
     *
     * @param  array{q?: ?string, eje?: mixed, objetivo?: mixed, meta?: mixed, desde?: ?string, hasta?: ?string, autor?: ?string, comentarios?: ?string, orden?: ?string}  $filters
     */
    public function search(array $filters = []): Builder
    {
        $filters = $this->normalize($filters);
        $sort = $filters['orden'];

        return Report::query()
            ->with(['goal.objective.strategicObjective.category', 'author'])
            ->withCount(['comments', 'positiveTestimonies'])
            ->whereHas('goal.objective')
            ->when($filters['q'], fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['meta'], fn (Builder $query, int $goalId) => $query->where('goal_id', $goalId))
            ->when($filters['objetivo'], fn (Builder $query, int $objectiveId) => $query->whereHas('goal', fn (Builder $goals) => $goals->where('objective_id', $objectiveId)))
            ->when($filters['eje'], fn (Builder $query, int $categoryId) => $query->whereHas('goal.objective.strategicObjective', fn (Builder $strategic) => $strategic->where('category_id', $categoryId)))
            ->when($filters['desde'], fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['hasta'], fn (Builder $query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->when($filters['autor'], function (Builder $query, string $author): void {
                $like = '%'.$author.'%';
                $query->whereHas('author', fn (Builder $users) => $users
                    ->where('name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("CONCAT(name, ' ', surname) like ?", [$like])
                    ->orWhereRaw("CONCAT(surname, ' ', name) like ?", [$like]));
            })
            ->when($filters['comentarios'] === self::WITH_COMMENTS, fn (Builder $query) => $query->has('comments'))
            ->when($filters['comentarios'] === self::WITHOUT_COMMENTS, fn (Builder $query) => $query->doesntHave('comments'))
            ->when($sort === self::SORT_COMMENTED, fn (Builder $query) => $query->orderByDesc('comments_count'))
            ->when($sort === self::SORT_OLDEST, fn (Builder $query) => $query->orderBy('date')->orderBy('id'))
            ->when($sort !== self::SORT_OLDEST, fn (Builder $query) => $query->orderByDesc('date')->orderByDesc('id'));
    }

    /**
     * Options for the chained Eje → Objetivo → Meta selects.
     *
     * @param  array{eje: ?int, objetivo: ?int}  $filters
     * @return array{categories: Collection<int, Category>, objectives: Collection<int, Objective>, goals: Collection<int, Goal>}
     */
    public function hierarchyOptions(array $filters): array
    {
        return [
            'categories' => Category::orderBy('order')->get(['id', 'title']),
            'objectives' => Objective::query()
                ->when($filters['eje'], fn (Builder $query, int $categoryId) => $query->whereHas('strategicObjective', fn (Builder $strategic) => $strategic->where('category_id', $categoryId)))
                ->orderBy('title')
                ->get(['id', 'title']),
            'goals' => $filters['objetivo']
                ? Goal::where('objective_id', $filters['objetivo'])->orderBy('title')->get(['id', 'title'])
                : new Collection(),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? null : $value;
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
