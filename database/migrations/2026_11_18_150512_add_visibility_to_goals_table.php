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
        Schema::table('goals', function (Blueprint $table) {
            $table->enum('visibility', ['public', 'internal'])->default('public');
            $table->index(['visibility', 'objective_id'], 'goals_visibility_objective_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex('goals_visibility_objective_index');
            $table->dropColumn('visibility');
        });
    }
};
