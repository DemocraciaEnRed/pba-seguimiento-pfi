<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Area, agency, institution or other entity associated with goals. It never grants permissions.
 */
class Organization extends Model
{
    protected $table = 'organizations';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function responsibleGoals(): HasMany
    {
        return $this->hasMany(Goal::class, 'responsible_organization_id');
    }

    public function participantGoals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_organization')->withTimestamps();
    }

    public function logo(): MorphOne
    {
        return $this->morphOne(ImageFile::class, 'imageable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Organizations responsible for or participating in any goal of the given goal query.
     *
     * @param  Builder<Goal>  $goals
     */
    public function scopeAssociatedWithGoals(Builder $query, Builder $goals): Builder
    {
        return $query->where(fn (Builder $organizations) => $organizations
            ->whereHas('responsibleGoals', fn (Builder $responsibleGoals) => $responsibleGoals->visibleIn($goals))
            ->orWhereHas('participantGoals', fn (Builder $participantGoals) => $participantGoals->visibleIn($goals)));
    }
}
