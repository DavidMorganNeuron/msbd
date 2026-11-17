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
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('GRANT SELECT (id_detail_pengajuan) ON TABLE public.detail_pengajuan TO role_siswa');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('REVOKE SELECT (id_detail_pengajuan) ON TABLE public.detail_pengajuan FROM role_siswa');
    }
};
