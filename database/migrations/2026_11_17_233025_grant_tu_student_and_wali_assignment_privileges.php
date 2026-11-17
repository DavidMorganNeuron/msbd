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
            GRANT INSERT (id_kelas, id_guru, id_semester, ditugaskan_pada),
                  UPDATE (id_guru, ditugaskan_pada),
                  DELETE
            ON TABLE public.penugasan_wali_kelas TO role_tu;
            GRANT USAGE ON SEQUENCE public.penugasan_wali_kelas_id_penugasan_wali_kelas_seq TO role_tu;

            GRANT UPDATE (
                nama_lengkap, nis, nisn, nik, jenis_kelamin, tempat_lahir,
                tanggal_lahir, email, no_telepon, diubah_pada
            ) ON TABLE public.siswa TO role_tu;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            REVOKE INSERT (id_kelas, id_guru, id_semester, ditugaskan_pada),
                   UPDATE (id_guru, ditugaskan_pada),
                   DELETE
            ON TABLE public.penugasan_wali_kelas FROM role_tu;
            REVOKE USAGE ON SEQUENCE public.penugasan_wali_kelas_id_penugasan_wali_kelas_seq FROM role_tu;

            REVOKE UPDATE (
                nama_lengkap, nis, nisn, nik, jenis_kelamin, tempat_lahir,
                tanggal_lahir, email, no_telepon, diubah_pada
            ) ON TABLE public.siswa FROM role_tu;
            SQL);
    }
};
