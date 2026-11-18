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
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->foreignId('responsible_organization_id')->nullable()->after('visibility')
                ->constrained('organizations')->restrictOnDelete();
        });

        Schema::create('goal_organization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['goal_id', 'organization_id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->json('organizations_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('organizations_snapshot');
        });

        Schema::dropIfExists('goal_organization');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_organization_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
