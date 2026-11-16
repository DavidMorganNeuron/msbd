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
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS keanggotaan_kelas_one_active_per_semester ON keanggotaan_kelas (id_siswa, id_semester) WHERE status = 'aktif'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS keanggotaan_kelas_one_active_per_semester');
    }
};
