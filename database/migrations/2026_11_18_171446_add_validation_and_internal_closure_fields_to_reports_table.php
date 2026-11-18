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
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable()->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable()->index();
            $table->index(['review_status', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['validated_by']);
            $table->dropForeign(['closed_by']);
            $table->dropIndex(['review_status', 'closed_at']);
            $table->dropIndex(['validated_at']);
            $table->dropIndex(['closed_at']);
            $table->dropColumn(['validated_by', 'validated_at', 'closed_by', 'closed_at']);
        });
    }
};
