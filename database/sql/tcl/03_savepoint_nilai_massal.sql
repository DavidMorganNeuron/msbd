BEGIN;
INSERT INTO public.nilai_siswa (
    id_anggota, id_mapel, id_semester, id_komponen, id_penginput, nilai
)
SELECT kk.id_keanggotaan, mp.id_mata_pelajaran, kk.id_semester,
       kp.id_komponen, g.id_guru, 88.0
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
      SELECT 1 FROM public.nilai_siswa AS n
      WHERE n.id_anggota = kk.id_keanggotaan
        AND n.id_mapel = mp.id_mata_pelajaran
        AND n.id_semester = kk.id_semester
        AND n.id_komponen = kp.id_komponen
  )
ORDER BY kk.id_keanggotaan
LIMIT 1;
SAVEPOINT s2;
INSERT INTO public.nilai_siswa (
    id_anggota, id_mapel, id_semester, id_komponen, id_penginput, nilai
)
SELECT kk.id_keanggotaan, mp.id_mata_pelajaran, kk.id_semester,
       kp.id_komponen, g.id_guru, 92.0
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
      SELECT 1 FROM public.nilai_siswa AS n
      WHERE n.id_anggota = kk.id_keanggotaan
        AND n.id_mapel = mp.id_mata_pelajaran
        AND n.id_semester = kk.id_semester
        AND n.id_komponen = kp.id_komponen
  )
ORDER BY kk.id_keanggotaan
LIMIT 1;
ROLLBACK TO SAVEPOINT s2;
ROLLBACK;
