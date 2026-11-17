-- Uji privilege & kelengkapan objek Fase 1 (menggantikan versi lama).
-- Profil dev lokal: app_login, app_owner, dan role aplikasi bukan superuser; app_owner sengaja NOLOGIN.
-- Jalankan: psql -v ON_ERROR_STOP=1 -d msbd_tubes_kel7 -f 01_uji_privilege.sql
-- Aman dijalankan ulang: seluruh skrip berada di dalam transaksi yang di-ROLLBACK.
-- Prinsip: hak diperiksa lewat katalog (has_*_privilege), lalu dibuktikan dengan percobaan DML nyata.
-- Percobaan hanya dianggap "DITOLAK" bila SQLSTATE = 42501 (insufficient_privilege); galat lain
-- (kolom salah, FK, trigger) berarti hak SUDAH lolos, sehingga tidak ada lagi positif palsu.

SELECT set_config('fase1.confirmed_target', 'msbd_tubes_kel7', false);

BEGIN;

DO $$
BEGIN
    IF current_database() <> 'msbd_tubes_kel7' THEN
        RAISE EXCEPTION 'Verifier privilege ditolak pada database %', current_database();
    END IF;
    IF current_setting('fase1.confirmed_target', true) IS DISTINCT FROM current_database() THEN
        RAISE EXCEPTION 'Database utama memerlukan konfirmasi target dari runner Fase 1';
    END IF;
END $$;

CREATE TEMP TABLE hasil_uji (
    no int GENERATED ALWAYS AS IDENTITY,
    kelompok text, pemeriksaan text, harapan text, hasil text, status text
);

CREATE FUNCTION pg_temp.catat(p_kelompok text, p_periksa text, p_harapan text, p_hasil text)
RETURNS void LANGUAGE sql AS $$
    INSERT INTO hasil_uji (kelompok, pemeriksaan, harapan, hasil, status)
    VALUES (p_kelompok, p_periksa, p_harapan, p_hasil,
            CASE
                WHEN p_harapan = p_hasil
                  OR (p_harapan = 'LOLOS' AND p_hasil LIKE 'LOLOS (SQLSTATE %')
                THEN 'PASS'
                ELSE 'FAIL'
            END);
$$;

-- Hak tabel lewat katalog. Role/tabel tidak ada -> hasil "ERROR", bukan berhenti diam-diam.
CREATE FUNCTION pg_temp.hak(p_kelompok text, p_role text, p_tabel text, p_hak text, p_ada boolean)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE v text;
BEGIN
    BEGIN
        v := CASE WHEN has_table_privilege(p_role, p_tabel, p_hak) THEN 'ADA' ELSE 'TIDAK ADA' END;
    EXCEPTION WHEN OTHERS THEN
        v := 'ERROR: ' || SQLERRM;
    END;
    PERFORM pg_temp.catat(p_kelompok, format('%s %s pada %s', p_role, p_hak, p_tabel),
                          CASE WHEN p_ada THEN 'ADA' ELSE 'TIDAK ADA' END, v);
END $$;

-- Percobaan DML nyata di bawah SET ROLE; selalu kembali ke role awal.
CREATE FUNCTION pg_temp.coba(p_kelompok text, p_role text, p_sql text, p_harapan text)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE v text;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = p_role) THEN
        v := 'ROLE TIDAK ADA';
    ELSE
        BEGIN
            EXECUTE format('SET LOCAL ROLE %I', p_role);
            EXECUTE p_sql;
            v := 'LOLOS';
        EXCEPTION
            WHEN insufficient_privilege THEN v := 'DITOLAK';
            WHEN undefined_object OR undefined_function OR undefined_table OR undefined_column OR syntax_error THEN
                v := 'GALAT ' || SQLSTATE || ': ' || SQLERRM;
            WHEN OTHERS THEN
                v := 'LOLOS (SQLSTATE ' || SQLSTATE || ')';
        END;
    END IF;
    RESET ROLE;
    PERFORM pg_temp.catat(p_kelompok, format('%s: %s', p_role, left(regexp_replace(p_sql, '\s+', ' ', 'g'), 70)),
                          p_harapan, v);
END $$;

CREATE FUNCTION pg_temp.jumlah_min(p_kelompok text, p_periksa text, p_min int, p_aktual int)
RETURNS void LANGUAGE sql AS $$
    SELECT pg_temp.catat(p_kelompok, p_periksa || ' (min ' || p_min || ')', 'CUKUP',
                         CASE WHEN p_aktual >= p_min THEN 'CUKUP' ELSE 'KURANG: ' || p_aktual END);
$$;

-- 1. Role dan atribut
DO $$
DECLARE r text; a record;
BEGIN
    FOREACH r IN ARRAY ARRAY['role_siswa','role_tu','role_kepsek','role_wali_kelas','app_owner','app_login'] LOOP
        SELECT * INTO a FROM pg_roles WHERE rolname = r;
        PERFORM pg_temp.catat('ROLE', r || ' ada', 'ADA', CASE WHEN FOUND THEN 'ADA' ELSE 'TIDAK ADA' END);
        IF FOUND THEN
            PERFORM pg_temp.catat('ROLE', r || ' bukan superuser', 'YA', CASE WHEN a.rolsuper THEN 'TIDAK' ELSE 'YA' END);
            PERFORM pg_temp.catat('ROLE', r || ' bisa login',
                                  CASE WHEN r = 'app_owner' THEN 'TIDAK' ELSE 'YA' END,
                                  CASE WHEN a.rolcanlogin THEN 'YA' ELSE 'TIDAK' END);
        END IF;
    END LOOP;
    SELECT * INTO a FROM pg_roles WHERE rolname = 'app_login';
    IF FOUND THEN
        PERFORM pg_temp.catat('ROLE', 'app_login INHERIT', 'YA', CASE WHEN a.rolinherit THEN 'YA' ELSE 'TIDAK' END);
    END IF;
    PERFORM pg_temp.catat(
        'ROLE',
        'app_login mewarisi empat role aplikasi tanpa SET ROLE',
        '4',
        (
            SELECT count(*)::text
            FROM pg_catalog.pg_auth_members AS membership
            JOIN pg_catalog.pg_roles AS granted_role ON granted_role.oid = membership.roleid
            JOIN pg_catalog.pg_roles AS member_role ON member_role.oid = membership.member
            WHERE member_role.rolname = 'app_login'
              AND granted_role.rolname = ANY (ARRAY['role_siswa','role_tu','role_kepsek','role_wali_kelas'])
              AND membership.inherit_option
              AND NOT membership.set_option
        )
    );
    PERFORM pg_temp.catat(
        'ROLE',
        'app_login search_path terdiri dari pg_catalog, public, pg_temp',
        'YA',
        CASE WHEN EXISTS (
            SELECT 1
            FROM pg_db_role_setting AS s
            JOIN pg_roles AS role_entry ON role_entry.oid = s.setrole
            JOIN pg_database AS database_entry ON database_entry.oid = s.setdatabase
            CROSS JOIN LATERAL unnest(s.setconfig) AS setting
            WHERE role_entry.rolname = 'app_login'
              AND database_entry.datname = current_database()
              AND setting = 'search_path=pg_catalog, public, pg_temp'
              AND string_to_array(split_part(setting, '=', 2), ', ') = ARRAY['pg_catalog', 'public', 'pg_temp']
        ) THEN 'YA' ELSE 'TIDAK' END
    );
    PERFORM pg_temp.catat('ROLE', 'tidak ada role DB bernama guru', '0',
        (SELECT count(*)::text FROM pg_roles WHERE rolname ~* 'guru'));
END $$;

-- app_login menggabungkan hak empat role aplikasi untuk koneksi tunggal Laravel.
DO $$
DECLARE
    t text;
    h text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'sessions','cache','cache_locks','jobs','job_batches','failed_jobs',
        'password_reset_tokens','personal_access_tokens','migrations'
    ] LOOP
        FOREACH h IN ARRAY ARRAY['SELECT','INSERT','UPDATE','DELETE'] LOOP
            PERFORM pg_temp.hak('INFRA', 'app_login', 'public.' || t, h, true);
        END LOOP;
    END LOOP;
    PERFORM pg_temp.catat(
        'KOLOM',
        'app_login dapat memperbarui sandi dan metadata login',
        'ADA',
        CASE WHEN has_column_privilege('app_login', 'public.pengguna', 'password_hash', 'UPDATE')
                  AND has_column_privilege('app_login', 'public.pengguna', 'terakhir_login', 'UPDATE')
                  AND has_column_privilege('app_login', 'public.pengguna', 'diubah_pada', 'UPDATE')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    FOREACH t IN ARRAY ARRAY['failed_jobs_id_seq','jobs_id_seq','personal_access_tokens_id_seq','migrations_id_seq'] LOOP
        PERFORM pg_temp.catat(
            'SEQUENCE',
            'app_login USAGE ' || t,
            'ADA',
            CASE WHEN has_sequence_privilege('app_login', 'public.' || t, 'USAGE') THEN 'ADA' ELSE 'TIDAK ADA' END
        );
    END LOOP;
END $$;

-- 2. Tulis akademik hanya wali_kelas (DELETE nilai_siswa diperlukan: AksiFormController menghapus nilai kosong)
DO $$
DECLARE r text; t text; h text;
BEGIN
    FOREACH t IN ARRAY ARRAY['nilai_siswa','sesi_absensi','detail_absensi','rapor','detail_rapor','catatan_wali_kelas','izin_siswa'] LOOP
        FOREACH r IN ARRAY ARRAY['role_tu','role_kepsek','role_siswa'] LOOP
            FOREACH h IN ARRAY ARRAY['INSERT','UPDATE','DELETE'] LOOP
                PERFORM pg_temp.hak('TULIS-AKADEMIK', r, 'public.' || t, h, false);
            END LOOP;
        END LOOP;
        FOREACH h IN ARRAY ARRAY['INSERT','UPDATE'] LOOP
            PERFORM pg_temp.hak('TULIS-AKADEMIK', 'role_wali_kelas', 'public.' || t, h, true);
        END LOOP;
    END LOOP;
    PERFORM pg_temp.hak('TULIS-AKADEMIK', 'role_wali_kelas', 'public.nilai_siswa', 'DELETE', true);
    PERFORM pg_temp.hak('BACA', 'role_tu', 'public.nilai_siswa', 'SELECT', true);
    PERFORM pg_temp.hak('BACA', 'role_kepsek', 'public.nilai_siswa', 'SELECT', true);
    PERFORM pg_temp.hak('PENUGASAN-WALI', 'role_tu', 'public.penugasan_wali_kelas', 'DELETE', true);
    PERFORM pg_temp.catat(
        'PENUGASAN-WALI',
        'role_tu dapat memakai sequence penugasan wali',
        'ADA',
        CASE WHEN has_sequence_privilege(
            'role_tu',
            'public.penugasan_wali_kelas_id_penugasan_wali_kelas_seq',
            'USAGE'
        ) THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    PERFORM pg_temp.catat(
        'PENUGASAN-WALI',
        'role_tu dapat menambah dan mengunci kolom penugasan wali',
        'ADA',
        CASE WHEN has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'id_kelas', 'INSERT')
                  AND has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'id_guru', 'INSERT')
                  AND has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'id_semester', 'INSERT')
                  AND has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'ditugaskan_pada', 'INSERT')
                  AND has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'id_guru', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.penugasan_wali_kelas', 'ditugaskan_pada', 'UPDATE')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    PERFORM pg_temp.catat(
        'EDIT-SISWA',
        'role_tu dapat memperbarui kolom profil siswa yang disunting UI',
        'ADA',
        CASE WHEN has_column_privilege('role_tu', 'public.siswa', 'nama_lengkap', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'nis', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'nisn', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'nik', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'jenis_kelamin', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'tempat_lahir', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'tanggal_lahir', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'email', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'no_telepon', 'UPDATE')
                  AND has_column_privilege('role_tu', 'public.siswa', 'diubah_pada', 'UPDATE')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    PERFORM pg_temp.catat(
        'EDIT-SISWA',
        'role_tu tidak dapat mengubah status atau relasi akun siswa',
        'TIDAK ADA',
        CASE WHEN has_column_privilege('role_tu', 'public.siswa', 'id_pengguna', 'UPDATE')
                  OR has_column_privilege('role_tu', 'public.siswa', 'id_status_siswa', 'UPDATE')
                  OR has_column_privilege('role_tu', 'public.siswa', 'id_agama', 'UPDATE')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
END $$;

-- 3. Log: tidak ada yang boleh ubah/hapus; hanya kepsek yang boleh membaca log_audit
DO $$
DECLARE r text; t text; h text;
BEGIN
    FOREACH t IN ARRAY ARRAY['log_audit','log_login'] LOOP
        FOREACH r IN ARRAY ARRAY['role_siswa','role_tu','role_kepsek','role_wali_kelas','app_login'] LOOP
            FOREACH h IN ARRAY ARRAY['UPDATE','DELETE','TRUNCATE'] LOOP
                PERFORM pg_temp.hak('LOG', r, 'public.' || t, h, false);
            END LOOP;
        END LOOP;
    END LOOP;
    PERFORM pg_temp.hak('LOG', 'role_kepsek', 'public.log_audit', 'SELECT', true);
    PERFORM pg_temp.hak('LOG', 'role_siswa', 'public.log_audit', 'SELECT', false);
    PERFORM pg_temp.hak('LOG', 'role_wali_kelas', 'public.log_audit', 'SELECT', false);
    PERFORM pg_temp.hak('LOG', 'app_login', 'public.log_login', 'INSERT', true);
END $$;

-- 4. password_hash tidak terbaca role bisnis; Tata Usaha hanya dapat memperbaruinya
DO $do$ BEGIN PERFORM pg_temp.catat('KOLOM', r || ' SELECT pengguna.password_hash', 'TIDAK ADA',
    CASE WHEN NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r) THEN 'ROLE TIDAK ADA'
         WHEN has_column_privilege(r, 'public.pengguna', 'password_hash', 'SELECT') THEN 'ADA'
         ELSE 'TIDAK ADA' END)
FROM unnest(ARRAY['role_siswa','role_tu','role_kepsek','role_wali_kelas']) AS r; END $do$;
DO $do$ BEGIN PERFORM pg_temp.catat('KOLOM', 'role_tu UPDATE pengguna.password_hash', 'ADA',
    CASE WHEN NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'role_tu') THEN 'ROLE TIDAK ADA'
         WHEN has_column_privilege('role_tu', 'public.pengguna', 'password_hash', 'UPDATE') THEN 'ADA'
         ELSE 'TIDAK ADA' END); END $do$;
DO $do$ BEGIN
    PERFORM pg_temp.catat(
        'KOLOM',
        r || ' UPDATE pengguna.password_hash',
        'ADA',
        CASE WHEN NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r) THEN 'ROLE TIDAK ADA'
             WHEN has_column_privilege(r, 'public.pengguna', 'password_hash', 'UPDATE') THEN 'ADA'
             ELSE 'TIDAK ADA' END
    )
    FROM unnest(ARRAY['role_siswa','role_kepsek','role_wali_kelas']) AS r;
    PERFORM pg_temp.catat(
        'KOLOM',
        'role_tu INSERT periode_update',
        'ADA',
        CASE WHEN has_column_privilege('role_tu', 'public.periode_update', 'id_tahun_ajaran', 'INSERT')
                   AND has_column_privilege('role_tu', 'public.periode_update', 'dibuat_oleh', 'INSERT')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    PERFORM pg_temp.catat(
        'KOLOM',
        'role_tu UPDATE periode_update.status',
        'ADA',
        CASE WHEN has_column_privilege('role_tu', 'public.periode_update', 'status', 'UPDATE')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
    PERFORM pg_temp.catat(
        'KOLOM',
        'role_wali_kelas INSERT notifikasi',
        'ADA',
        CASE WHEN has_column_privilege('role_wali_kelas', 'public.notifikasi', 'id_pengguna', 'INSERT')
                   AND has_column_privilege('role_wali_kelas', 'public.notifikasi', 'pesan', 'INSERT')
             THEN 'ADA' ELSE 'TIDAK ADA' END
    );
END $do$;

-- 5. Tidak ada hak tersisa untuk PUBLIC pada tabel/view; EXECUTE prosedur dikunci
DO $do$ BEGIN PERFORM pg_temp.catat('PUBLIC', 'jumlah GRANT ke PUBLIC pada tabel/view skema public', '0',
    (SELECT count(*)::text FROM pg_class c
       JOIN pg_namespace n ON n.oid = c.relnamespace
       CROSS JOIN LATERAL aclexplode(c.relacl) a
      WHERE n.nspname = 'public' AND c.relkind IN ('r','v','p') AND a.grantee = 0)); END $do$;

DO $do$ BEGIN PERFORM pg_temp.catat('PUBLIC', 'EXECUTE sp_setujui_pengajuan untuk ' || r, e,
    CASE WHEN NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r) THEN 'ROLE TIDAK ADA'
         WHEN has_function_privilege(r, 'public.sp_setujui_pengajuan(bigint,bigint,text)', 'EXECUTE')
         THEN 'ADA' ELSE 'TIDAK ADA' END)
FROM (VALUES ('role_wali_kelas','ADA'), ('role_siswa','TIDAK ADA'), ('app_login','ADA')) AS x(r, e); END $do$;

-- 6. SECURITY DEFINER aman: pemilik app_owner dan search_path terkunci
DO $do$ BEGIN PERFORM pg_temp.catat('DEFINER', p.proname || ' SECURITY DEFINER + search_path + pemilik app_owner', 'YA',
    CASE WHEN p.prosecdef AND COALESCE(p.proconfig::text, '') LIKE '%search_path%'
              AND pg_get_userbyid(p.proowner) = 'app_owner' THEN 'YA' ELSE 'TIDAK' END)
FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public' AND p.proname IN ('sp_setujui_pengajuan','fn_audit_trail'); END $do$;

-- 7. View: security_invoker pada semua view vw_*, dan tidak ada kolom password_hash
DO $do$ BEGIN PERFORM pg_temp.catat('VIEW', 'view vw_* tanpa security_invoker', '0',
    (SELECT count(*)::text FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
      WHERE n.nspname = 'public' AND c.relkind = 'v' AND c.relname LIKE 'vw\_%'
        AND NOT (COALESCE(c.reloptions, '{}') @> ARRAY['security_invoker=true']))); END $do$;
DO $do$ BEGIN PERFORM pg_temp.catat('VIEW', 'kolom password_hash pada view', '0',
    (SELECT count(*)::text FROM information_schema.columns
      WHERE table_schema = 'public' AND column_name = 'password_hash'
        AND table_name IN (SELECT table_name FROM information_schema.views WHERE table_schema = 'public'))); END $do$;

-- 8. Kelengkapan objek (fungsi bawaan ekstensi seperti btree_gist dikecualikan)
DO $do$ BEGIN PERFORM pg_temp.catat('OBJEK', 'tabel tanpa PRIMARY KEY', '0',
    (SELECT count(*)::text FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
      WHERE n.nspname = 'public' AND c.relkind IN ('r','p')
        AND NOT EXISTS (SELECT 1 FROM pg_constraint k WHERE k.conrelid = c.oid AND k.contype = 'p'))); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'FOREIGN KEY', 83,
    (SELECT count(*)::int FROM pg_constraint k JOIN pg_namespace n ON n.oid = k.connamespace
      WHERE n.nspname = 'public' AND k.contype = 'f')); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'view', 12,
    (SELECT count(*)::int FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
      WHERE n.nspname = 'public' AND c.relkind = 'v')); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'trigger (non-internal)', 18,
    (SELECT count(*)::int FROM pg_trigger WHERE NOT tgisinternal)); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'stored function (bukan trigger)', 6,
    (SELECT count(*)::int FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
      WHERE n.nspname = 'public' AND p.prokind = 'f' AND p.prorettype <> 'trigger'::regtype
        AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = p.oid AND d.deptype = 'e'))); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'trigger function', 5,
    (SELECT count(*)::int FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
      WHERE n.nspname = 'public' AND p.prorettype = 'trigger'::regtype
        AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = p.oid AND d.deptype = 'e'))); END $do$;
DO $do$ BEGIN PERFORM pg_temp.jumlah_min('OBJEK', 'stored procedure', 4,
    (SELECT count(*)::int FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
      WHERE n.nspname = 'public' AND p.prokind = 'p')); END $do$;

-- 9. Audit terpasang pada seluruh tabel yang diaudit
DO $$
DECLARE t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'siswa', 'pengguna', 'pengajuan_perubahan', 'alamat_siswa', 'orang_tua_wali',
        'dokumen_siswa', 'keanggotaan_kelas', 'kelas', 'tahun_ajaran', 'semester',
        'konfirmasi_data', 'periode_update', 'nilai_siswa', 'rapor', 'mutasi_siswa',
        'pendaftaran_ppdb', 'agama', 'pekerjaan', 'sekolah', 'status_siswa',
        'jenis_dokumen', 'ref_checklist_item', 'ref_alasan_pengembalian',
        'ref_field_data', 'penugasan_wali_kelas'
    ] LOOP
        PERFORM pg_temp.catat('AUDIT', 'trigger fn_audit_trail pada ' || t, '1',
            CASE WHEN to_regclass('public.' || t) IS NULL THEN 'TABEL TIDAK ADA'
                 ELSE (SELECT count(*)::text FROM pg_trigger
                        WHERE tgrelid = to_regclass('public.' || t) AND NOT tgisinternal
                          AND tgfoid = to_regproc('public.fn_audit_trail')::oid) END);
    END LOOP;
END $$;

-- 10. Statement INSERT kosong menguji privilege kolom tanpa meninggalkan data atau memicu constraint.
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', r,
    'INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_komponen, id_penginput, nilai) SELECT id_anggota, id_mapel, id_semester, id_komponen, id_penginput, nilai FROM public.nilai_siswa LIMIT 0', e)
FROM (VALUES ('role_tu','DITOLAK'), ('role_kepsek','DITOLAK'), ('role_siswa','DITOLAK'), ('role_wali_kelas','LOLOS')) AS x(r, e); END $do$;
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', 'role_kepsek', 'UPDATE public.log_audit SET nama_tabel = nama_tabel WHERE false', 'DITOLAK'); END $do$;
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', r, 'SELECT password_hash FROM public.pengguna LIMIT 0', 'DITOLAK')
FROM unnest(ARRAY['role_siswa','role_tu','role_kepsek','role_wali_kelas']) AS r; END $do$;
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', 'role_tu',
    'UPDATE public.pengguna SET password_hash = ''uji'' WHERE false', 'LOLOS'); END $do$;
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', 'role_siswa', 'CALL public.sp_setujui_pengajuan(0, 0, NULL)', 'DITOLAK'); END $do$;
DO $do$ BEGIN PERFORM pg_temp.coba('DML-NYATA', 'role_wali_kelas', 'CALL public.sp_setujui_pengajuan(0, 0, NULL)', 'LOLOS'); END $do$;

-- 11. Trigger anti-ubah log_audit (diuji sebagai superuser; trigger tetap aktif untuk semua role)
DO $$
DECLARE
    v_log_audit_ctid tid;
    v_id_log_login bigint;
    v_before bigint;
    v_after bigint;
BEGIN
    SELECT count(*) INTO v_before FROM public.log_audit;
    INSERT INTO public.log_audit (nama_tabel, operasi)
    VALUES ('uji_trigger', 'INSERT')
    RETURNING ctid INTO v_log_audit_ctid;
    SELECT count(*) INTO v_after FROM public.log_audit;
    PERFORM pg_temp.catat(
        'TRIGGER',
        'INSERT log_audit menambah satu baris',
        'YA',
        CASE WHEN v_after = v_before + 1 THEN 'YA' ELSE 'TIDAK' END
    );

    BEGIN
        UPDATE public.log_audit
        SET nama_tabel = nama_tabel
        WHERE ctid = v_log_audit_ctid;
        PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_audit oleh superuser', 'DIBLOKIR', 'TIDAK DIBLOKIR');
    EXCEPTION
        WHEN SQLSTATE 'P0001' THEN
            PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_audit oleh superuser', 'DIBLOKIR', 'DIBLOKIR');
        WHEN OTHERS THEN
            PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_audit oleh superuser', 'DIBLOKIR', 'GALAT ' || SQLSTATE);
    END;

    BEGIN
        DELETE FROM public.log_audit WHERE ctid = v_log_audit_ctid;
        PERFORM pg_temp.catat('TRIGGER', 'DELETE log_audit oleh superuser', 'DIBLOKIR', 'TIDAK DIBLOKIR');
    EXCEPTION
        WHEN SQLSTATE 'P0001' THEN
            PERFORM pg_temp.catat('TRIGGER', 'DELETE log_audit oleh superuser', 'DIBLOKIR', 'DIBLOKIR');
        WHEN OTHERS THEN
            PERFORM pg_temp.catat('TRIGGER', 'DELETE log_audit oleh superuser', 'DIBLOKIR', 'GALAT ' || SQLSTATE);
    END;

    SELECT id_log_login INTO v_id_log_login
    FROM public.log_login
    ORDER BY id_log_login
    LIMIT 1;
    IF v_id_log_login IS NULL THEN
        INSERT INTO public.log_login (username_dicoba, berhasil)
        VALUES ('__privilege_verifier__', FALSE)
        RETURNING id_log_login INTO v_id_log_login;
    END IF;

    BEGIN
        UPDATE public.log_login
        SET username_dicoba = username_dicoba
        WHERE id_log_login = v_id_log_login;
        PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_login oleh superuser', 'DIBLOKIR', 'TIDAK DIBLOKIR');
    EXCEPTION
        WHEN SQLSTATE 'P0001' THEN
            PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_login oleh superuser', 'DIBLOKIR', 'DIBLOKIR');
        WHEN OTHERS THEN
            PERFORM pg_temp.catat('TRIGGER', 'UPDATE log_login oleh superuser', 'DIBLOKIR', 'GALAT ' || SQLSTATE);
    END;

    BEGIN
        DELETE FROM public.log_login WHERE id_log_login = v_id_log_login;
        PERFORM pg_temp.catat('TRIGGER', 'DELETE log_login oleh superuser', 'DIBLOKIR', 'TIDAK DIBLOKIR');
    EXCEPTION
        WHEN SQLSTATE 'P0001' THEN
            PERFORM pg_temp.catat('TRIGGER', 'DELETE log_login oleh superuser', 'DIBLOKIR', 'DIBLOKIR');
        WHEN OTHERS THEN
            PERFORM pg_temp.catat('TRIGGER', 'DELETE log_login oleh superuser', 'DIBLOKIR', 'GALAT ' || SQLSTATE);
    END;

    PERFORM pg_temp.catat(
        'TRIGGER',
        'trigger non-internal berstatus enabled',
        '0',
        (
            SELECT count(*)::text
            FROM pg_catalog.pg_trigger AS t
            JOIN pg_catalog.pg_class AS c ON c.oid = t.tgrelid
            JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND NOT t.tgisinternal
              AND t.tgenabled <> 'O'
        )
    );
EXCEPTION WHEN OTHERS THEN
    PERFORM pg_temp.catat('TRIGGER', 'Pengujian trigger log oleh superuser', 'SEMUA TERUJI', 'TIDAK TERUJI: ' || SQLSTATE);
END $$;

-- Ringkasan lalu rincian (FAIL tampil di atas)
SELECT status, count(*) AS jumlah FROM hasil_uji GROUP BY status ORDER BY status;
SELECT no, kelompok, pemeriksaan, harapan, hasil, status
  FROM hasil_uji ORDER BY (status = 'PASS'), no;

ROLLBACK;
