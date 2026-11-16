-- Remove only records marked with the reserved Fase 1 demo identifiers.
-- Immutable log_audit entries are retained by design.

DELETE FROM public.detail_pengajuan
WHERE id_pengajuan IN (
    SELECT pp.id_pengajuan
    FROM public.pengajuan_perubahan AS pp
    JOIN public.siswa AS s ON s.id_siswa = pp.id_siswa
    JOIN public.pengguna AS p ON p.id_pengguna = s.id_pengguna
    WHERE s.nis = 'FASE1-DEMO-001'
      AND p.username = 'fase1_demo_siswa'
);

DELETE FROM public.pengajuan_perubahan AS pp
USING public.siswa AS s, public.pengguna AS p
WHERE pp.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.detail_rapor
WHERE id_rapor IN (
    SELECT r.id_rapor
    FROM public.rapor AS r
    JOIN public.keanggotaan_kelas AS kk ON kk.id_keanggotaan = r.id_anggota
    JOIN public.siswa AS s ON s.id_siswa = kk.id_siswa
    JOIN public.pengguna AS p ON p.id_pengguna = s.id_pengguna
    WHERE s.nis = 'FASE1-DEMO-001'
      AND p.username = 'fase1_demo_siswa'
);

DELETE FROM public.rapor AS r
USING public.keanggotaan_kelas AS kk, public.siswa AS s, public.pengguna AS p
WHERE r.id_anggota = kk.id_keanggotaan
  AND kk.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.nilai_siswa AS n
USING public.keanggotaan_kelas AS kk, public.siswa AS s, public.pengguna AS p
WHERE n.id_anggota = kk.id_keanggotaan
  AND kk.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.detail_absensi AS da
USING public.siswa AS s, public.pengguna AS p
WHERE da.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.catatan_wali_kelas AS cw
USING public.siswa AS s, public.pengguna AS p
WHERE cw.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.konfirmasi_data
WHERE id_periode_update IN (
    SELECT id_periode_update
    FROM public.periode_update
    WHERE nama_periode = 'FASE1-DEMO-ONLY'
);

DELETE FROM public.konfirmasi_data AS kd
USING public.siswa AS s, public.pengguna AS p
WHERE kd.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.periode_update WHERE nama_periode = 'FASE1-DEMO-ONLY';

DELETE FROM public.keanggotaan_kelas AS kk
USING public.siswa AS s, public.pengguna AS p
WHERE kk.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.alamat_siswa AS a
USING public.siswa AS s, public.pengguna AS p
WHERE a.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.orang_tua_wali AS o
USING public.siswa AS s, public.pengguna AS p
WHERE o.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.dokumen_siswa AS d
USING public.siswa AS s, public.pengguna AS p
WHERE d.id_siswa = s.id_siswa
  AND s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.siswa AS s
USING public.pengguna AS p
WHERE s.id_pengguna = p.id_pengguna
  AND s.nis = 'FASE1-DEMO-001'
  AND p.username = 'fase1_demo_siswa';

DELETE FROM public.pengguna
WHERE username IN ('fase1_demo_siswa', 'fase1_demo_tu', 'fase1_demo_kepsek', 'fase1_demo_wali');
