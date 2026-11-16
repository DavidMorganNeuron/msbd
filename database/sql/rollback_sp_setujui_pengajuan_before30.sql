CREATE OR REPLACE PROCEDURE public.sp_setujui_pengajuan(IN p_id_pengajuan bigint, IN p_id_pemeriksa bigint, IN p_catatan text DEFAULT NULL::text)
 LANGUAGE plpgsql
AS $procedure$
DECLARE
    v_id_siswa BIGINT;
    v_status VARCHAR(30);
    v_detail RECORD;
    v_jumlah_detail INTEGER := 0;
BEGIN
    -- 1. Periksa pengajuan dan kunci baris agar tidak diproses
    -- bersamaan oleh petugas lain.
    SELECT id_siswa, status_pengajuan
    INTO v_id_siswa, v_status
    FROM pengajuan_perubahan
    WHERE id_pengajuan = p_id_pengajuan
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION
            'Pengajuan dengan ID % tidak ditemukan.',
            p_id_pengajuan;
    END IF;

    -- 2. Pastikan pengajuan masih menunggu persetujuan.
    IF v_status <> 'menunggu' THEN
        RAISE EXCEPTION
            'Pengajuan tidak dapat diproses karena statusnya %.',
            v_status;
    END IF;

    -- 3. Pastikan petugas pemeriksa tersedia.
    IF NOT EXISTS (
        SELECT 1
        FROM pengguna
        WHERE id_pengguna = p_id_pemeriksa
    ) THEN
        RAISE EXCEPTION
            'Pengguna pemeriksa dengan ID % tidak ditemukan.',
            p_id_pemeriksa;
    END IF;

    -- 4. Terapkan setiap perubahan yang diizinkan.
    FOR v_detail IN
        SELECT nama_field, nilai_baru
        FROM detail_pengajuan
        WHERE id_pengajuan = p_id_pengajuan
        ORDER BY id_detail_pengajuan
    LOOP
        v_jumlah_detail := v_jumlah_detail + 1;

        CASE v_detail.nama_field

            WHEN 'nama_lengkap' THEN
                UPDATE siswa
                SET nama_lengkap = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'jenis_kelamin' THEN
                UPDATE siswa
                SET jenis_kelamin = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'tempat_lahir' THEN
                UPDATE siswa
                SET tempat_lahir = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'tanggal_lahir' THEN
                UPDATE siswa
                SET tanggal_lahir =
                    NULLIF(v_detail.nilai_baru #>> '{}', '')::DATE,
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'email' THEN
                UPDATE siswa
                SET email = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'no_telepon' THEN
                UPDATE siswa
                SET no_telepon = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'nis' THEN
                UPDATE siswa
                SET nis = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'nisn' THEN
                UPDATE siswa
                SET nisn = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            WHEN 'nik' THEN
                UPDATE siswa
                SET nik = v_detail.nilai_baru #>> '{}',
                    diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            ELSE
                RAISE EXCEPTION
                    'Field perubahan "%" belum diizinkan.',
                    v_detail.nama_field;

        END CASE;
    END LOOP;

    -- 5. Tolak pengajuan kosong.
    IF v_jumlah_detail = 0 THEN
        RAISE EXCEPTION
            'Pengajuan tidak memiliki detail perubahan.';
    END IF;

    -- 6. Perbarui status pengajuan setelah semua perubahan berhasil.
    UPDATE pengajuan_perubahan
    SET status_pengajuan = 'disetujui',
        id_pemeriksa = p_id_pemeriksa,
        catatan_pemeriksa = p_catatan,
        diputuskan_pada = CURRENT_TIMESTAMP
    WHERE id_pengajuan = p_id_pengajuan;

END;
$procedure$
