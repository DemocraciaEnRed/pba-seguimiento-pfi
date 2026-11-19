<?php

namespace App;

use App\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    /** @var list<string>|null */
    private ?array $authorizationRoles = null;

    /** @var list<array{id: int, objective_id: int, role: string}>|null */
    private ?array $authorizationGoals = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'surname', 'email', 'password','organization'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'email','password', 'remember_token', 'trace'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getFullnameAttribute(){
        return $this->surname . ', ' . $this->name;
    }

    public function roles()
    {
        return $this->belongsToMany('App\Role','role_user','user_id','role_id')->withTimestamps();
    }

    public function subscriptions($hidden = null)
    {
        $q = $this->belongsToMany('App\Objective','objective_subscriber','subscriber_id','objective_id')->withTimestamps();
        if(!is_null($hidden)){
            $q = $q->where('hidden',$hidden);
        }
        return $q;
    }
    
    public function comments()
    {
        return $this->hasMany('App\Comment');
    }

    public function reports()
    {
        return $this->hasMany('App\Report', 'author_id');
    }

    public function responsibleGoals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_user', 'user_id', 'goal_id')
            ->wherePivot('role', 'responsible');
    }

    public function reporterGoals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_user', 'user_id', 'goal_id')
            ->wherePivot('role', 'reporter');
    }

    public function assignedGoals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_user', 'user_id', 'goal_id')->distinct();
    }

    public function avatar()
    {
        return $this->morphOne('App\ImageFile', 'imageable');
    }

    public function pfiMembership(): HasOne
    {
        return $this->hasOne(PfiMembership::class);
    }

    public function pfiCategories(): BelongsToMany
    {
        return $this->belongsToMany(PfiCategory::class)->withTimestamps()->orderBy('name');
    }

    /**
     * Membership only makes the person selectable for assignments; it grants no access by itself.
     */
    public function isPfiMember(): bool
    {
        return $this->pfiMembership !== null;
    }

    public function scopePfiMembers(Builder $query): Builder
    {
        return $query->has('pfiMembership');
    }

    public function scopeMatchingNameOrEmail(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';

        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('surname', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhereRaw("CONCAT(name, ' ', surname) like ?", [$like])
            ->orWhereRaw("CONCAT(surname, ' ', name) like ?", [$like]));
    }
    
    public function authorizeRoles($roles)
    {
        abort_unless($this->hasAnyRole($roles), 401);    
        return true;
    }

    public function hasAnyRole($roles)
    {
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }
        } else {
            if ($this->hasRole($roles)) {
                return true; 
            }   
        }
        return false;
    }

    public function loadAuthorizationContext(): void
    {
        $this->authorizationRoles = $this->roles()->pluck('name')->all();
        $this->authorizationGoals = $this->assignedGoals()->withPivot('role')->get(['goals.id', 'goals.objective_id'])
            ->map(fn (Goal $goal): array => ['id' => $goal->id, 'objective_id' => $goal->objective_id, 'role' => $goal->pivot->role])
            ->all();
    }

    public function forgetAuthorizationContext(): void
    {
        $this->authorizationRoles = null;
        $this->authorizationGoals = null;
    }

    public function hasRole(string $role): bool
    {
        return $this->authorizationRoles !== null
            ? in_array($role, $this->authorizationRoles, true)
            : $this->roles()->where('name', $role)->exists();
    }

    public function hasAssignedGoalInObjective(int|string $objectiveId): bool
    {
        if ($this->authorizationGoals !== null) {
            return collect($this->authorizationGoals)->contains('objective_id', (int) $objectiveId);
        }

        return $this->assignedGoals()->where('goals.objective_id', $objectiveId)->exists();
    }

    public function isAssignedToGoal(int|string $goalId): bool
    {
        return $this->hasGoalAssignment($goalId);
    }

    public function isResponsibleForGoal(int|string $goalId): bool
    {
        return $this->hasGoalAssignment($goalId, 'responsible');
    }

    public function isReporterForGoal(int|string $goalId): bool
    {
        return $this->hasGoalAssignment($goalId, 'reporter');
    }

    private function hasGoalAssignment(int|string $goalId, ?string $role = null): bool
    {
        if ($this->authorizationGoals !== null) {
            foreach ($this->authorizationGoals as $assignment) {
                if ($assignment['id'] === (int) $goalId && ($role === null || $assignment['role'] === $role)) {
                    return true;
                }
            }

            return false;
        }

        return $this->assignedGoals()->where('goals.id', $goalId)
            ->when($role !== null, fn ($query) => $query->where('goal_user.role', $role))
            ->exists();
    }
}
