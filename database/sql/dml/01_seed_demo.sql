-- Seed isolated Fase 1 demo records; never use these identifiers for real users.
-- Bcrypt of the demo-only password; this does not require the pgcrypto extension.

INSERT INTO public.role (nama_role, deskripsi)
VALUES
    ('siswa', 'Role siswa'),
    ('tu', 'Role TU'),
    ('kepsek', 'Role kepala sekolah'),
    ('wali_kelas', 'Role wali kelas')
ON CONFLICT (nama_role) DO NOTHING;

INSERT INTO public.hak_akses (kode_hak_akses, nama_hak_akses, deskripsi)
VALUES
    ('read_siswa', 'Baca siswa', 'baca data siswa'),
    ('write_nilai', 'Tulis nilai', 'tulis nilai'),
    ('read_rapor', 'Baca rapor', 'baca rapor')
ON CONFLICT (kode_hak_akses) DO NOTHING;

INSERT INTO public.pengguna (id_role, username, password_hash, is_aktif)
VALUES
    ((SELECT id_role FROM public.role WHERE nama_role = 'siswa'), 'fase1_demo_siswa', '$2y$12$VGa.rmwZKkTj9SRvv7JYHO59B1Xrou3kED/6pSl65AiJMjCupgq8W', TRUE),
    ((SELECT id_role FROM public.role WHERE nama_role = 'tu'), 'fase1_demo_tu', '$2y$12$VGa.rmwZKkTj9SRvv7JYHO59B1Xrou3kED/6pSl65AiJMjCupgq8W', TRUE),
    ((SELECT id_role FROM public.role WHERE nama_role = 'kepsek'), 'fase1_demo_kepsek', '$2y$12$VGa.rmwZKkTj9SRvv7JYHO59B1Xrou3kED/6pSl65AiJMjCupgq8W', TRUE),
    ((SELECT id_role FROM public.role WHERE nama_role = 'wali_kelas'), 'fase1_demo_wali', '$2y$12$VGa.rmwZKkTj9SRvv7JYHO59B1Xrou3kED/6pSl65AiJMjCupgq8W', TRUE)
ON CONFLICT (username) DO NOTHING;

INSERT INTO public.siswa (id_pengguna, id_status_siswa, nis, nisn, nik, nama_lengkap)
SELECT p.id_pengguna, st.id_status_siswa, 'FASE1-DEMO-001', 'FASE1-DEMO-001', 'FASE1-DEMO-001', 'Siswa Demo Fase 1'
FROM public.pengguna AS p
JOIN public.status_siswa AS st ON st.kode_status = 'aktif'
WHERE p.username = 'fase1_demo_siswa'
ON CONFLICT (nis) DO NOTHING;

INSERT INTO public.periode_update (
    id_tahun_ajaran, nama_periode, tanggal_mulai, tanggal_selesai, status
)
SELECT ta.id_tahun_ajaran, 'FASE1-DEMO-ONLY', CURRENT_DATE - INTERVAL '1 day',
       CURRENT_DATE + INTERVAL '30 days', 'aktif'
FROM public.tahun_ajaran AS ta
WHERE ta.is_aktif
  AND NOT EXISTS (
      SELECT 1 FROM public.periode_update WHERE nama_periode = 'FASE1-DEMO-ONLY'
  )
ORDER BY ta.id_tahun_ajaran DESC
LIMIT 1;

WITH semester_aktif AS (
    SELECT id_semester, id_tahun_ajaran
    FROM public.semester
    WHERE is_aktif
    ORDER BY tanggal_mulai DESC NULLS LAST, id_semester DESC
    LIMIT 1
), kelas_aktif AS (
    SELECT k.id_kelas, sm.id_semester
    FROM semester_aktif AS sm
    JOIN LATERAL (
        SELECT id_kelas
        FROM public.kelas
        WHERE id_tahun_ajaran = sm.id_tahun_ajaran
          AND is_aktif
        ORDER BY id_kelas
        LIMIT 1
    ) AS k ON TRUE
)
INSERT INTO public.keanggotaan_kelas (id_siswa, id_kelas, id_semester)
SELECT s.id_siswa, k.id_kelas, k.id_semester
FROM public.siswa AS s
CROSS JOIN kelas_aktif AS k
WHERE s.nis = 'FASE1-DEMO-001'
  AND NOT EXISTS (
      SELECT 1
      FROM public.keanggotaan_kelas AS existing
      WHERE existing.id_siswa = s.id_siswa
        AND existing.id_semester = k.id_semester
  );

INSERT INTO public.konfirmasi_data (id_periode_update, id_siswa, status)
SELECT pu.id_periode_update, s.id_siswa, 'belum'
FROM public.periode_update AS pu
CROSS JOIN public.siswa AS s
WHERE pu.nama_periode = 'FASE1-DEMO-ONLY'
  AND s.nis = 'FASE1-DEMO-001'
  AND NOT EXISTS (
      SELECT 1
      FROM public.konfirmasi_data AS existing
      WHERE existing.id_periode_update = pu.id_periode_update
        AND existing.id_siswa = s.id_siswa
  );

INSERT INTO public.pengajuan_perubahan (id_siswa, id_periode_update, status_pengajuan, alasan)
SELECT s.id_siswa, pu.id_periode_update, 'menunggu', 'FASE1_DEMO'
FROM public.siswa AS s
LEFT JOIN public.periode_update AS pu ON pu.nama_periode = 'FASE1-DEMO-ONLY'
WHERE s.nis = 'FASE1-DEMO-001'
  AND NOT EXISTS (
      SELECT 1
      FROM public.pengajuan_perubahan AS pp
      WHERE pp.id_siswa = s.id_siswa
        AND pp.alasan = 'FASE1_DEMO'
        AND pp.status_pengajuan = 'menunggu'
  );

INSERT INTO public.detail_pengajuan (id_pengajuan, nama_field, nilai_lama, nilai_baru)
SELECT pp.id_pengajuan, 'email', to_jsonb(s.email), to_jsonb('fase1-demo-updated@example.invalid'::TEXT)
FROM public.pengajuan_perubahan AS pp
JOIN public.siswa AS s ON s.id_siswa = pp.id_siswa
JOIN public.ref_field_data AS rf
  ON rf.nama_tabel = 'siswa'
 AND rf.nama_kolom = 'email'
 AND rf.boleh_diubah_siswa
WHERE s.nis = 'FASE1-DEMO-001'
  AND pp.alasan = 'FASE1_DEMO'
  AND pp.status_pengajuan = 'menunggu'
  AND NOT EXISTS (
      SELECT 1
      FROM public.detail_pengajuan AS dp
      WHERE dp.id_pengajuan = pp.id_pengajuan
  );
