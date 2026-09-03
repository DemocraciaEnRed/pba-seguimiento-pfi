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
        Schema::create('strategic_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('codigo', 225);
            $table->string('title', 550);
            $table->timestamps();
            $table->softDeletes('deleted_at', 0);
        });

        Schema::table('objectives', function (Blueprint $table) {
            $table->foreignId('strategic_objective_id')->constrained('strategic_objectives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('strategic_objective_id');
        });

        Schema::dropIfExists('strategic_objectives');
    }
};
