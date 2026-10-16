<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('name', 'app_homepage_show_categories_selector')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! DB::table('settings')->where('name', 'app_homepage_show_categories_selector')->exists()) {
            DB::table('settings')->insert([
                'name' => 'app_homepage_show_categories_selector',
                'value' => true,
                'type' => 'boolean',
                'cached' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
