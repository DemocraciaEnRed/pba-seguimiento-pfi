<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Initial PFI categories reported by the team. Administration can edit or deactivate them afterwards.
     *
     * @var list<string>
     */
    private const array CATEGORIES = [
        'Integrante MISTO',
        'Ente',
        'Empresa',
        'Organismos MISP',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('pfi_categories')->insertOrIgnore(array_map(fn (string $name): array => [
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::CATEGORIES));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pfi_categories')
            ->whereIn('name', self::CATEGORIES)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('pfi_category_user')
                ->whereColumn('pfi_category_user.pfi_category_id', 'pfi_categories.id'))
            ->delete();
    }
};
