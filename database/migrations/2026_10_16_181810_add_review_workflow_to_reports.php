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
        Schema::table('reports', function (Blueprint $table)         {
            Schema::table('reports', function (Blueprint $table): void {
                // NULL identifies only records predating the one-time data migration.
                $table->string('review_status')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users');
                $table->text('return_reason')->nullable();
                $table->unsignedInteger('review_version')->default(1);
                $table->boolean('notify_subscribers')->default(false);
                $table->dateTime('proposed_milestone_date')->nullable();
                $table->index(['review_status', 'submitted_at']);
            });
            foreach (['files', 'image_files'] as $name) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->string('disk')->nullable();
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table)         {
            foreach (['files', 'image_files'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('disk'));
            }
            Schema::table('reports', function (Blueprint $table): void {
                $table->dropForeign(['reviewed_by']);
                $table->dropIndex(['review_status', 'submitted_at']);
                $table->dropIndex(['review_status']);
                $table->dropColumn(['review_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'return_reason', 'review_version', 'notify_subscribers', 'proposed_milestone_date']);
            });
        });
    }
};
