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

        DB::unprepared(<<<'SQL'
GRANT INSERT (id_pendaftaran, id_petugas, id_alasan, hasil, catatan, tgl_verifikasi)
    ON TABLE public.verifikasi_pendaftaran TO role_tu;
GRANT UPDATE (catatan_verifikasi, diperiksa_pada, id_kelas_diterima, id_pengguna_pemeriksa, status_pendaftaran)
    ON TABLE public.pendaftaran_ppdb TO role_tu;
GRANT UPDATE (catatan, id_siswa, status_verifikasi)
    ON TABLE public.dokumen_siswa TO role_tu;
GRANT INSERT (id_role, username, password_hash, is_aktif, dibuat_pada, diubah_pada)
    ON TABLE public.pengguna TO role_tu;
GRANT UPDATE (is_aktif)
    ON TABLE public.pengguna TO role_tu;
GRANT INSERT (id_pengguna, id_status_siswa, id_agama, nis, nisn, nik, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, email, no_telepon, tanggal_masuk, dibuat_pada, diubah_pada)
    ON TABLE public.siswa TO role_tu;
GRANT INSERT (id_siswa, alamat, rt, rw, kelurahan, kecamatan, kota, provinsi, kode_pos)
    ON TABLE public.alamat_siswa TO role_tu;
GRANT INSERT (id_siswa, hubungan, nama_lengkap, id_pekerjaan, no_telepon, alamat)
    ON TABLE public.orang_tua_wali TO role_tu;
GRANT INSERT (id_siswa, id_kelas, id_semester, tanggal_mulai, tanggal_selesai, status)
    ON TABLE public.keanggotaan_kelas TO role_tu;
GRANT UPDATE (id_kelas, tanggal_mulai, tanggal_selesai, status)
    ON TABLE public.keanggotaan_kelas TO role_tu;
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
REVOKE INSERT (id_pendaftaran, id_petugas, id_alasan, hasil, catatan, tgl_verifikasi)
    ON TABLE public.verifikasi_pendaftaran FROM role_tu;
REVOKE UPDATE (catatan_verifikasi, diperiksa_pada, id_kelas_diterima, id_pengguna_pemeriksa, status_pendaftaran)
    ON TABLE public.pendaftaran_ppdb FROM role_tu;
REVOKE UPDATE (catatan, id_siswa, status_verifikasi)
    ON TABLE public.dokumen_siswa FROM role_tu;
REVOKE INSERT (id_role, username, password_hash, is_aktif, dibuat_pada, diubah_pada)
    ON TABLE public.pengguna FROM role_tu;
REVOKE UPDATE (is_aktif)
    ON TABLE public.pengguna FROM role_tu;
REVOKE INSERT (id_pengguna, id_status_siswa, id_agama, nis, nisn, nik, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, email, no_telepon, tanggal_masuk, dibuat_pada, diubah_pada)
    ON TABLE public.siswa FROM role_tu;
REVOKE INSERT (id_siswa, alamat, rt, rw, kelurahan, kecamatan, kota, provinsi, kode_pos)
    ON TABLE public.alamat_siswa FROM role_tu;
REVOKE INSERT (id_siswa, hubungan, nama_lengkap, id_pekerjaan, no_telepon, alamat)
    ON TABLE public.orang_tua_wali FROM role_tu;
REVOKE INSERT (id_siswa, id_kelas, id_semester, tanggal_mulai, tanggal_selesai, status)
    ON TABLE public.keanggotaan_kelas FROM role_tu;
REVOKE UPDATE (id_kelas, tanggal_mulai, tanggal_selesai, status)
    ON TABLE public.keanggotaan_kelas FROM role_tu;
SQL);
    }
};
