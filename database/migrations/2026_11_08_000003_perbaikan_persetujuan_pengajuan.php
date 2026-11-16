<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_perubahan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_alasan_pengembalian')->nullable();
            $table->foreign('id_alasan_pengembalian')
                ->references('id_alasan')
                ->on('ref_alasan_pengembalian')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE dokumen_siswa ALTER COLUMN status_verifikasi SET DEFAULT 'menunggu'");
        DB::statement('ALTER TABLE dokumen_siswa ALTER COLUMN diunggah_pada SET DEFAULT CURRENT_TIMESTAMP');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_ref_field_data_tabel_kolom ON ref_field_data (nama_tabel, nama_kolom)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE PROCEDURE public.sp_setujui_pengajuan(
    IN p_id_pengajuan bigint,
    IN p_id_pemeriksa bigint,
    IN p_catatan text DEFAULT NULL::text
)
LANGUAGE plpgsql
AS $procedure$
DECLARE
    v_id_siswa bigint;
    v_status varchar(30);
    v_detail record;
    v_field ref_field_data%ROWTYPE;
    v_kolom text;
    v_nilai text;
    v_hubungan text;
    v_target_id bigint;
    v_jumlah_detail integer := 0;
BEGIN
    SELECT id_siswa, status_pengajuan
      INTO v_id_siswa, v_status
      FROM pengajuan_perubahan
     WHERE id_pengajuan = p_id_pengajuan
       FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Pengajuan dengan ID % tidak ditemukan.', p_id_pengajuan;
    END IF;

    IF v_status <> 'menunggu' THEN
        RAISE EXCEPTION 'Pengajuan tidak dapat diproses karena statusnya %.', v_status;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pengguna WHERE id_pengguna = p_id_pemeriksa) THEN
        RAISE EXCEPTION 'Pengguna pemeriksa dengan ID % tidak ditemukan.', p_id_pemeriksa;
    END IF;

    FOR v_detail IN
        SELECT nama_field, nilai_baru, bukti_path
          FROM detail_pengajuan
         WHERE id_pengajuan = p_id_pengajuan
         ORDER BY id_detail_pengajuan
    LOOP
        v_jumlah_detail := v_jumlah_detail + 1;

        SELECT * INTO v_field
          FROM ref_field_data
         WHERE nama_tabel IN ('siswa', 'alamat_siswa', 'orang_tua_wali')
           AND nama_kolom = v_detail.nama_field
           AND boleh_diubah_siswa = true
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
            v_nilai := v_detail.nilai_baru::text;
        END IF;

        IF v_field.nama_tabel = 'siswa' THEN
            IF v_field.nama_kolom NOT IN (
                'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
                'email', 'no_telepon', 'nis', 'nisn', 'nik'
            ) THEN
                RAISE EXCEPTION 'Kolom siswa "%" tidak boleh diubah.', v_field.nama_kolom;
            END IF;

            IF NOT EXISTS (SELECT 1 FROM siswa WHERE id_siswa = v_id_siswa) THEN
                RAISE EXCEPTION 'Siswa dengan ID % tidak ditemukan.', v_id_siswa;
            END IF;

            IF v_field.nama_kolom = 'tanggal_lahir' THEN
                UPDATE siswa
                   SET tanggal_lahir = NULLIF(v_nilai, '')::date,
                       diubah_pada = CURRENT_TIMESTAMP
                 WHERE id_siswa = v_id_siswa;
            ELSE
                EXECUTE format(
                    'UPDATE siswa SET %I = $1, diubah_pada = CURRENT_TIMESTAMP WHERE id_siswa = $2',
                    v_field.nama_kolom
                ) USING v_nilai, v_id_siswa;
            END IF;

        ELSIF v_field.nama_tabel = 'alamat_siswa' THEN
            IF v_field.nama_kolom NOT IN (
                'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kota', 'provinsi', 'kode_pos'
            ) THEN
                RAISE EXCEPTION 'Kolom alamat "%" tidak boleh diubah.', v_field.nama_kolom;
            END IF;

            SELECT id_alamat INTO v_target_id
              FROM alamat_siswa
             WHERE id_siswa = v_id_siswa
             ORDER BY id_alamat
             LIMIT 1;

            IF v_target_id IS NULL THEN
                INSERT INTO alamat_siswa (id_siswa, alamat)
                VALUES (v_id_siswa, '')
                RETURNING id_alamat INTO v_target_id;
            END IF;

            EXECUTE format(
                'UPDATE alamat_siswa SET %I = $1 WHERE id_alamat = $2',
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

            SELECT id_orang_tua_wali INTO v_target_id
              FROM orang_tua_wali
             WHERE id_siswa = v_id_siswa
               AND lower(hubungan) = lower(v_hubungan)
             ORDER BY id_orang_tua_wali
             LIMIT 1;

            IF v_target_id IS NULL THEN
                INSERT INTO orang_tua_wali (id_siswa, hubungan, nama_lengkap)
                VALUES (v_id_siswa, v_hubungan, '')
                RETURNING id_orang_tua_wali INTO v_target_id;
            END IF;

            EXECUTE format(
                'UPDATE orang_tua_wali SET %I = $1 WHERE id_orang_tua_wali = $2',
                v_kolom
            ) USING v_nilai, v_target_id;
        END IF;
    END LOOP;

    IF v_jumlah_detail = 0 THEN
        RAISE EXCEPTION 'Pengajuan tidak memiliki detail perubahan.';
    END IF;

    UPDATE pengajuan_perubahan
       SET status_pengajuan = 'disetujui',
           id_pemeriksa = p_id_pemeriksa,
           catatan_pemeriksa = p_catatan,
           diputuskan_pada = CURRENT_TIMESTAMP
     WHERE id_pengajuan = p_id_pengajuan;
END;
$procedure$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS public.sp_setujui_pengajuan(bigint, bigint, text)');

        DB::statement('DROP INDEX IF EXISTS uq_ref_field_data_tabel_kolom');

        Schema::table('pengajuan_perubahan', function (Blueprint $table) {
            $table->dropForeign(['id_alasan_pengembalian']);
            $table->dropColumn('id_alasan_pengembalian');
        });
    }
};
