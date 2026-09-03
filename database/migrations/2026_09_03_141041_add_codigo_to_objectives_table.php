<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('objectives', 'codigo')) {
            Schema::table('objectives', function (Blueprint $table) {
                $table->string('codigo', 225)->nullable();
                $table->unique('codigo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('objectives', 'codigo')) {
            Schema::table('objectives', function (Blueprint $table) {
                $table->dropUnique(['codigo']);
                $table->dropColumn('codigo');
            });
        }
    }
};
