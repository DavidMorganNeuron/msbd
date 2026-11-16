<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CacheKeyConstraintMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge();
    }

    public function test_adds_unique_key_indexes_to_legacy_cache_tables(): void
    {
        $this->createLegacyCacheTables();

        $migration = require database_path('migrations/2026_11_16_010419_ensure_unique_cache_key_constraints.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('cache', ['key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('cache_locks', ['key'], 'unique'));
    }

    public function test_does_not_discard_cache_rows_when_duplicate_keys_block_the_migration(): void
    {
        $this->createLegacyCacheTables();
        DB::table('cache')->insert([
            ['key' => 'duplicate', 'value' => 'one', 'expiration' => 100],
            ['key' => 'duplicate', 'value' => 'two', 'expiration' => 200],
        ]);

        $migration = require database_path('migrations/2026_11_16_010419_ensure_unique_cache_key_constraints.php');

        try {
            $migration->up();
            $this->fail('The migration should reject duplicate cache keys.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cache', $exception->getMessage());
        }

        $this->assertDatabaseCount('cache', 2);
    }

    private function createLegacyCacheTables(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key');
            $table->text('value');
            $table->bigInteger('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key');
            $table->string('owner');
            $table->bigInteger('expiration');
        });
    }
}
