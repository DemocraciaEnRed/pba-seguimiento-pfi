<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Settings that used to be edited from "Configuración general" and are now static assets or unused.
     *
     * @var array<string, string|null>
     */
    private array $removedSettings = [
        'app_logo_color' => null,
        'app_logo_white' => null,
        'app_logo_footer' => null,
        'app_favicon' => null,
        'app_footer_contact_info' => 'mesadeayuda@minfra.gba.gob.ar',
        'app_footer_description' => '',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->whereIn('name', array_keys($this->removedSettings))->delete();

        foreach (array_keys($this->removedSettings) as $name) {
            Cache::forget($name);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->removedSettings as $name => $value) {
            if (DB::table('settings')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'name' => $name,
                'value' => $value,
                'type' => 'string',
                'cached' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
