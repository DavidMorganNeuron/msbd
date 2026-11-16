-- Audit trail trigger for the school transaction tables.

CREATE OR REPLACE FUNCTION public.fn_audit_trail()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    v_id_data TEXT;
    v_pengguna_id BIGINT;
    v_username TEXT;
    v_old JSONB := NULL;
    v_new JSONB := NULL;
BEGIN
    v_pengguna_id := NULLIF(current_setting('app.user_id', true), '')::BIGINT;
    v_username := NULLIF(current_setting('app.username', true), '');

    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        v_old := to_jsonb(OLD);
    END IF;

    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        v_new := to_jsonb(NEW);
    END IF;

    CASE TG_TABLE_NAME
        WHEN 'siswa' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_siswa';
        WHEN 'pengguna' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_pengguna';
        WHEN 'pengajuan_perubahan' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_pengajuan';
        WHEN 'konfirmasi_data' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_konfirmasi';
        WHEN 'periode_update' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_periode_update';
        WHEN 'nilai_siswa' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_nilai';
        WHEN 'rapor' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_rapor';
        ELSE
            v_id_data := COALESCE(v_new, v_old)->>'id';
    END CASE;

    INSERT INTO public.log_audit (
        nama_tabel,
        operasi,
        id_data,
        nilai_lama,
        nilai_baru,
        id_pengguna_pengubah,
        username_pengubah,
        waktu
    ) VALUES (
        TG_TABLE_NAME,
        TG_OP,
        v_id_data,
        v_old,
        v_new,
        v_pengguna_id,
        v_username,
        CURRENT_TIMESTAMP
    );

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_audit_siswa ON public.siswa;
CREATE TRIGGER trg_audit_siswa
AFTER INSERT OR UPDATE OR DELETE ON public.siswa
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_pengguna ON public.pengguna;
CREATE TRIGGER trg_audit_pengguna
AFTER INSERT OR UPDATE OR DELETE ON public.pengguna
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_pengajuan ON public.pengajuan_perubahan;
CREATE TRIGGER trg_audit_pengajuan
AFTER INSERT OR UPDATE OR DELETE ON public.pengajuan_perubahan
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_konfirmasi_data ON public.konfirmasi_data;
CREATE TRIGGER trg_audit_konfirmasi_data
AFTER INSERT OR UPDATE OR DELETE ON public.konfirmasi_data
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_periode_update ON public.periode_update;
CREATE TRIGGER trg_audit_periode_update
AFTER INSERT OR UPDATE OR DELETE ON public.periode_update
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_nilai_siswa ON public.nilai_siswa;
CREATE TRIGGER trg_audit_nilai_siswa
AFTER INSERT OR UPDATE OR DELETE ON public.nilai_siswa
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_rapor ON public.rapor;
CREATE TRIGGER trg_audit_rapor
AFTER INSERT OR UPDATE OR DELETE ON public.rapor
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();
