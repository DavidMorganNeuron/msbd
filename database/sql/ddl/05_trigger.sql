-- Trigger function and trigger set for audit, validation, and enrollment logic.

SET client_min_messages = warning;

CREATE OR REPLACE FUNCTION public.fn_konfirmasi_terbuka()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_status_periode VARCHAR(30);
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT status
          INTO v_status_periode
          FROM public.periode_update
         WHERE id_periode_update = NEW.id_periode_update;
    ELSIF TG_OP = 'DELETE' THEN
        SELECT status
          INTO v_status_periode
          FROM public.periode_update
         WHERE id_periode_update = OLD.id_periode_update;
    ELSE
        SELECT status
          INTO v_status_periode
          FROM public.periode_update
         WHERE id_periode_update = OLD.id_periode_update;

        IF v_status_periode = 'ditutup' THEN
            RAISE EXCEPTION 'Periode konfirmasi sudah ditutup dan tidak bisa diubah';
        END IF;

        SELECT status
          INTO v_status_periode
          FROM public.periode_update
         WHERE id_periode_update = NEW.id_periode_update;
    END IF;

    IF v_status_periode = 'ditutup' THEN
        RAISE EXCEPTION 'Periode konfirmasi sudah ditutup dan tidak bisa diubah';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_blokir_ubah_log()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Log audit dan log login bersifat append-only dan tidak dapat diubah';
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_cek_penginput_wali_kelas()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF current_setting('app.role', true) IS DISTINCT FROM 'wali_kelas' THEN
        RAISE EXCEPTION 'Hanya wali kelas yang boleh menginput nilai dan absensi';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION public.fn_buat_konfirmasi_data()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_siswa RECORD;
BEGIN
    IF NEW.status = 'aktif' THEN
        FOR v_siswa IN SELECT id_siswa FROM public.siswa LOOP
            INSERT INTO public.konfirmasi_data (id_periode_update, id_siswa, status)
            VALUES (NEW.id_periode_update, v_siswa.id_siswa, 'belum')
            ON CONFLICT (id_periode_update, id_siswa) DO NOTHING;
        END LOOP;
    END IF;
    RETURN NEW;
END;
$$;

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
        WHEN 'mutasi_siswa' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_mutasi';
        WHEN 'pendaftaran_ppdb' THEN
            v_id_data := COALESCE(v_new, v_old)->>'id_pendaftaran';
        ELSE
            v_id_data := NULL;
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

DROP TRIGGER IF EXISTS trg_konfirmasi_auto ON public.periode_update;
CREATE TRIGGER trg_konfirmasi_auto
AFTER INSERT OR UPDATE OF status ON public.periode_update
FOR EACH ROW
WHEN (NEW.status = 'aktif')
EXECUTE FUNCTION public.fn_buat_konfirmasi_data();

DROP TRIGGER IF EXISTS trg_blokir_update_log_audit ON public.log_audit;
CREATE TRIGGER trg_blokir_update_log_audit
BEFORE UPDATE OR DELETE ON public.log_audit
FOR EACH ROW EXECUTE FUNCTION public.fn_blokir_ubah_log();

DROP TRIGGER IF EXISTS trg_blokir_update_log_login ON public.log_login;
CREATE TRIGGER trg_blokir_update_log_login
BEFORE UPDATE OR DELETE ON public.log_login
FOR EACH ROW EXECUTE FUNCTION public.fn_blokir_ubah_log();

DROP TRIGGER IF EXISTS trg_blokir_update_konfirmasi ON public.konfirmasi_data;
CREATE TRIGGER trg_blokir_update_konfirmasi
BEFORE INSERT OR UPDATE OR DELETE ON public.konfirmasi_data
FOR EACH ROW
EXECUTE FUNCTION public.fn_konfirmasi_terbuka();

DROP TRIGGER IF EXISTS trg_audit_nilai_siswa ON public.nilai_siswa;
CREATE TRIGGER trg_audit_nilai_siswa
AFTER INSERT OR UPDATE OR DELETE ON public.nilai_siswa
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_rapor ON public.rapor;
CREATE TRIGGER trg_audit_rapor
AFTER INSERT OR UPDATE OR DELETE ON public.rapor
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_mutasi_siswa ON public.mutasi_siswa;
CREATE TRIGGER trg_audit_mutasi_siswa
AFTER INSERT OR UPDATE OR DELETE ON public.mutasi_siswa
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_pendaftaran_ppdb ON public.pendaftaran_ppdb;
CREATE TRIGGER trg_audit_pendaftaran_ppdb
AFTER INSERT OR UPDATE OR DELETE ON public.pendaftaran_ppdb
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_konfirmasi_data ON public.konfirmasi_data;
CREATE TRIGGER trg_audit_konfirmasi_data
AFTER INSERT OR UPDATE OR DELETE ON public.konfirmasi_data
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

DROP TRIGGER IF EXISTS trg_audit_periode_update ON public.periode_update;
CREATE TRIGGER trg_audit_periode_update
AFTER INSERT OR UPDATE OR DELETE ON public.periode_update
FOR EACH ROW EXECUTE FUNCTION public.fn_audit_trail();

RESET client_min_messages;
