<?php

if (! function_exists('app_setting')) {
   function app_setting($key, $default = null)
    {
        // Barryvdh\Debugbar\Facade::info($default);
        $value = Cache::rememberForever($key, function () use ($key, $default) {
          $setting = \App\Setting::where('name',$key)->first();
          if(is_null($setting)){
            return null;
          }
          if(is_null($setting->casted_value) && !is_null($default)){
            return $default;
          }

          return $setting->casted_value;
        });
        return is_null($value) ? $default : $value;
    }
}

if (! function_exists('indicator_number')) {
    function indicator_number(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}

if (! function_exists('indicator_percentage')) {
    /**
     * Formats a ratio (1.0 = 100%) as a rounded percentage.
     */
    function indicator_percentage(?float $ratio, bool $signed = false): string
    {
        if ($ratio === null) {
            return '—';
        }

        $percentage = (int) round($ratio * 100);

        return ($signed && $percentage > 0 ? '+' : '').$percentage.'%';
    }
}
