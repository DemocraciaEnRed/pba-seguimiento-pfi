<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.
    protected $appends = ['background_color'];

    public function objectives()
    {
        return $this->hasManyThrough(
            'App\Objective',
            'App\StrategicObjective',
            'category_id',
            'strategic_objective_id'
        );
    }

    public function strategicObjectives()
    {
        return $this->hasMany('App\StrategicObjective','category_id');
    }

    public function getBackgroundColorAttribute()
    {
        return "{$this->color}33";
    }
}
