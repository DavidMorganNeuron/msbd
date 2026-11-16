<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'cache' => 'cache_key_unique',
        'cache_locks' => 'cache_locks_key_unique',
    ];

    public function up(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'key')) {
                throw new RuntimeException("Tabel {$table} tidak memiliki kolom key.");
            }

            if (Schema::hasIndex($table, ['key'], 'unique')) {
                continue;
            }

            $hasDuplicateKeys = DB::table($table)
                ->select('key')
                ->groupBy('key')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasDuplicateKeys) {
                throw new RuntimeException(
                    "Tabel {$table} memiliki key duplikat. Bersihkan cache duplikat sebelum menjalankan migration."
                );
            }
        }

        foreach (self::TABLES as $table => $index) {
            if (! Schema::hasTable($table) || Schema::hasIndex($table, ['key'], 'unique')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->unique('key', $index);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index, 'unique')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        }
    }
};
