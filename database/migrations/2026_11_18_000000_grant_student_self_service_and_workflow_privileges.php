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
            GRANT UPDATE (password_hash, diubah_pada)
                ON TABLE public.pengguna TO role_siswa, role_kepsek, role_wali_kelas;

            GRANT INSERT (id_tahun_ajaran, nama_periode, tanggal_mulai, tanggal_selesai, status, dibuat_oleh),
                  UPDATE (status)
                ON TABLE public.periode_update TO role_tu;

            GRANT INSERT (id_pengguna, judul, pesan, dibaca, dibuat_pada)
                ON TABLE public.notifikasi TO role_wali_kelas;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            REVOKE UPDATE (password_hash, diubah_pada)
                ON TABLE public.pengguna FROM role_siswa, role_kepsek, role_wali_kelas;

            REVOKE INSERT (id_tahun_ajaran, nama_periode, tanggal_mulai, tanggal_selesai, status, dibuat_oleh),
                   UPDATE (status)
                ON TABLE public.periode_update FROM role_tu;

            REVOKE INSERT (id_pengguna, judul, pesan, dibaca, dibuat_pada)
                ON TABLE public.notifikasi FROM role_wali_kelas;
            SQL);
    }
};
