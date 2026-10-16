<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('settings')->where('name', 'app_search_indexing_enabled')->exists()) {
            DB::table('settings')->insert([
                'name' => 'app_search_indexing_enabled',
                'value' => false,
                'type' => 'boolean',
                'cached' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('app_search_indexing_enabled');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('name', 'app_search_indexing_enabled')->delete();
        Cache::forget('app_search_indexing_enabled');
    }
};
