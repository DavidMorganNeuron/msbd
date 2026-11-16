-- DRAFT / USULAN PERUBAHAN — JANGAN DIJALANKAN TANPA VERIFIKASI & PERSETUJUAN
-- Tujuan: memperluas public.sp_setujui_pengajuan agar dapat menerapkan perubahan
-- field alamat_siswa dan orang_tua_wali (sesuai ref_field_data) selain field siswa
-- yang sudah didukung. Berdasarkan analisis skema/seeder (schema_backup.sql +
-- ReferensiDataSeeder::fieldPengajuan). BELUM diverifikasi terhadap struktur/isi
-- database aktif dan definisi SP aktif (koneksi DB tidak tersedia saat disusun).
--
-- Sebelum eksekusi: verifikasi definisi SP aktif, isi aktual ref_field_data,
-- constraint/tipe/relasi, dan uji pada data aman/transaksi. Siapkan rollback
-- (simpan definisi SP lama). Perubahan definisi SP existing memerlukan persetujuan.

CREATE OR REPLACE PROCEDURE public.sp_setujui_pengajuan(
    IN p_id_pengajuan bigint,
    IN p_id_pemeriksa bigint,
    IN p_catatan text DEFAULT NULL::text
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_id_siswa BIGINT;
    v_status VARCHAR(30);
    v_detail RECORD;
    v_jumlah_detail INTEGER := 0;
    v_nilai TEXT;
    v_hubungan TEXT;
    v_kolom_otw TEXT;
BEGIN
    -- 1. Kunci pengajuan agar tidak diproses ganda.
    SELECT id_siswa, status_pengajuan
    INTO v_id_siswa, v_status
    FROM pengajuan_perubahan
    WHERE id_pengajuan = p_id_pengajuan
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Pengajuan dengan ID % tidak ditemukan.', p_id_pengajuan;
    END IF;

    -- 2. Hanya pengajuan menunggu yang dapat diproses.
    IF v_status <> 'menunggu' THEN
        RAISE EXCEPTION 'Pengajuan tidak dapat diproses karena statusnya %.', v_status;
    END IF;

    -- 3. Pemeriksa harus valid.
    IF NOT EXISTS (SELECT 1 FROM pengguna WHERE id_pengguna = p_id_pemeriksa) THEN
        RAISE EXCEPTION 'Pengguna pemeriksa dengan ID % tidak ditemukan.', p_id_pemeriksa;
    END IF;

    -- 4. Terapkan setiap perubahan (relasi selalu ke v_id_siswa milik pengajuan).
    FOR v_detail IN
        SELECT nama_field, nilai_baru
        FROM detail_pengajuan
        WHERE id_pengajuan = p_id_pengajuan
        ORDER BY id_detail_pengajuan
    LOOP
        v_jumlah_detail := v_jumlah_detail + 1;
        v_nilai := v_detail.nilai_baru #>> '{}';

        CASE v_detail.nama_field
            -- ===== Tabel siswa (dukungan existing, dipertahankan) =====
            WHEN 'nama_lengkap' THEN
                UPDATE siswa SET nama_lengkap = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'jenis_kelamin' THEN
                UPDATE siswa SET jenis_kelamin = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'tempat_lahir' THEN
                UPDATE siswa SET tempat_lahir = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'tanggal_lahir' THEN
                UPDATE siswa SET tanggal_lahir = NULLIF(v_nilai, '')::DATE, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'email' THEN
                UPDATE siswa SET email = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'no_telepon' THEN
                UPDATE siswa SET no_telepon = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'nis' THEN
                UPDATE siswa SET nis = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'nisn' THEN
                UPDATE siswa SET nisn = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;
            WHEN 'nik' THEN
                UPDATE siswa SET nik = v_nilai, diubah_pada = CURRENT_TIMESTAMP
                WHERE id_siswa = v_id_siswa;

            -- ===== Tabel alamat_siswa (1-1 per id_siswa, UNIQUE id_siswa) =====
            -- Kolom: alamat (NOT NULL), rt,rw,kelurahan,kecamatan,kota,provinsi,kode_pos
            WHEN 'alamat' THEN
                UPDATE alamat_siswa SET alamat = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    INSERT INTO alamat_siswa (id_siswa, alamat) VALUES (v_id_siswa, v_nilai);
                END IF;
            WHEN 'rt' THEN
                UPDATE alamat_siswa SET rt = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'rw' THEN
                UPDATE alamat_siswa SET rw = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'kelurahan' THEN
                UPDATE alamat_siswa SET kelurahan = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'kecamatan' THEN
                UPDATE alamat_siswa SET kecamatan = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'kota' THEN
                UPDATE alamat_siswa SET kota = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'provinsi' THEN
                UPDATE alamat_siswa SET provinsi = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;
            WHEN 'kode_pos' THEN
                UPDATE alamat_siswa SET kode_pos = v_nilai WHERE id_siswa = v_id_siswa;
                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Data alamat siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi alamat terlebih dahulu.', v_detail.nama_field;
                END IF;

            ELSE
                -- ===== Tabel orang_tua_wali (per id_siswa + hubungan ayah/ibu/wali) =====
                -- nama_kolom kanonik: {ayah|ibu|wali}_{nama_lengkap|no_telepon|email|alamat}
                IF v_detail.nama_field ~ '^(ayah|ibu|wali)_(nama_lengkap|no_telepon|email|alamat)$' THEN
                    v_hubungan := split_part(v_detail.nama_field, '_', 1);          -- ayah|ibu|wali
                    v_kolom_otw := substring(v_detail.nama_field from position('_' in v_detail.nama_field) + 1);

                    IF v_kolom_otw = 'nama_lengkap' THEN
                        UPDATE orang_tua_wali SET nama_lengkap = v_nilai
                        WHERE id_siswa = v_id_siswa AND hubungan = v_hubungan;
                        IF NOT FOUND THEN
                            INSERT INTO orang_tua_wali (id_siswa, hubungan, nama_lengkap)
                            VALUES (v_id_siswa, v_hubungan, v_nilai);
                        END IF;
                    ELSIF v_kolom_otw = 'no_telepon' THEN
                        UPDATE orang_tua_wali SET no_telepon = v_nilai
                        WHERE id_siswa = v_id_siswa AND hubungan = v_hubungan;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Data % siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi nama % terlebih dahulu.', v_hubungan, v_detail.nama_field, v_hubungan;
                        END IF;
                    ELSIF v_kolom_otw = 'email' THEN
                        UPDATE orang_tua_wali SET email = v_nilai
                        WHERE id_siswa = v_id_siswa AND hubungan = v_hubungan;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Data % siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi nama % terlebih dahulu.', v_hubungan, v_detail.nama_field, v_hubungan;
                        END IF;
                    ELSIF v_kolom_otw = 'alamat' THEN
                        UPDATE orang_tua_wali SET alamat = v_nilai
                        WHERE id_siswa = v_id_siswa AND hubungan = v_hubungan;
                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'Data % siswa belum tersedia, sehingga field "%" tidak dapat diubah. Lengkapi nama % terlebih dahulu.', v_hubungan, v_detail.nama_field, v_hubungan;
                        END IF;
                    ELSE
                        RAISE EXCEPTION 'Field perubahan "%" belum diizinkan.', v_detail.nama_field;
                    END IF;
                ELSE
                    RAISE EXCEPTION 'Field perubahan "%" belum diizinkan.', v_detail.nama_field;
                END IF;
        END CASE;
    END LOOP;

    -- 5. Tolak pengajuan kosong.
    IF v_jumlah_detail = 0 THEN
        RAISE EXCEPTION 'Pengajuan tidak memiliki detail perubahan.';
    END IF;

    -- 6. Tandai disetujui setelah semua perubahan berhasil (atomik dalam transaksi).
    UPDATE pengajuan_perubahan
    SET status_pengajuan = 'disetujui',
        id_pemeriksa = p_id_pemeriksa,
        catatan_pemeriksa = p_catatan,
        diputuskan_pada = CURRENT_TIMESTAMP
    WHERE id_pengajuan = p_id_pengajuan;

END;
$$;

-- CATATAN VERIFIKASI (DB aktif belum dapat diakses):
-- 1. Pastikan definisi SP aktif saat ini identik/kompatibel dengan backup sebelum replace.
-- 2. Pastikan isi aktual ref_field_data (nama_tabel/nama_kolom/boleh_diubah_siswa/perlu_bukti)
--    selaras; field di luar whitelist/pola di atas tetap ditolak (aman).
-- 3. alamat_siswa.alamat NOT NULL: insert hanya aman saat field 'alamat'; field alamat lain
--    butuh baris alamat ada (ditolak eksplisit jika belum ada). Verifikasi kebutuhan bisnis.
-- 4. orang_tua_wali.nama_lengkap NOT NULL & UNIQUE(id_siswa,hubungan): insert hanya saat
--    nama_lengkap; kolom lain butuh baris hubungan ada. Pastikan tidak ada penukaran hubungan.
-- 5. Tipe/constraint (CHECK jenis_kelamin, UNIQUE nik/nis/nisn, panjang kolom, NULLability)
--    dapat memicu exception -> rollback otomatis; uji skenario gagal & tidak setengah jadi.
-- 6. Uji pada data aman: siswa/alamat/ayah/ibu/wali, tolak/kembalikan/proses-ulang,
--    isolasi antar siswa, notifikasi #29 & riwayat, akses guru.
