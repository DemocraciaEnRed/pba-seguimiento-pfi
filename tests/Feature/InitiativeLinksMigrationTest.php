<?php

namespace Tests\Feature;

use App\Initiative;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitiativeLinksMigrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function afterRefreshingDatabase(): void
    {
        $this->beforeApplicationDestroyed(fn () => Schema::disableForeignKeyConstraints());
    }

    public function test_existing_urls_are_migrated_without_reseeding(): void
    {
        $linked = Initiative::factory()->create();
        $empty = Initiative::factory()->create();
        DB::table('initiatives')->where('id', $linked->id)->update(['url' => 'https://example.com/?a=1&b=2']);

        $migration = require database_path('migrations/2026_11_13_141440_add_links_to_initiatives_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame([
            ['label' => 'Conocé más', 'url' => 'https://example.com/?a=1&b=2'],
        ], $linked->fresh()->links);
        $this->assertSame([], $empty->fresh()->links);
        $this->assertSame('https://example.com/?a=1&b=2', $linked->fresh()->getRawOriginal('url'));
    }
}
