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
        Schema::create('initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle', 600);
            $table->string('url', 500)->nullable();
            $table->boolean('featured_on_home')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('background_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->index(['featured_on_home', 'order']);
        });

        Schema::create('category_initiative', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();

            $table->primary(['category_id', 'initiative_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_initiative');
        Schema::dropIfExists('initiatives');
    }
};
