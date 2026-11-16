-- Core SQL functions used by the reporting and student workflow.

CREATE OR REPLACE FUNCTION public.fn_predikat_nilai(p_nilai NUMERIC)
RETURNS VARCHAR(10)
LANGUAGE sql
STABLE
AS $$
    SELECT CASE
        WHEN p_nilai >= 85 THEN 'A'
        WHEN p_nilai >= 75 THEN 'B'
        WHEN p_nilai >= 65 THEN 'C'
        WHEN p_nilai >= 55 THEN 'D'
        ELSE 'E'
    END;
$$;

CREATE OR REPLACE FUNCTION public.fn_rata_nilai_siswa(p_id_siswa BIGINT)
RETURNS NUMERIC
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(AVG(ns.nilai), 0)
    FROM public.nilai_siswa AS ns
    JOIN public.keanggotaan_kelas AS kk ON kk.id_keanggotaan = ns.id_anggota
    WHERE kk.id_siswa = p_id_siswa;
$$;

CREATE OR REPLACE FUNCTION public.fn_persen_kehadiran(p_id_siswa BIGINT)
RETURNS NUMERIC
LANGUAGE sql
STABLE
AS $$
    WITH kehadiran AS (
        SELECT COUNT(*) AS total,
               COUNT(*) FILTER (WHERE lower(sh.kode) IN ('h', 'hadir')) AS hadir
        FROM public.detail_absensi AS da
        JOIN public.status_kehadiran AS sh ON sh.id_status_hadir = da.id_status_hadir
        WHERE da.id_siswa = p_id_siswa
    )
    SELECT CASE
        WHEN kehadiran.total = 0 THEN 0
        ELSE ROUND((kehadiran.hadir::NUMERIC / kehadiran.total::NUMERIC) * 100, 2)
    END
    FROM kehadiran;
$$;

CREATE OR REPLACE FUNCTION public.fn_semester_aktif()
RETURNS BIGINT
LANGUAGE sql
STABLE
AS $$
    SELECT id_semester
    FROM public.semester
    WHERE is_aktif
    ORDER BY tanggal_mulai DESC NULLS LAST, id_semester DESC
    LIMIT 1;
$$;

CREATE OR REPLACE FUNCTION public.fn_persen_konfirmasi_periode(p_id_periode_update BIGINT)
RETURNS NUMERIC
LANGUAGE sql
STABLE
AS $$
    WITH total AS (
        SELECT COUNT(*) AS total_siswa FROM public.siswa
    ), konfirmasi AS (
        SELECT COUNT(DISTINCT id_siswa) AS total_siswa
        FROM public.konfirmasi_data
        WHERE id_periode_update = p_id_periode_update
          AND status = 'dikonfirmasi'
    )
    SELECT CASE
        WHEN total.total_siswa = 0 THEN 0
        ELSE ROUND((konfirmasi.total_siswa::NUMERIC / total.total_siswa::NUMERIC) * 100, 2)
    END
    FROM total, konfirmasi;
$$;

CREATE OR REPLACE FUNCTION public.fn_jumlah_siswa_kelas(p_id_kelas BIGINT)
RETURNS BIGINT
LANGUAGE sql
STABLE
AS $$
    SELECT COUNT(*)
    FROM public.keanggotaan_kelas AS k
    WHERE k.id_kelas = p_id_kelas
      AND k.status = 'aktif';
$$;
