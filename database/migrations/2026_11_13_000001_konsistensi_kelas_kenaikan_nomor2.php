<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE keanggotaan_kelas DROP CONSTRAINT IF EXISTS keanggotaan_kelas_status_check');
        DB::statement("ALTER TABLE keanggotaan_kelas ADD CONSTRAINT keanggotaan_kelas_status_check CHECK (status IN ('aktif','pindah','naik_kelas','tidak_naik_kelas','lulus','keluar'))");

        DB::statement('DROP INDEX IF EXISTS keanggotaan_kelas_id_siswa_id_semester_unique');
        DB::statement('DROP INDEX IF EXISTS keanggotaan_kelas_id_siswa_id_semester_key');
        DB::statement('CREATE INDEX IF NOT EXISTS keanggotaan_kelas_id_siswa_id_semester_index ON keanggotaan_kelas (id_siswa, id_semester)');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS kelas_id_tahun_ajaran_nama_kelas_unique ON kelas (id_tahun_ajaran, lower(nama_kelas))');
        DB::statement('CREATE INDEX IF NOT EXISTS keanggotaan_kelas_id_kelas_id_semester_index ON keanggotaan_kelas (id_kelas, id_semester)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS keanggotaan_kelas_id_kelas_id_semester_index');
        DB::statement('DROP INDEX IF EXISTS keanggotaan_kelas_id_siswa_id_semester_index');
        DB::statement('DROP INDEX IF EXISTS kelas_id_tahun_ajaran_nama_kelas_unique');

        DB::statement('ALTER TABLE keanggotaan_kelas DROP CONSTRAINT IF EXISTS keanggotaan_kelas_status_check');
        DB::statement("ALTER TABLE keanggotaan_kelas ADD CONSTRAINT keanggotaan_kelas_status_check CHECK (status IN ('aktif','pindah','naik_kelas','lulus','keluar'))");

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS keanggotaan_kelas_id_siswa_id_semester_unique ON keanggotaan_kelas (id_siswa, id_semester)');
    }
};
