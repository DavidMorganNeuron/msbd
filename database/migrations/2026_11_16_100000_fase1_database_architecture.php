<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Fase 1 hanya dapat dijalankan pada PostgreSQL.');
        }

        $targetDatabase = getenv('FASE1_TARGET_DB');
        $connection = DB::selectOne('SELECT current_database() AS database_name');
        $currentDatabase = $connection->database_name ?? null;

        if ($targetDatabase !== 'msbd_tubes_kel7_staging' || $currentDatabase !== $targetDatabase) {
            throw new RuntimeException(sprintf(
                'Fase 1 ditolak: FASE1_TARGET_DB harus msbd_tubes_kel7_staging dan harus sama dengan current_database() (aktual: %s).',
                $currentDatabase ?? '<tidak diketahui>',
            ));
        }

        foreach ([
            'ddl/01_restore_constraints.sql',
            'ddl/02_constraint_tambahan.sql',
        ] as $file) {
            $path = database_path('sql'.DIRECTORY_SEPARATOR.$file);

            if (! is_file($path)) {
                throw new RuntimeException("File SQL Fase 1 tidak ditemukan: {$path}");
            }

            $sql = file_get_contents($path);

            if ($sql === false) {
                throw new RuntimeException("File SQL Fase 1 tidak dapat dibaca: {$path}");
            }

            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
    }
};
