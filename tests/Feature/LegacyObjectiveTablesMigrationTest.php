<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyObjectiveTablesMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_11_18_204035_archive_and_drop_legacy_objective_tables.php';

    protected function afterRefreshingDatabase(): void
    {
        $this->beforeApplicationDestroyed(fn () => Schema::disableForeignKeyConstraints());
    }

    public function test_legacy_rows_are_archived_before_both_tables_are_dropped(): void
    {
        Storage::fake('archive');
        $this->travelTo('2026-11-18 20:40:35');
        $migration = require database_path(self::MIGRATION);
        $this->assertFalse(Schema::hasTable('objective_user'));
        $this->assertFalse(Schema::hasTable('objective_organization'));

        $migration->down();
        $this->legacyFixtures();
        $expected = [
            'objective_user' => DB::table('objective_user')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            'objective_organization' => DB::table('objective_organization')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
        $migration->up();

        $this->assertFalse(Schema::hasTable('objective_user'));
        $this->assertFalse(Schema::hasTable('objective_organization'));
        $path = 'legacy-objective-tables-20261118-204035.json';
        Storage::disk('archive')->assertExists($path);
        $archive = json_decode(Storage::disk('archive')->get($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(now()->toIso8601String(), $archive['archived_at']);
        $this->assertSame($expected, $archive['tables']);
        $this->assertSame(['manager', 'reporter'], array_column($archive['tables']['objective_user'], 'role'));
        $this->assertCount(1, $archive['tables']['objective_organization']);
    }

    public function test_empty_installations_drop_the_tables_without_writing_an_archive(): void
    {
        Storage::fake('archive');
        $migration = require database_path(self::MIGRATION);

        $migration->down();
        $migration->down();
        $this->assertTrue(Schema::hasTable('objective_user'));
        $this->assertTrue(Schema::hasTable('objective_organization'));
        $migration->up();

        $this->assertFalse(Schema::hasTable('objective_user'));
        $this->assertFalse(Schema::hasTable('objective_organization'));
        $this->assertSame([], Storage::disk('archive')->allFiles());
    }

    private function legacyFixtures(): void
    {
        foreach ([1, 2] as $id) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Usuario', 'surname' => 'Prueba', 'email' => $id.'@example.com', 'password' => 'hash']);
        }
        $category = DB::table('categories')->insertGetId(['title' => 'Eje', 'icon' => 'fas fa-circle', 'color' => '#123456', 'order' => 1]);
        $strategic = DB::table('strategic_objectives')->insertGetId(['codigo' => 'OE1', 'title' => 'Estratégico', 'category_id' => $category]);
        $objective = DB::table('objectives')->insertGetId(['title' => 'Objetivo', 'content' => 'Contenido', 'author_id' => 1, 'strategic_objective_id' => $strategic]);
        $organization = DB::table('organizations')->insertGetId(['name' => 'Organización', 'description' => 'Descripción']);
        DB::table('objective_user')->insert([
            ['objective_id' => $objective, 'user_id' => 1, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['objective_id' => $objective, 'user_id' => 2, 'role' => 'reporter', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('objective_organization')->insert(['objective_id' => $objective, 'organization_id' => $organization]);
    }
}
