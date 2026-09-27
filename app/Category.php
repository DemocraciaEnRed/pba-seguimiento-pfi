<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.
    protected $appends = ['background_color', 'icon_url'];

    /**
     * SVG files available in public/icons, keyed by slug.
     *
     * @var array<string, string>
     */
    public const AVAILABLE_ICONS = [
        'observatorio-integridad' => 'Integridad',
        'observatorio-sostenible' => 'Sostenible',
        'observatorio-genero' => 'Género',
        'observatorio-innovacion' => 'Innovación',
        'observatorio-planeamiento' => 'Planeamiento',
        'observatorio-procesos' => 'Procesos',
        'observatorio-aprendizaje' => 'Aprendizaje',
    ];

    public static function iconUrl(?string $icon): ?string
    {
        return array_key_exists($icon ?? '', self::AVAILABLE_ICONS) ? asset("icons/{$icon}.svg") : null;
    }

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

    public function getIconUrlAttribute(): ?string
    {
        return self::iconUrl($this->icon);
    }
}
