-- Stored procedures used by the classroom and approval workflow.

CREATE OR REPLACE PROCEDURE public.sp_setujui_pengajuan(
    IN p_id_pengajuan BIGINT,
    IN p_id_pemeriksa BIGINT,
    IN p_catatan TEXT DEFAULT NULL
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_id_siswa BIGINT;
    v_status VARCHAR(30);
    v_detail RECORD;
    v_field public.ref_field_data%ROWTYPE;
    v_kolom TEXT;
    v_nilai TEXT;
    v_hubungan TEXT;
    v_target_id BIGINT;
    v_jumlah_detail INTEGER := 0;
BEGIN
    SELECT id_siswa, status_pengajuan
      INTO v_id_siswa, v_status
      FROM public.pengajuan_perubahan
     WHERE id_pengajuan = p_id_pengajuan
       FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Pengajuan dengan ID % tidak ditemukan.', p_id_pengajuan;
    END IF;

    IF v_status <> 'menunggu' THEN
        RAISE EXCEPTION 'Pengajuan tidak dapat diproses karena statusnya %.', v_status;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM public.pengguna
        WHERE id_pengguna = p_id_pemeriksa
    ) THEN
        RAISE EXCEPTION 'Pengguna pemeriksa dengan ID % tidak ditemukan.', p_id_pemeriksa;
    END IF;

    FOR v_detail IN
        SELECT nama_field, nilai_baru, bukti_path
          FROM public.detail_pengajuan
         WHERE id_pengajuan = p_id_pengajuan
         ORDER BY id_detail_pengajuan
    LOOP
        v_jumlah_detail := v_jumlah_detail + 1;

        SELECT *
          INTO v_field
          FROM public.ref_field_data
         WHERE nama_tabel IN ('siswa', 'alamat_siswa', 'orang_tua_wali')
           AND nama_kolom = v_detail.nama_field
           AND boleh_diubah_siswa = TRUE
         LIMIT 1;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Field perubahan "%" belum diizinkan.', v_detail.nama_field;
        END IF;

        IF v_field.perlu_bukti AND COALESCE(v_detail.bukti_path, '') = '' THEN
            RAISE EXCEPTION 'Field "%" wajib dilengkapi bukti.', v_field.label_field;
        END IF;

        IF v_detail.nilai_baru IS NULL THEN
            CONTINUE;
        END IF;

        IF jsonb_typeof(v_detail.nilai_baru) = 'string' THEN
            v_nilai := v_detail.nilai_baru #>> '{}';
        ELSE
            v_nilai := v_detail.nilai_baru::TEXT;
        END IF;

        IF v_field.nama_tabel = 'siswa' THEN
            IF v_field.nama_kolom NOT IN (
                'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                'email', 'no_telepon', 'nis', 'nisn', 'nik'
            ) THEN
                RAISE EXCEPTION 'Kolom siswa "%" tidak boleh diubah.', v_field.nama_kolom;
            END IF;

            IF NOT EXISTS (
                SELECT 1 FROM public.siswa WHERE id_siswa = v_id_siswa
            ) THEN
                RAISE EXCEPTION 'Siswa dengan ID % tidak ditemukan.', v_id_siswa;
            END IF;

            IF v_field.nama_kolom = 'tanggal_lahir' THEN
                UPDATE public.siswa
                   SET tanggal_lahir = NULLIF(v_nilai, '')::DATE,
                       diubah_pada = CURRENT_TIMESTAMP
                 WHERE id_siswa = v_id_siswa;
            ELSE
                EXECUTE format(
                    'UPDATE public.siswa SET %I = $1, diubah_pada = CURRENT_TIMESTAMP WHERE id_siswa = $2',
                    v_field.nama_kolom
                ) USING v_nilai, v_id_siswa;
            END IF;
        ELSIF v_field.nama_tabel = 'alamat_siswa' THEN
            IF v_field.nama_kolom NOT IN (
                'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kode_pos'
            ) THEN
                RAISE EXCEPTION 'Kolom alamat "%" tidak boleh diubah.', v_field.nama_kolom;
            END IF;

            SELECT id_alamat
              INTO v_target_id
              FROM public.alamat_siswa
             WHERE id_siswa = v_id_siswa
             ORDER BY id_alamat
             LIMIT 1;

            IF v_target_id IS NULL THEN
                INSERT INTO public.alamat_siswa (id_siswa, alamat)
                VALUES (v_id_siswa, '')
                RETURNING id_alamat INTO v_target_id;
            END IF;

            EXECUTE format(
                'UPDATE public.alamat_siswa SET %I = $1 WHERE id_alamat = $2',
                v_field.nama_kolom
            ) USING v_nilai, v_target_id;
        ELSE
            v_hubungan := CASE split_part(v_field.nama_kolom, '_', 1)
                WHEN 'ayah' THEN 'ayah'
                WHEN 'ibu' THEN 'ibu'
                ELSE 'wali'
            END;
            v_kolom := regexp_replace(v_field.nama_kolom, '^(ayah|ibu|wali)_', '');

            IF v_kolom NOT IN ('nama_lengkap', 'no_telepon', 'email', 'alamat') THEN
                RAISE EXCEPTION 'Kolom orang tua/wali "%" tidak boleh diubah.', v_kolom;
            END IF;

            SELECT id_orang_tua_wali
              INTO v_target_id
              FROM public.orang_tua_wali
             WHERE id_siswa = v_id_siswa
               AND lower(hubungan) = lower(v_hubungan)
             ORDER BY id_orang_tua_wali
             LIMIT 1;

            IF v_target_id IS NULL THEN
                INSERT INTO public.orang_tua_wali (id_siswa, hubungan, nama_lengkap)
                VALUES (v_id_siswa, v_hubungan, '')
                RETURNING id_orang_tua_wali INTO v_target_id;
            END IF;

            EXECUTE format(
                'UPDATE public.orang_tua_wali SET %I = $1 WHERE id_orang_tua_wali = $2',
                v_kolom
            ) USING v_nilai, v_target_id;
        END IF;
    END LOOP;

    IF v_jumlah_detail = 0 THEN
        RAISE EXCEPTION 'Pengajuan tidak memiliki detail perubahan.';
    END IF;

    UPDATE public.pengajuan_perubahan
       SET status_pengajuan = 'disetujui',
           id_pemeriksa = p_id_pemeriksa,
           catatan_pemeriksa = p_catatan,
           diputuskan_pada = CURRENT_TIMESTAMP
     WHERE id_pengajuan = p_id_pengajuan;
END;
$$;

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
