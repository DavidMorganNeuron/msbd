<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tabel = [
        'agama',
        'pekerjaan',
        'sekolah',
        'status_siswa',
        'jenis_dokumen',
        'ref_checklist_item',
        'ref_alasan_pengembalian',
        'ref_field_data',
        'penugasan_wali_kelas',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.fn_audit_trail()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public, pg_temp
AS $$
DECLARE
    v_id_data TEXT;
    v_pengguna_id BIGINT;
    v_username TEXT;
    v_old JSONB;
    v_new JSONB;
    v_sandi_diubah BOOLEAN := FALSE;
BEGIN
    v_pengguna_id := NULLIF(current_setting('app.user_id', TRUE), '')::BIGINT;
    v_username := NULLIF(current_setting('app.username', TRUE), '');

    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        v_old := to_jsonb(OLD);
    END IF;

    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        v_new := to_jsonb(NEW);
    END IF;

    IF TG_TABLE_NAME = 'pengguna' THEN
        IF TG_OP = 'UPDATE' THEN
            v_sandi_diubah := (v_old->>'password_hash') IS DISTINCT FROM (v_new->>'password_hash');
        END IF;
        v_old := v_old - 'password_hash';
        v_new := v_new - 'password_hash';

        IF v_sandi_diubah THEN
            v_new := v_new || jsonb_build_object('sandi_diubah', TRUE);
        END IF;
    END IF;

    CASE TG_TABLE_NAME
        WHEN 'siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_siswa';
        WHEN 'pengguna' THEN v_id_data := COALESCE(v_new, v_old)->>'id_pengguna';
        WHEN 'pengajuan_perubahan' THEN v_id_data := COALESCE(v_new, v_old)->>'id_pengajuan';
        WHEN 'alamat_siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_alamat';
        WHEN 'orang_tua_wali' THEN v_id_data := COALESCE(v_new, v_old)->>'id_orang_tua_wali';
        WHEN 'dokumen_siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_dokumen';
        WHEN 'keanggotaan_kelas' THEN v_id_data := COALESCE(v_new, v_old)->>'id_keanggotaan';
        WHEN 'kelas' THEN v_id_data := COALESCE(v_new, v_old)->>'id_kelas';
        WHEN 'tahun_ajaran' THEN v_id_data := COALESCE(v_new, v_old)->>'id_tahun_ajaran';
        WHEN 'semester' THEN v_id_data := COALESCE(v_new, v_old)->>'id_semester';
        WHEN 'konfirmasi_data' THEN v_id_data := COALESCE(v_new, v_old)->>'id_konfirmasi';
        WHEN 'periode_update' THEN v_id_data := COALESCE(v_new, v_old)->>'id_periode_update';
        WHEN 'nilai_siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_nilai';
        WHEN 'rapor' THEN v_id_data := COALESCE(v_new, v_old)->>'id_rapor';
        WHEN 'mutasi_siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_mutasi';
        WHEN 'pendaftaran_ppdb' THEN v_id_data := COALESCE(v_new, v_old)->>'id_pendaftaran';
        WHEN 'agama' THEN v_id_data := COALESCE(v_new, v_old)->>'id_agama';
        WHEN 'pekerjaan' THEN v_id_data := COALESCE(v_new, v_old)->>'id_pekerjaan';
        WHEN 'sekolah' THEN v_id_data := COALESCE(v_new, v_old)->>'id_sekolah';
        WHEN 'status_siswa' THEN v_id_data := COALESCE(v_new, v_old)->>'id_status_siswa';
        WHEN 'jenis_dokumen' THEN v_id_data := COALESCE(v_new, v_old)->>'id_jenis_dokumen';
        WHEN 'ref_checklist_item' THEN v_id_data := COALESCE(v_new, v_old)->>'id_item';
        WHEN 'ref_alasan_pengembalian' THEN v_id_data := COALESCE(v_new, v_old)->>'id_alasan';
        WHEN 'ref_field_data' THEN v_id_data := COALESCE(v_new, v_old)->>'id_field';
        WHEN 'penugasan_wali_kelas' THEN v_id_data := COALESCE(v_new, v_old)->>'id_penugasan_wali_kelas';
        ELSE v_id_data := NULL;
    END CASE;

    INSERT INTO public.log_audit (
        nama_tabel, operasi, id_data, nilai_lama, nilai_baru,
        id_pengguna_pengubah, username_pengubah, waktu
    )
    VALUES (
        TG_TABLE_NAME, TG_OP, v_id_data, v_old, v_new,
        v_pengguna_id, v_username, CURRENT_TIMESTAMP
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;
SQL);

            foreach ($this->tabel as $tabel) {
                $trigger = 'trg_audit_'.$tabel;

                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON public.{$tabel}");
                DB::statement("CREATE TRIGGER {$trigger} AFTER INSERT OR UPDATE OR DELETE ON public.{$tabel} FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail()");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Trigger audit dipertahankan agar rollback tidak membuat celah pencatatan.
    }
};
