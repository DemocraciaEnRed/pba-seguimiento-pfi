<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.

    /**
     * Forget the cached value read by app_setting() whenever a setting changes.
     */
    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget($setting->name));
        static::deleted(fn (Setting $setting) => Cache::forget($setting->name));
    }

    public function getCastedValueAttribute()
    {
    switch ($this->type) {
        case 'int':
        case 'integer':
            return intval($this->value);
            break;

        case 'bool':
        case 'boolean':
            return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            break;

        default:
            return $this->value;
        }
    }
}
