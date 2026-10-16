<?php

namespace App\Services\Admin;

use App\ActionLog;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserDirectory
{
    public const string VERIFIED = 'si';

    public const string UNVERIFIED = 'no';

    public const string ROLE_ADMIN = 'admin';

    public const string ROLE_USER = 'usuario';

    public const string WITH_TEAM = 'con';

    public const string WITHOUT_TEAM = 'sin';

    public const string SORT_RECENT = 'recientes';

    public const string SORT_NAME = 'nombre';

    public const string SORT_OBJECTIVES = 'objetivos';

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_RECENT => 'Más recientes',
            self::SORT_NAME => 'Apellido y nombre',
            self::SORT_OBJECTIVES => 'Cantidad de objetivos',
        ];
    }

    /**
     * Users filtered by name, surname or email, verification, platform role and team membership.
     *
     * @param  array{q?: ?string, verificado?: ?string, rol?: ?string, equipo?: ?string, orden?: ?string}  $filters
     */
    public function search(array $filters = []): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $verified = $filters['verificado'] ?? null;
        $role = $filters['rol'] ?? null;
        $team = $filters['equipo'] ?? null;
        $sort = $filters['orden'] ?? null;
        $isAdmin = fn (Builder $query) => $query->where('name', 'admin');

        return User::query()
            ->with(['avatar', 'roles'])
            ->withCount(['objectives', 'subscriptions', 'comments', 'reports'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw("CONCAT(name, ' ', surname) like ?", [$like])
                    ->orWhereRaw("CONCAT(surname, ' ', name) like ?", [$like]));
            })
            ->when($verified === self::VERIFIED, fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($verified === self::UNVERIFIED, fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($role === self::ROLE_ADMIN, fn (Builder $query) => $query->whereHas('roles', $isAdmin))
            ->when($role === self::ROLE_USER, fn (Builder $query) => $query->whereDoesntHave('roles', $isAdmin))
            ->when($team === self::WITH_TEAM, fn (Builder $query) => $query->has('objectives'))
            ->when($team === self::WITHOUT_TEAM, fn (Builder $query) => $query->doesntHave('objectives'))
            ->when($sort === self::SORT_NAME, fn (Builder $query) => $query->orderBy('surname')->orderBy('name'))
            ->when($sort === self::SORT_OBJECTIVES, fn (Builder $query) => $query->orderByDesc('objectives_count')->orderBy('surname'))
            ->latest()
            ->orderByDesc('id');
    }

    /**
     * @return array{managed: int, reported: int, subscriptions: int, comments: int, reports: int}
     */
    public function profileCounts(User $user): array
    {
        return [
            'managed' => $user->objectives()->wherePivot('role', 'manager')->count(),
            'reported' => $user->objectives()->wherePivot('role', 'reporter')->count(),
            'subscriptions' => $user->subscriptions()->count(),
            'comments' => $user->comments()->count(),
            'reports' => $user->reports()->count(),
        ];
    }

    /**
     * Latest actions performed by the user, excluding notification delivery logs.
     *
     * @return Collection<int, ActionLog>
     */
    public function latestActions(User $user, int $limit = 5): Collection
    {
        return ActionLog::query()
            ->where('context->user_id', $user->id)
            ->where(fn (Builder $query) => $query->whereNull('context->type')->orWhere('context->type', '!=', 'notifications'))
            ->orderByDesc('record_datetime')
            ->orderByDesc('id')
            ->take($limit)
            ->get();
    }
}
