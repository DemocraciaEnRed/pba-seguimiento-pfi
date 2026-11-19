<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = ['objective_user', 'objective_organization'];

    /**
     * Archive the legacy per-objective team and organizations to the private
     * archive disk before dropping them. Nothing is written when both are empty.
     */
    public function up(): void
    {
        $tables = collect(self::TABLES)
            ->mapWithKeys(fn (string $table): array => [$table => Schema::hasTable($table)
                ? DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all()
                : []]);

        if ($tables->contains(fn (array $rows): bool => $rows !== [])) {
            Storage::disk('archive')->put(
                'legacy-objective-tables-'.now()->format('Ymd-His').'.json',
                json_encode([
                    'archived_at' => now()->toIso8601String(),
                    'tables' => $tables->all(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
        }

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Recreate the empty legacy tables. Archived rows are not restored
     * automatically; they remain in storage/app/private/archive.
     */
    public function down(): void
    {
        if (! Schema::hasTable('objective_organization')) {
            Schema::create('objective_organization', function (Blueprint $table) {
                $table->id();
                $table->foreignId('objective_id')->constrained('objectives')->onDelete('cascade');
                $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('objective_user')) {
            Schema::create('objective_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('objective_id')->constrained('objectives')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('role');
                $table->timestamps();
            });
        }
    }
};
