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
        (new \App\Services\Reports\LegacyReportApproval())->run();
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('review_status')->nullable(false)->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('review_status')->nullable()->default(null)->change();
        });
    }
};
