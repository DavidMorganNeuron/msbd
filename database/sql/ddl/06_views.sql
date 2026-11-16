-- Views for reporting and school operations.

CREATE OR REPLACE VIEW public.vw_progres_konfirmasi_kelas AS
SELECT kk.id_kelas, COUNT(DISTINCT kd.id_siswa) AS total_terkonfirmasi
FROM public.konfirmasi_data kd
JOIN public.keanggotaan_kelas kk ON kk.id_siswa = kd.id_siswa
WHERE kd.status = 'dikonfirmasi'
  AND kk.status = 'aktif'
GROUP BY kk.id_kelas;

CREATE OR REPLACE VIEW public.vw_siswa_belum_konfirmasi AS
SELECT s.id_siswa, s.nama_lengkap
FROM public.siswa s
LEFT JOIN public.konfirmasi_data kd
    ON kd.id_siswa = s.id_siswa
   AND kd.status = 'dikonfirmasi'
WHERE kd.id_konfirmasi IS NULL;

CREATE OR REPLACE VIEW public.vw_rekap_nilai_kelas AS
SELECT s.id_siswa, s.nama_lengkap, AVG(n.nilai) AS rata_rata
FROM public.siswa s
LEFT JOIN public.keanggotaan_kelas kk
    ON kk.id_siswa = s.id_siswa
   AND kk.status = 'aktif'
LEFT JOIN public.nilai_siswa n ON n.id_anggota = kk.id_keanggotaan
GROUP BY s.id_siswa, s.nama_lengkap;

CREATE OR REPLACE VIEW public.vw_jadwal_kelas_lengkap AS
SELECT jp.id_jadwal, k.nama_kelas, mp.nama_mapel, g.nama_guru
FROM public.jadwal_pelajaran jp
LEFT JOIN public.kelas k ON k.id_kelas = jp.id_kelas
LEFT JOIN public.mata_pelajaran mp ON mp.id_mata_pelajaran = jp.id_mata_pelajaran
LEFT JOIN public.guru g ON g.id_guru = jp.id_guru;

CREATE OR REPLACE VIEW public.vw_statistik_ppdb AS
SELECT status_pendaftaran, COUNT(*) AS jumlah
FROM public.pendaftaran_ppdb
GROUP BY status_pendaftaran;

CREATE OR REPLACE VIEW public.vw_log_audit_ringkas AS
SELECT nama_tabel, operasi, id_data, username_pengubah, waktu
FROM public.log_audit;

CREATE OR REPLACE VIEW public.vw_riwayat_mutasi AS
SELECT m.id_mutasi, s.nama_lengkap, m.alasan AS keterangan, m.tgl_pengajuan AS created_at
FROM public.mutasi_siswa m
LEFT JOIN public.siswa s ON s.id_siswa = m.id_siswa;

CREATE OR REPLACE VIEW public.vw_dashboard_kepsek AS
SELECT
    (SELECT COUNT(*) FROM public.siswa) AS total_siswa,
    (SELECT COUNT(*) FROM public.guru) AS total_guru,
    (SELECT COUNT(*) FROM public.kelas) AS total_kelas,
    (SELECT COUNT(*) FROM public.pendaftaran_ppdb) AS total_pendaftar,
    (
        SELECT COUNT(*)
        FROM public.rapor
        WHERE status_rapor IN ('final', 'disetujui', 'siap_cetak')
    ) AS total_rapor_siap_cetak;

CREATE OR REPLACE VIEW public.vw_antrean_verifikasi AS
SELECT id_pendaftaran, status_pendaftaran, diajukan_pada AS tanggal_daftar
FROM public.pendaftaran_ppdb
WHERE lower(status_pendaftaran) IN ('menunggu', 'diajukan', 'perlu_verifikasi', 'perlu_perbaikan');

CREATE OR REPLACE VIEW public.vw_kelengkapan_data_kelas AS
SELECT k.id_kelas, k.nama_kelas,
       COUNT(DISTINCT kg.id_siswa) AS jumlah_siswa,
       COUNT(DISTINCT kg.id_siswa) FILTER (WHERE s.id_siswa IS NOT NULL) AS siswa_terdata
FROM public.kelas k
LEFT JOIN public.keanggotaan_kelas kg
    ON kg.id_kelas = k.id_kelas
   AND kg.status = 'aktif'
LEFT JOIN public.siswa s ON s.id_siswa = kg.id_siswa
GROUP BY k.id_kelas, k.nama_kelas;

CREATE OR REPLACE VIEW public.vw_nilai_rata_siswa AS
WITH nilai_berbobot AS (
    SELECT ns.id_anggota,
           ns.id_mapel,
           ns.id_semester,
           SUM(ns.nilai * kp.bobot_persen) / NULLIF(SUM(kp.bobot_persen), 0) AS nilai_rata_rata
    FROM public.nilai_siswa ns
    JOIN public.komponen_penilaian kp ON kp.id_komponen = ns.id_komponen
    GROUP BY ns.id_anggota, ns.id_mapel, ns.id_semester
)
SELECT id_anggota, id_mapel, id_semester, ROUND(nilai_rata_rata, 2) AS nilai_rata_rata
FROM nilai_berbobot;

CREATE OR REPLACE VIEW public.vw_rapor_siap_cetak AS
SELECT id_rapor, id_anggota, id_semester, rata_rata, status_rapor, tgl_dibuat
FROM public.rapor
WHERE status_rapor IN ('final', 'disetujui', 'siap_cetak');

CREATE OR REPLACE VIEW public.vw_rekap_absensi_siswa AS
SELECT da.id_siswa,
       sa.tanggal,
       COUNT(*) FILTER (WHERE sh.kode = 'H') AS hadir,
       COUNT(*) FILTER (WHERE sh.kode = 'S') AS sakit,
       COUNT(*) FILTER (WHERE sh.kode = 'I') AS izin,
       COUNT(*) FILTER (WHERE sh.kode = 'A') AS alpa
FROM public.detail_absensi da
JOIN public.sesi_absensi sa ON sa.id_sesi = da.id_sesi
JOIN public.status_kehadiran sh ON sh.id_status_hadir = da.id_status_hadir
GROUP BY da.id_siswa, sa.tanggal;

ALTER VIEW public.vw_progres_konfirmasi_kelas SET (security_invoker = true);
ALTER VIEW public.vw_siswa_belum_konfirmasi SET (security_invoker = true);
ALTER VIEW public.vw_rekap_nilai_kelas SET (security_invoker = true);
ALTER VIEW public.vw_jadwal_kelas_lengkap SET (security_invoker = true);
ALTER VIEW public.vw_statistik_ppdb SET (security_invoker = true);
ALTER VIEW public.vw_log_audit_ringkas SET (security_invoker = true);
ALTER VIEW public.vw_riwayat_mutasi SET (security_invoker = true);
ALTER VIEW public.vw_dashboard_kepsek SET (security_invoker = true);
ALTER VIEW public.vw_antrean_verifikasi SET (security_invoker = true);
ALTER VIEW public.vw_kelengkapan_data_kelas SET (security_invoker = true);
ALTER VIEW public.vw_nilai_rata_siswa SET (security_invoker = true);
ALTER VIEW public.vw_rapor_siap_cetak SET (security_invoker = true);
ALTER VIEW public.vw_rekap_absensi_siswa SET (security_invoker = true);
