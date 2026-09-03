<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrategicObjective extends Model
{
    use SoftDeletes;

    protected $table = 'strategic_objectives';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.

    public function category()
    {
        return $this->belongsTo('App\Category', 'category_id');
    }

    public function objectives()
    {
        return $this->hasMany('App\Objective', 'strategic_objective_id');
    }
}
