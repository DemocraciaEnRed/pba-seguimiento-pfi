<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Reports\ReviewStatus;

class Report extends Model
{
    /** @var array<string, string> */
    public const TYPES = [
        'post' => 'Novedad',
        'progress' => 'Avance',
        'milestone' => 'Hito',
    ];

    use SoftDeletes;

    protected $table = 'reports';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.
    protected $appends = ['type_label','status_label','previous_status_label','type_icon'];

    protected $casts = [
        'review_status' => ReviewStatus::class,
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'proposed_milestone_date' => 'datetime',
        'notify_subscribers' => 'boolean',
        'review_version' => 'integer',
        'date' => 'datetime',
        'tags' => 'array',
        'map_center' => 'array',
        'map_geometries' => 'array',
        'previous_progress' => 'float',
        'progress' => 'float',
        'measured_value' => 'float',
    ];

    protected $attributes = ['review_status' => 'pending', 'review_version' => 1, 'notify_subscribers' => false];

    public function scopeApproved(Builder $query): void
    {
        $query->where($this->qualifyColumn('review_status'), ReviewStatus::Approved->value);
    }

    public function scopeManageableBy(Builder $query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }
        $query->where(function (Builder $reports) use ($user): void {
            $reports->approved()
                ->orWhere(function (Builder $proposals) use ($user): void {
                    $proposals->where('author_id', $user->id)
                        ->whereHas('goal.objective.members', fn (Builder $members) => $members->where('users.id', $user->id)->whereIn('objective_user.role', ['reporter', 'manager']));
                })
                ->orWhereHas('goal.objective.members', fn (Builder $members) => $members->where('users.id', $user->id)->where('objective_user.role', 'manager'));
        });
    }

    public function isApproved(): bool
    {
        return $this->review_status === ReviewStatus::Approved;
    }

    public function canManage(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        $objectiveId = $this->goal->objective_id;
        return ! $this->isApproved() && ($user->isManagerObjective($objectiveId)
            || ($this->author_id === $user->id && $user->isReporterObjective($objectiveId)));
    }

    public function canViewInManagement(User $user): bool
    {
        return $this->canManage($user)
            || ($this->isApproved() && $user->isMemberObjective($this->goal->objective_id));
    }

    public function canDelete(User $user): bool
    {
        return $user->hasRole('admin') || (! $this->isApproved() && $this->author_id === $user->id && $this->canManage($user));
    }

    public function getReviewStatusLabelAttribute(): string
    {
        return $this->review_status->label();
    }

    public function reviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, mixed>|null */
    public function getMapGeometriesAttribute(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $geometry = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        // Older map forms stored an encoded JSON string inside the JSON column.
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true, 512, JSON_THROW_ON_ERROR);
        }
        if ($geometry !== null && ! is_array($geometry)) {
            throw new \UnexpectedValueException('La geometría del reporte no es un objeto JSON.');
        }
        return $geometry;
    }

    public function period()
    {
        return $this->belongsTo(GoalPeriod::class, 'goal_period_id');
    }

    /**
     * Preloads what the report resources read for the requested `with` options.
     *
     * @param  list<string>  $with
     */
    public function scopeForListing(Builder $query, array $with): void
    {
        $query->withCount(['comments', 'positiveTestimonies', 'negativeTestimonies']);

        if (in_array('report_hierarchy', $with)) {
            $query->with('goal.objective.strategicObjective.category');
        } elseif (in_array('report_goal', $with) || in_array('report_highlights', $with)) {
            $query->with('goal');
        }
        if (in_array('report_cover', $with)) {
            $query->with(['photos' => fn ($photos) => $photos->orderBy('id')]);
        }
        if (in_array('report_highlights', $with)) {
            $query->with(['period', 'milestone']);
        }
    }

    public function scopeFromVisibleObjectives(Builder $query): void
    {
        $query->whereHas('goal.objective', fn (Builder $objectives) => $objectives->where('hidden', false));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->isApproved() && $this->goal->objective->isVisibleTo($user);
    }

    public function objective()
    {
        return $this->goal->objective();
    }

    public function author()
    {
        return $this->belongsTo('App\User', 'author_id');
    }

    public function goal()
    {
        return $this->belongsTo('App\Goal','goal_id');
    }

    public function milestone()
    {
        return $this->belongsTo('App\Milestone','milestone_achieved');
    }
    public function files()
    {
        return $this->morphMany('App\File','fileable');
    }

    public function photos()
    {
        return $this->morphMany('App\ImageFile','imageable');
    }

    public function testimonies()
    {
        return $this->hasMany('App\Testimony','report_id');
    }

    public function comments()
    {
        return $this->morphMany('App\Comment', 'commentable')->whereNull('parent_id');
    }

    public function positiveTestimonies()
    {
        return $this->testimonies()->where('value', true);
    }

    public function negativeTestimonies()
    {
        return $this->testimonies()->where('value', false);
    }
    public function getPositiveTestimoniesAttribute()
    {
        return $this->testimonies()->where('value', true)->count();
    }

    public function getNegativeTestimoniesAttribute()
    {
        return $this->testimonies()->where('value', false)->count();
    }

    public function userTestimony($userId)
    {
        return $this->testimonies()->where('user_id', $userId);
    }

    public function getTypeLabelAttribute()
    {
        switch($this->type){
            case 'post':
                return 'Novedad';
                break;
            case 'progress':
                return 'Avance';
                break;
            case 'milestone':
                return 'Hito';
                break;
            default:
                return 'Sin etiqueta';
        }
    }
    public function getStatusLabelAttribute()
    {
        switch($this->status){
            case 'reached':
                return 'Alcanzada';
                break;
            case 'ongoing':
                return 'En progreso';
                break;
            case 'delayed':
                return 'No cumplida';
                break;
            case 'inactive':
                return 'Inactiva';
                break;
            default:
                return '???';
        }
    }
    public function getPreviousStatusLabelAttribute()
    {
        switch($this->previous_status){
            case 'reached':
                return 'Alcanzada';
                break;
            case 'ongoing':
                return 'En progreso';
                break;
            case 'delayed':
                return 'No cumplida';
                break;
            case 'inactive':
                return 'Inactiva';
                break;
            default:
                return '???';
        }
    }
    public function getTypeIconAttribute()
    {
        switch($this->type){
            case 'post':
                return 'fas fa-bullhorn';
                break;
            case 'progress':
                return 'fas fa-fast-forward';
                break;
            case 'milestone':
                return 'fas fa-medal';
                break;
            default:
                return 'fas fa-question';
                break;
        }
    }
}
