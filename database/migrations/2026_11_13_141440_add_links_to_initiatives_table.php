<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('initiatives', function (Blueprint $table): void {
            $table->json('links')->nullable();
        });

        DB::table('initiatives')->select(['id', 'url'])->chunkById(100, function (Collection $initiatives): void {
            foreach ($initiatives as $initiative) {
                $links = $initiative->url
                    ? [['label' => 'Conocé más', 'url' => $initiative->url]]
                    : [];

                DB::table('initiatives')->where('id', $initiative->id)->update([
                    'links' => json_encode($links, JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    /**
     * Requiere respaldo: eliminar links pierde los enlaces editados tras la migración.
     */
    public function down(): void
    {
        Schema::table('initiatives', function (Blueprint $table): void {
            $table->dropColumn('links');
        });
    }
};
