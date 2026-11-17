-- Stored routines used by the classroom and request workflows.

CREATE SCHEMA IF NOT EXISTS app_owner AUTHORIZATION app_owner;

CREATE OR REPLACE FUNCTION app_owner.fn_buat_pengajuan_perubahan(
    p_id_siswa BIGINT,
    p_id_periode_update BIGINT,
    p_alasan TEXT
)
RETURNS BIGINT
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $function$
DECLARE
    v_id_pengajuan BIGINT;
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM public.periode_update
         WHERE id_periode_update = p_id_periode_update
           AND status = 'aktif'
           AND CURRENT_DATE BETWEEN tanggal_mulai AND tanggal_selesai
    ) THEN
        RAISE EXCEPTION 'Periode update data belum dibuka.';
    END IF;

    INSERT INTO public.pengajuan_perubahan (
        id_siswa,
        id_periode_update,
        tgl_pengajuan,
        status_pengajuan,
        alasan
    )
    VALUES (
        p_id_siswa,
        p_id_periode_update,
        CURRENT_TIMESTAMP,
        'menunggu',
        p_alasan
    )
    RETURNING id_pengajuan INTO v_id_pengajuan;

    RETURN v_id_pengajuan;
END;
$function$;
ALTER FUNCTION app_owner.fn_buat_pengajuan_perubahan(BIGINT, BIGINT, TEXT)
    OWNER TO app_owner;
REVOKE ALL ON FUNCTION app_owner.fn_buat_pengajuan_perubahan(BIGINT, BIGINT, TEXT) FROM PUBLIC;
GRANT USAGE ON SCHEMA app_owner TO role_siswa;
GRANT EXECUTE ON FUNCTION app_owner.fn_buat_pengajuan_perubahan(BIGINT, BIGINT, TEXT) TO role_siswa;

ALTER PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT)
    SECURITY DEFINER;
ALTER PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT)
    SET search_path = pg_catalog, public;
ALTER PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT)
    OWNER TO app_owner;

CREATE OR REPLACE PROCEDURE public.sp_naik_kelas(
    IN p_id_kelas BIGINT,
    IN p_id_tahun_ajaran BIGINT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_id_kelas IS NULL OR p_id_tahun_ajaran IS NULL THEN
        RAISE EXCEPTION 'Parameter kelas dan tahun ajaran harus diisi';
    END IF;

    UPDATE public.keanggotaan_kelas AS kk
       SET status = 'naik_kelas'
      FROM public.semester AS sm
     WHERE kk.id_semester = sm.id_semester
       AND kk.id_kelas = p_id_kelas
       AND sm.id_tahun_ajaran = p_id_tahun_ajaran
       AND kk.status = 'aktif';
END;
$$;

CREATE OR REPLACE PROCEDURE public.sp_proses_mutasi(
    IN p_id_siswa BIGINT,
    IN p_id_kelas_baru BIGINT,
    IN p_id_semester_baru BIGINT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_id_tahun_ajaran BIGINT;
BEGIN
    IF p_id_siswa IS NULL OR p_id_kelas_baru IS NULL OR p_id_semester_baru IS NULL THEN
        RAISE EXCEPTION 'ID siswa, kelas baru, dan semester baru harus diisi';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM public.siswa WHERE id_siswa = p_id_siswa
    ) THEN
        RAISE EXCEPTION 'Siswa dengan ID % tidak ditemukan.', p_id_siswa;
    END IF;

    SELECT id_tahun_ajaran
      INTO v_id_tahun_ajaran
      FROM public.semester
     WHERE id_semester = p_id_semester_baru;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Semester dengan ID % tidak ditemukan.', p_id_semester_baru;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM public.kelas
         WHERE id_kelas = p_id_kelas_baru
           AND id_tahun_ajaran = v_id_tahun_ajaran
           AND is_aktif
    ) THEN
        RAISE EXCEPTION 'Kelas % tidak aktif atau bukan bagian dari tahun ajaran semester %.',
            p_id_kelas_baru, p_id_semester_baru;
    END IF;

    IF EXISTS (
        SELECT 1
          FROM public.keanggotaan_kelas
         WHERE id_siswa = p_id_siswa
           AND id_semester = p_id_semester_baru
    ) THEN
        RAISE EXCEPTION 'Siswa % sudah memiliki keanggotaan pada semester %.',
            p_id_siswa, p_id_semester_baru;
    END IF;

    INSERT INTO public.keanggotaan_kelas (
        id_siswa, id_kelas, id_semester, tanggal_mulai, status
    )
    VALUES (p_id_siswa, p_id_kelas_baru, p_id_semester_baru, CURRENT_DATE, 'aktif');
END;
$$;

CREATE OR REPLACE PROCEDURE public.sp_finalisasi_rapor(
    IN p_id_rapor BIGINT,
    IN p_status_rapor VARCHAR(30)
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_id_rapor IS NULL THEN
        RAISE EXCEPTION 'ID rapor harus diisi';
    END IF;

    UPDATE public.rapor
    SET status_rapor = COALESCE(p_status_rapor, 'final')
    WHERE id_rapor = p_id_rapor;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Rapor dengan ID % tidak ditemukan.', p_id_rapor;
    END IF;
END;
$$;
