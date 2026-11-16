-- Query examples are restricted to the Fase 1 demo student.

SELECT s.id_siswa, s.nama_lengkap, st.nama_status
FROM public.siswa AS s
JOIN public.status_siswa AS st ON st.id_status_siswa = s.id_status_siswa
WHERE s.nis = 'FASE1-DEMO-001';

INSERT INTO public.nilai_siswa (
    id_anggota, id_mapel, id_semester, id_komponen, id_penginput, nilai
)
SELECT kk.id_keanggotaan, mp.id_mata_pelajaran, kk.id_semester,
       kp.id_komponen, g.id_guru, 87.5
FROM public.siswa AS s
JOIN public.keanggotaan_kelas AS kk
  ON kk.id_siswa = s.id_siswa
 AND kk.status = 'aktif'
CROSS JOIN LATERAL (
    SELECT id_mata_pelajaran
    FROM public.mata_pelajaran
    WHERE is_aktif
    ORDER BY id_mata_pelajaran
    LIMIT 1
) AS mp
CROSS JOIN LATERAL (
    SELECT id_komponen
    FROM public.komponen_penilaian
    ORDER BY id_komponen
    LIMIT 1
) AS kp
CROSS JOIN LATERAL (
    SELECT id_guru
    FROM public.guru
    WHERE is_aktif
    ORDER BY id_guru
    LIMIT 1
) AS g
WHERE s.nis = 'FASE1-DEMO-001'
  AND NOT EXISTS (
      SELECT 1
      FROM public.nilai_siswa AS existing
      WHERE existing.id_anggota = kk.id_keanggotaan
        AND existing.id_mapel = mp.id_mata_pelajaran
        AND existing.id_semester = kk.id_semester
        AND existing.id_komponen = kp.id_komponen
  )
ORDER BY kk.id_keanggotaan
LIMIT 1;

UPDATE public.konfirmasi_data AS kd
SET status = 'dikonfirmasi',
    dikonfirmasi_pada = CURRENT_TIMESTAMP
FROM public.siswa AS s, public.periode_update AS pu
WHERE kd.id_siswa = s.id_siswa
  AND kd.id_periode_update = pu.id_periode_update
  AND s.nis = 'FASE1-DEMO-001'
  AND pu.nama_periode = 'FASE1-DEMO-ONLY'
  AND pu.status = 'aktif'
  AND kd.status <> 'dikonfirmasi'
RETURNING kd.*;

DELETE FROM public.detail_pengajuan
WHERE id_pengajuan IN (
    SELECT pp.id_pengajuan
    FROM public.pengajuan_perubahan AS pp
    JOIN public.siswa AS s ON s.id_siswa = pp.id_siswa
    WHERE s.nis = 'FASE1-DEMO-001'
      AND pp.alasan = 'FASE1_DEMO'
);

DELETE FROM public.pengajuan_perubahan AS pp
USING public.siswa AS s
WHERE pp.id_siswa = s.id_siswa
  AND s.nis = 'FASE1-DEMO-001'
  AND pp.alasan = 'FASE1_DEMO'
RETURNING pp.*;

SELECT s.nama_lengkap, COUNT(n.id_nilai) AS jumlah_nilai
FROM public.siswa AS s
LEFT JOIN public.keanggotaan_kelas AS kk ON kk.id_siswa = s.id_siswa
LEFT JOIN public.nilai_siswa AS n ON n.id_anggota = kk.id_keanggotaan
WHERE s.nis = 'FASE1-DEMO-001'
GROUP BY s.id_siswa, s.nama_lengkap
HAVING COUNT(n.id_nilai) > 0;
