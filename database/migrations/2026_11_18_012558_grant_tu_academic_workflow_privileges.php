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
            GRANT DELETE ON TABLE public.jadwal_pelajaran TO role_tu;
            GRANT INSERT (id_kelas, id_semester, id_mata_pelajaran, id_guru, hari, jam_mulai, jam_selesai, ruang),
                  UPDATE (id_kelas, id_semester, id_mata_pelajaran, id_guru, hari, jam_mulai, jam_selesai, ruang)
                ON TABLE public.jadwal_pelajaran TO role_tu;

            GRANT INSERT (nama_guru, is_aktif) ON TABLE public.guru TO role_tu;

            GRANT DELETE ON TABLE public.kelas TO role_tu;
            GRANT INSERT (id_tahun_ajaran, id_tingkat, id_jurusan, nama_kelas, kapasitas, is_aktif),
                  UPDATE (nama_kelas, id_tahun_ajaran, id_tingkat, id_jurusan, kapasitas)
                ON TABLE public.kelas TO role_tu;

            GRANT DELETE, SELECT ON TABLE public.komponen_penilaian TO role_tu;
            GRANT INSERT (nama_komponen, bobot_persen),
                  UPDATE (nama_komponen, bobot_persen)
                ON TABLE public.komponen_penilaian TO role_tu;

            GRANT INSERT (id_periode_update, id_siswa, status)
                ON TABLE public.konfirmasi_data TO role_tu;

            GRANT DELETE ON TABLE public.mata_pelajaran TO role_tu;
            GRANT INSERT (kode_mapel, nama_mapel, is_aktif),
                  UPDATE (kode_mapel, nama_mapel, is_aktif)
                ON TABLE public.mata_pelajaran TO role_tu;

            GRANT INSERT (
                id_siswa, id_pengaju, id_penyetuju, id_sekolah_tujuan, jenis, alasan, status,
                tgl_pengajuan, tgl_keputusan, id_sekolah_asal, id_kelas_tujuan, nisn_baru,
                nama_siswa_baru, jenis_kelamin_baru, tempat_lahir_baru, tanggal_lahir_baru,
                email_baru, no_telepon_baru, tanggal_efektif, path_lampiran, nama_lampiran,
                catatan_keputusan, nama_sekolah_tujuan
            ), UPDATE (
                id_siswa, id_sekolah_tujuan, jenis, alasan, status, id_sekolah_asal,
                tanggal_efektif, path_lampiran, nama_lampiran, nama_sekolah_tujuan
            ) ON TABLE public.mutasi_siswa TO role_tu;

            GRANT INSERT (id_pengguna, judul, pesan, dibaca, dibuat_pada)
                ON TABLE public.notifikasi TO role_tu, role_kepsek;

            GRANT DELETE ON TABLE public.semester TO role_tu;
            GRANT INSERT (id_tahun_ajaran, nama_semester, tanggal_mulai, tanggal_selesai, is_aktif),
                  UPDATE (id_tahun_ajaran, nama_semester, tanggal_mulai, tanggal_selesai, is_aktif)
                ON TABLE public.semester TO role_tu;

            GRANT SELECT (id_siswa, nama_lengkap, nisn),
                  UPDATE (id_status_siswa, diubah_pada)
                ON TABLE public.siswa TO role_kepsek;
            GRANT UPDATE (status, tanggal_selesai)
                ON TABLE public.keanggotaan_kelas TO role_kepsek;

            GRANT DELETE, SELECT ON TABLE public.skala_predikat TO role_tu;
            GRANT INSERT (huruf, nilai_min, nilai_maks, deskripsi),
                  UPDATE (huruf, nilai_min, nilai_maks, deskripsi)
                ON TABLE public.skala_predikat TO role_tu;

            GRANT DELETE ON TABLE public.tahun_ajaran TO role_tu;
            GRANT INSERT (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif),
                  UPDATE (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif)
                ON TABLE public.tahun_ajaran TO role_tu;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            REVOKE DELETE ON TABLE public.jadwal_pelajaran FROM role_tu;
            REVOKE INSERT (id_kelas, id_semester, id_mata_pelajaran, id_guru, hari, jam_mulai, jam_selesai, ruang),
                   UPDATE (id_kelas, id_semester, id_mata_pelajaran, id_guru, hari, jam_mulai, jam_selesai, ruang)
                ON TABLE public.jadwal_pelajaran FROM role_tu;

            REVOKE INSERT (nama_guru, is_aktif) ON TABLE public.guru FROM role_tu;

            REVOKE DELETE ON TABLE public.kelas FROM role_tu;
            REVOKE INSERT (id_tahun_ajaran, id_tingkat, id_jurusan, nama_kelas, kapasitas, is_aktif),
                   UPDATE (nama_kelas, id_tahun_ajaran, id_tingkat, id_jurusan, kapasitas)
                ON TABLE public.kelas FROM role_tu;

            REVOKE DELETE, SELECT ON TABLE public.komponen_penilaian FROM role_tu;
            REVOKE INSERT (nama_komponen, bobot_persen),
                   UPDATE (nama_komponen, bobot_persen)
                ON TABLE public.komponen_penilaian FROM role_tu;

            REVOKE INSERT (id_periode_update, id_siswa, status)
                ON TABLE public.konfirmasi_data FROM role_tu;

            REVOKE DELETE ON TABLE public.mata_pelajaran FROM role_tu;
            REVOKE INSERT (kode_mapel, nama_mapel, is_aktif),
                   UPDATE (kode_mapel, nama_mapel, is_aktif)
                ON TABLE public.mata_pelajaran FROM role_tu;

            REVOKE INSERT (
                id_siswa, id_pengaju, id_penyetuju, id_sekolah_tujuan, jenis, alasan, status,
                tgl_pengajuan, tgl_keputusan, id_sekolah_asal, id_kelas_tujuan, nisn_baru,
                nama_siswa_baru, jenis_kelamin_baru, tempat_lahir_baru, tanggal_lahir_baru,
                email_baru, no_telepon_baru, tanggal_efektif, path_lampiran, nama_lampiran,
                catatan_keputusan, nama_sekolah_tujuan
            ), UPDATE (
                id_siswa, id_sekolah_tujuan, jenis, alasan, status, id_sekolah_asal,
                tanggal_efektif, path_lampiran, nama_lampiran, nama_sekolah_tujuan
            ) ON TABLE public.mutasi_siswa FROM role_tu;

            REVOKE INSERT (id_pengguna, judul, pesan, dibaca, dibuat_pada)
                ON TABLE public.notifikasi FROM role_tu, role_kepsek;

            REVOKE DELETE ON TABLE public.semester FROM role_tu;
            REVOKE INSERT (id_tahun_ajaran, nama_semester, tanggal_mulai, tanggal_selesai, is_aktif),
                   UPDATE (id_tahun_ajaran, nama_semester, tanggal_mulai, tanggal_selesai, is_aktif)
                ON TABLE public.semester FROM role_tu;

            REVOKE SELECT (id_siswa, nama_lengkap, nisn),
                   UPDATE (id_status_siswa, diubah_pada)
                ON TABLE public.siswa FROM role_kepsek;
            REVOKE UPDATE (status, tanggal_selesai)
                ON TABLE public.keanggotaan_kelas FROM role_kepsek;

            REVOKE DELETE, SELECT ON TABLE public.skala_predikat FROM role_tu;
            REVOKE INSERT (huruf, nilai_min, nilai_maks, deskripsi),
                   UPDATE (huruf, nilai_min, nilai_maks, deskripsi)
                ON TABLE public.skala_predikat FROM role_tu;

            REVOKE DELETE ON TABLE public.tahun_ajaran FROM role_tu;
            REVOKE INSERT (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif),
                   UPDATE (nama_tahun_ajaran, tanggal_mulai, tanggal_selesai, is_aktif)
                ON TABLE public.tahun_ajaran FROM role_tu;
            SQL);
    }
};
