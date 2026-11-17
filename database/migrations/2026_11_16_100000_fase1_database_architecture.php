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
        $allowPrimaryReset = getenv('FASE1_ALLOW_PRIMARY_RESET') === 'true';
        $connection = DB::selectOne(<<<'SQL'
            SELECT current_database() AS database_name,
                   CASE
                       WHEN shobj_description(database_row.oid, 'pg_database') = 'FASE1_TRAINING_CLONE' THEN 1
                       ELSE 0
                   END AS is_training_clone
            FROM pg_database AS database_row
            WHERE database_row.datname = current_database()
            SQL);
        $currentDatabase = $connection->database_name ?? null;
        $isPrimaryReset = $allowPrimaryReset
            && $targetDatabase === 'msbd_tubes_kel7'
            && $currentDatabase === $targetDatabase;
        $isStaging = $targetDatabase === 'msbd_tubes_kel7_staging'
            && $currentDatabase === $targetDatabase;
        $isMarkedTrainingClone = $targetDatabase === $currentDatabase
            && (bool) ($connection->is_training_clone ?? false);

        if (! $isPrimaryReset && ! $isStaging && ! $isMarkedTrainingClone) {
            throw new RuntimeException(sprintf(
                'Fase 1 ditolak: target harus database staging, clone bertanda FASE1_TRAINING_CLONE, atau database utama dengan FASE1_ALLOW_PRIMARY_RESET=true (aktual: %s).',
                $currentDatabase ?? '<tidak diketahui>',
            ));
        }

        if ($isPrimaryReset) {
            DB::select("SELECT set_config('fase1.confirmed_target', ?, false)", [$currentDatabase]);
        }

        try {
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
        } finally {
            if ($isPrimaryReset) {
                DB::statement('RESET fase1.confirmed_target');
            }
        }
    }

    public function down(): void {}
};
