<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX pendaftaran_ppdb_nisn_terkirim_unique ON pendaftaran_ppdb (TRIM(nisn)) WHERE status_pendaftaran <> 'draf' AND nisn IS NOT NULL AND TRIM(nisn) <> ''");
        DB::statement("CREATE UNIQUE INDEX pendaftaran_ppdb_nik_terkirim_unique ON pendaftaran_ppdb (TRIM(nik)) WHERE status_pendaftaran <> 'draf' AND nik IS NOT NULL AND TRIM(nik) <> ''");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pendaftaran_ppdb_nisn_terkirim_unique');
        DB::statement('DROP INDEX IF EXISTS pendaftaran_ppdb_nik_terkirim_unique');
    }
};
