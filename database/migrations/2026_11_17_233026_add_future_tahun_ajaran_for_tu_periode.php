<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            INSERT INTO public.tahun_ajaran (
                nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif
            )
            SELECT tahun.nama_tahun_ajaran, tahun.tanggal_mulai, tahun.tanggal_selesai, false
            FROM (VALUES
                ('2027/2028', DATE '2027-07-01', DATE '2028-06-30'),
                ('2028/2029', DATE '2028-07-01', DATE '2029-06-30')
            ) AS tahun(nama_tahun_ajaran, tanggal_mulai, tanggal_selesai)
            WHERE NOT EXISTS (
                SELECT 1
                FROM public.tahun_ajaran AS existing
                WHERE existing.nama_tahun_ajaran = tahun.nama_tahun_ajaran
            );
            SQL);
    }

    public function down(): void
    {
        // Keep academic-year rows on rollback because they may already be referenced by records.
    }
};
