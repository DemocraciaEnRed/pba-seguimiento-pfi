<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('reports')
            ->whereIn('review_status', ['pending', 'returned'])
            ->whereNull('attendant_id')
            ->update(['attendant_id' => DB::raw('author_id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep subsequent explicit reassignments; rolling back the schema removes the column.
    }
};
