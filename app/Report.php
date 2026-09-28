<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $table = 'reports';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.
    protected $appends = ['type_label','status_label','previous_status_label','type_icon'];

    protected $casts = [
        'date' => 'datetime',
        'tags' => 'array',
        'map_center' => 'array',
        'map_geometries' => 'array',
        'previous_progress' => 'float',
        'progress' => 'float',
        'measured_value' => 'float',
    ];

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
        return $this->goal->objective->isVisibleTo($user);
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
