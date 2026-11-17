-- Fase 1: pulihkan constraint dari schema_backup.sql pada database yang diizinkan.
-- PK memakai unique index persis bila tersedia; FK bersih divalidasi, FK yatim dibiarkan NOT VALID.
DO $fase1$
DECLARE
    v record;
    v_table_oid oid;
    v_parent_oid oid;
    v_keys smallint[];
    v_parent_keys smallint[];
    v_existing_keys smallint[];
    v_existing_parent_keys smallint[];
    v_existing_parent_oid oid;
    v_existing_action "char";
    v_expected_action "char";
    v_existing_update_action "char";
    v_existing_type "char";
    v_is_valid boolean;
    v_has_issue boolean;
    v_index_name name;
    v_columns text;
    v_parent_columns text;
    v_null_predicate text;
    v_match_predicate text;
BEGIN
    IF current_database() NOT IN ('msbd_tubes_kel7_staging', 'msbd_latihan_utama', 'msbd_tubes_kel7')
       AND NOT EXISTS (
           SELECT 1 FROM pg_database
           WHERE datname = current_database()
             AND shobj_description(oid, 'pg_database') = 'FASE1_TRAINING_CLONE'
       ) THEN
        RAISE EXCEPTION 'Fase 1 constraint ditolak pada database %', current_database();
    END IF;
    IF current_database() <> 'msbd_tubes_kel7'
       AND current_database() <> 'msbd_tubes_kel7_staging'
       AND NOT EXISTS (
           SELECT 1 FROM pg_database
           WHERE datname = current_database()
             AND shobj_description(oid, 'pg_database') = 'FASE1_TRAINING_CLONE'
       ) THEN
        RAISE EXCEPTION 'Database clone tidak memiliki marker FASE1_TRAINING_CLONE';
    END IF;
    IF current_database() = 'msbd_tubes_kel7'
       AND current_setting('fase1.confirmed_target', true) IS DISTINCT FROM current_database() THEN
        RAISE EXCEPTION 'Database utama memerlukan konfirmasi target dari runner Fase 1';
    END IF;

    FOR v IN SELECT * FROM (VALUES
        ('agama', 'agama_pkey', 'id_agama'),
        ('alamat_siswa', 'alamat_siswa_pkey', 'id_alamat'),
        ('cache_locks', 'cache_locks_pkey', 'key'),
        ('cache', 'cache_pkey', 'key'),
        ('catatan_wali_kelas', 'catatan_wali_kelas_pkey', 'id_catatan'),
        ('cetak_rapor', 'cetak_rapor_pkey', 'id_cetak'),
        ('detail_absensi', 'detail_absensi_pkey', 'id_detail_absensi'),
        ('detail_pengajuan', 'detail_pengajuan_pkey', 'id_detail_pengajuan'),
        ('detail_rapor', 'detail_rapor_pkey', 'id_detail_rapor'),
        ('detail_verifikasi', 'detail_verifikasi_pkey', 'id_verifikasi,id_item'),
        ('dokumen_siswa', 'dokumen_siswa_pkey', 'id_dokumen'),
        ('ekspor_laporan', 'ekspor_laporan_pkey', 'id_ekspor'),
        ('failed_jobs', 'failed_jobs_pkey', 'id'),
        ('guru', 'guru_pkey', 'id_guru'),
        ('hak_akses', 'hak_akses_pkey', 'id_hak_akses'),
        ('izin_siswa', 'izin_siswa_pkey', 'id_izin'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_pkey', 'id_jadwal'),
        ('jenis_dokumen', 'jenis_dokumen_pkey', 'id_jenis_dokumen'),
        ('jenjang', 'jenjang_pkey', 'id_jenjang'),
        ('job_batches', 'job_batches_pkey', 'id'),
        ('jobs', 'jobs_pkey', 'id'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_pkey', 'id_keanggotaan'),
        ('kecamatan', 'kecamatan_pkey', 'id_kecamatan'),
        ('kelas', 'kelas_pkey', 'id_kelas'),
        ('kelurahan', 'kelurahan_pkey', 'id_kelurahan'),
        ('komponen_penilaian', 'komponen_penilaian_pkey', 'id_komponen'),
        ('konfirmasi_data', 'konfirmasi_data_pkey', 'id_konfirmasi'),
        ('kota', 'kota_pkey', 'id_kota'),
        ('log_audit', 'log_audit_pkey', 'id_log'),
        ('log_login', 'log_login_pkey', 'id_log_login'),
        ('mata_pelajaran', 'mata_pelajaran_pkey', 'id_mata_pelajaran'),
        ('migrations', 'migrations_pkey', 'id'),
        ('mutasi_siswa', 'mutasi_siswa_pkey', 'id_mutasi'),
        ('nilai_siswa', 'nilai_siswa_pkey', 'id_nilai'),
        ('notifikasi', 'notifikasi_pkey', 'id_notifikasi'),
        ('orang_tua_wali', 'orang_tua_wali_pkey', 'id_orang_tua_wali'),
        ('password_reset_tokens', 'password_reset_tokens_pkey', 'email'),
        ('pekerjaan', 'pekerjaan_pkey', 'id_pekerjaan'),
        ('pendaftaran_ppdb', 'pendaftaran_ppdb_pkey', 'id_pendaftaran'),
        ('pengajuan_perubahan', 'pengajuan_perubahan_pkey', 'id_pengajuan'),
        ('pengaturan_sistem', 'pengaturan_sistem_pkey', 'id_pengaturan'),
        ('pengguna', 'pengguna_pkey', 'id_pengguna'),
        ('penugasan_wali_kelas', 'penugasan_wali_kelas_pkey', 'id_penugasan_wali_kelas'),
        ('periode_update', 'periode_update_pkey', 'id_periode_update'),
        ('provinsi', 'provinsi_pkey', 'id_provinsi'),
        ('rapor', 'rapor_pkey', 'id_rapor'),
        ('ref_alasan_pengembalian', 'ref_alasan_pengembalian_pkey', 'id_alasan'),
        ('ref_checklist_item', 'ref_checklist_item_pkey', 'id_item'),
        ('ref_field_data', 'ref_field_data_pkey', 'id_field'),
        ('role_hak_akses', 'role_hak_akses_pkey', 'id_role,id_hak_akses'),
        ('role', 'role_pkey', 'id_role'),
        ('sekolah', 'sekolah_pkey', 'id_sekolah'),
        ('semester', 'semester_pkey', 'id_semester'),
        ('sesi_absensi', 'sesi_absensi_pkey', 'id_sesi'),
        ('sessions', 'sessions_pkey', 'id'),
        ('siswa', 'siswa_pkey', 'id_siswa'),
        ('skala_predikat', 'skala_predikat_pkey', 'id_predikat'),
        ('staf', 'staf_pkey', 'id_staf'),
        ('status_kehadiran', 'status_kehadiran_pkey', 'id_status_hadir'),
        ('status_siswa', 'status_siswa_pkey', 'id_status_siswa'),
        ('tahun_ajaran', 'tahun_ajaran_pkey', 'id_tahun_ajaran'),
        ('tingkat', 'tingkat_pkey', 'id_tingkat'),
        ('users', 'users_pkey', 'id'),
        ('verifikasi_pendaftaran', 'verifikasi_pendaftaran_pkey', 'id_verifikasi')
    ) AS candidates(table_name, constraint_name, column_names)
    LOOP
        v_table_oid := to_regclass(format('public.%I', v.table_name));
        IF v_table_oid IS NULL THEN
            RAISE EXCEPTION 'Tabel kandidat PK tidak ditemukan: public.%', v.table_name;
        END IF;

        SELECT array_agg(a.attnum::smallint ORDER BY x.ordinality),
               string_agg(format('%I', btrim(x.column_name)), ', ' ORDER BY x.ordinality),
               string_agg(format('%I IS NULL', btrim(x.column_name)), ' OR ' ORDER BY x.ordinality)
        INTO v_keys, v_columns, v_null_predicate
        FROM unnest(string_to_array(v.column_names, ',')) WITH ORDINALITY AS x(column_name, ordinality)
        JOIN pg_attribute a ON a.attrelid = v_table_oid AND a.attname = btrim(x.column_name) AND NOT a.attisdropped;

        IF v_keys IS NULL
           OR cardinality(v_keys) <> cardinality(string_to_array(v.column_names, ',')) THEN
            RAISE EXCEPTION 'Kolom kandidat PK tidak ditemukan: public.%.%', v.table_name, v.column_names;
        END IF;

        SELECT conkey INTO v_existing_keys
        FROM pg_constraint
        WHERE conrelid = v_table_oid AND contype = 'p';
        IF FOUND THEN
            IF v_existing_keys IS DISTINCT FROM v_keys THEN
                RAISE EXCEPTION 'PK yang sudah ada pada public.% berbeda dengan kandidat %', v.table_name, v.constraint_name;
            END IF;
            CONTINUE;
        END IF;

        EXECUTE format('SELECT EXISTS (SELECT 1 FROM public.%I WHERE %s)', v.table_name, v_null_predicate)
        INTO v_has_issue;
        IF v_has_issue THEN
            RAISE EXCEPTION 'Kandidat PK public.%.% masih memiliki NULL', v.table_name, v.column_names;
        END IF;

        EXECUTE format('SELECT EXISTS (SELECT 1 FROM public.%I GROUP BY %s HAVING count(*) > 1)', v.table_name, v_columns)
        INTO v_has_issue;
        IF v_has_issue THEN
            RAISE EXCEPTION 'Kandidat PK public.%.% masih memiliki duplikasi', v.table_name, v.column_names;
        END IF;

        v_index_name := NULL;
        SELECT idx.relname INTO v_index_name
        FROM pg_index i
        JOIN pg_class idx ON idx.oid = i.indexrelid
        JOIN pg_am am ON am.oid = idx.relam
        WHERE i.indrelid = v_table_oid
          AND i.indisunique AND i.indisvalid AND i.indisready
          AND i.indpred IS NULL AND i.indexprs IS NULL
          AND i.indnkeyatts = cardinality(v_keys) AND i.indnatts = cardinality(v_keys)
          AND i.indkey::smallint[] = v_keys
          AND am.amname = 'btree'
          AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid)
        ORDER BY idx.relname
        LIMIT 1;

        IF v_index_name IS NOT NULL THEN
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I PRIMARY KEY USING INDEX public.%I',
                v.table_name, v.constraint_name, v_index_name);
        ELSE
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I PRIMARY KEY (%s)',
                v.table_name, v.constraint_name, v_columns);
        END IF;
    END LOOP;

    FOR v IN SELECT * FROM (VALUES
        ('alamat_siswa', 'alamat_siswa_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'CASCADE'),
        ('catatan_wali_kelas', 'catatan_wali_kelas_id_guru_fkey', 'id_guru', 'guru', 'id_guru', 'RESTRICT'),
        ('catatan_wali_kelas', 'catatan_wali_kelas_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('catatan_wali_kelas', 'catatan_wali_kelas_id_tahun_ajaran_fkey', 'id_tahun_ajaran', 'tahun_ajaran', 'id_tahun_ajaran', 'RESTRICT'),
        ('cetak_rapor', 'cetak_rapor_id_pencetak_fkey', 'id_pencetak', 'pengguna', 'id_pengguna', 'RESTRICT'),
        ('cetak_rapor', 'cetak_rapor_id_rapor_fkey', 'id_rapor', 'rapor', 'id_rapor', 'RESTRICT'),
        ('detail_absensi', 'detail_absensi_id_sesi_fkey', 'id_sesi', 'sesi_absensi', 'id_sesi', 'CASCADE'),
        ('detail_absensi', 'detail_absensi_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('detail_absensi', 'detail_absensi_id_status_hadir_fkey', 'id_status_hadir', 'status_kehadiran', 'id_status_hadir', 'RESTRICT'),
        ('detail_pengajuan', 'detail_pengajuan_id_pengajuan_fkey', 'id_pengajuan', 'pengajuan_perubahan', 'id_pengajuan', 'CASCADE'),
        ('detail_rapor', 'detail_rapor_id_mapel_fkey', 'id_mapel', 'mata_pelajaran', 'id_mata_pelajaran', 'RESTRICT'),
        ('detail_rapor', 'detail_rapor_id_predikat_fkey', 'id_predikat', 'skala_predikat', 'id_predikat', 'SET NULL'),
        ('detail_rapor', 'detail_rapor_id_rapor_fkey', 'id_rapor', 'rapor', 'id_rapor', 'CASCADE'),
        ('detail_verifikasi', 'detail_verifikasi_id_item_fkey', 'id_item', 'ref_checklist_item', 'id_item', 'RESTRICT'),
        ('detail_verifikasi', 'detail_verifikasi_id_verifikasi_fkey', 'id_verifikasi', 'verifikasi_pendaftaran', 'id_verifikasi', 'CASCADE'),
        ('dokumen_siswa', 'dokumen_siswa_id_pendaftaran_fkey', 'id_pendaftaran', 'pendaftaran_ppdb', 'id_pendaftaran', 'CASCADE'),
        ('dokumen_siswa', 'dokumen_siswa_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'CASCADE'),
        ('ekspor_laporan', 'ekspor_laporan_id_kelas_fkey', 'id_kelas', 'kelas', 'id_kelas', 'SET NULL'),
        ('ekspor_laporan', 'ekspor_laporan_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'RESTRICT'),
        ('ekspor_laporan', 'ekspor_laporan_id_tahun_ajaran_fkey', 'id_tahun_ajaran', 'tahun_ajaran', 'id_tahun_ajaran', 'SET NULL'),
        ('kelas', 'fk_kelas_tahun_ajaran', 'id_tahun_ajaran', 'tahun_ajaran', 'id_tahun_ajaran', 'SET NULL'),
        ('tingkat', 'fk_tingkat_jenjang', 'id_jenjang', 'jenjang', 'id_jenjang', 'SET NULL'),
        ('guru', 'guru_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('izin_siswa', 'izin_siswa_id_dokumen_bukti_fkey', 'id_dokumen_bukti', 'dokumen_siswa', 'id_dokumen', 'SET NULL'),
        ('izin_siswa', 'izin_siswa_id_penyetuju_fkey', 'id_penyetuju', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('izin_siswa', 'izin_siswa_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('izin_siswa', 'izin_siswa_id_status_hadir_fkey', 'id_status_hadir', 'status_kehadiran', 'id_status_hadir', 'RESTRICT'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_id_guru_fkey', 'id_guru', 'guru', 'id_guru', 'RESTRICT'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_id_kelas_fkey', 'id_kelas', 'kelas', 'id_kelas', 'RESTRICT'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_id_mata_pelajaran_fkey', 'id_mata_pelajaran', 'mata_pelajaran', 'id_mata_pelajaran', 'RESTRICT'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_id_semester_fkey', 'id_semester', 'semester', 'id_semester', 'RESTRICT'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_id_kelas_fkey', 'id_kelas', 'kelas', 'id_kelas', 'RESTRICT'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_id_semester_fkey', 'id_semester', 'semester', 'id_semester', 'RESTRICT'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('kecamatan', 'kecamatan_id_kota_fkey', 'id_kota', 'kota', 'id_kota', 'RESTRICT'),
        ('kelas', 'kelas_id_tingkat_fkey', 'id_tingkat', 'tingkat', 'id_tingkat', 'RESTRICT'),
        ('kelurahan', 'kelurahan_id_kecamatan_fkey', 'id_kecamatan', 'kecamatan', 'id_kecamatan', 'RESTRICT'),
        ('konfirmasi_data', 'konfirmasi_data_id_periode_update_fkey', 'id_periode_update', 'periode_update', 'id_periode_update', 'RESTRICT'),
        ('konfirmasi_data', 'konfirmasi_data_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('kota', 'kota_id_provinsi_fkey', 'id_provinsi', 'provinsi', 'id_provinsi', 'RESTRICT'),
        ('log_audit', 'log_audit_id_pengguna_pengubah_fkey', 'id_pengguna_pengubah', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('log_login', 'log_login_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('mutasi_siswa', 'mutasi_siswa_id_pengaju_fkey', 'id_pengaju', 'pengguna', 'id_pengguna', 'RESTRICT'),
        ('mutasi_siswa', 'mutasi_siswa_id_penyetuju_fkey', 'id_penyetuju', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('mutasi_siswa', 'mutasi_siswa_id_sekolah_tujuan_fkey', 'id_sekolah_tujuan', 'sekolah', 'id_sekolah', 'SET NULL'),
        ('mutasi_siswa', 'mutasi_siswa_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('nilai_siswa', 'nilai_siswa_id_anggota_fkey', 'id_anggota', 'keanggotaan_kelas', 'id_keanggotaan', 'RESTRICT'),
        ('nilai_siswa', 'nilai_siswa_id_komponen_fkey', 'id_komponen', 'komponen_penilaian', 'id_komponen', 'RESTRICT'),
        ('nilai_siswa', 'nilai_siswa_id_mapel_fkey', 'id_mapel', 'mata_pelajaran', 'id_mata_pelajaran', 'RESTRICT'),
        ('nilai_siswa', 'nilai_siswa_id_penginput_fkey', 'id_penginput', 'pengguna', 'id_pengguna', 'RESTRICT'),
        ('nilai_siswa', 'nilai_siswa_id_semester_fkey', 'id_semester', 'semester', 'id_semester', 'RESTRICT'),
        ('notifikasi', 'notifikasi_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'CASCADE'),
        ('orang_tua_wali', 'orang_tua_wali_id_pekerjaan_fkey', 'id_pekerjaan', 'pekerjaan', 'id_pekerjaan', 'SET NULL'),
        ('orang_tua_wali', 'orang_tua_wali_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'CASCADE'),
        ('pendaftaran_ppdb', 'pendaftaran_ppdb_id_pengguna_pemeriksa_fkey', 'id_pengguna_pemeriksa', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('pengajuan_perubahan', 'pengajuan_perubahan_id_pemeriksa_fkey', 'id_pemeriksa', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('pengajuan_perubahan', 'pengajuan_perubahan_id_periode_update_fkey', 'id_periode_update', 'periode_update', 'id_periode_update', 'RESTRICT'),
        ('pengajuan_perubahan', 'pengajuan_perubahan_id_siswa_fkey', 'id_siswa', 'siswa', 'id_siswa', 'RESTRICT'),
        ('pengaturan_sistem', 'pengaturan_sistem_id_pengguna_pengubah_fkey', 'id_pengguna_pengubah', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('pengguna', 'pengguna_id_role_fkey', 'id_role', 'role', 'id_role', 'RESTRICT'),
        ('penugasan_wali_kelas', 'penugasan_wali_kelas_id_guru_fkey', 'id_guru', 'guru', 'id_guru', 'RESTRICT'),
        ('penugasan_wali_kelas', 'penugasan_wali_kelas_id_kelas_fkey', 'id_kelas', 'kelas', 'id_kelas', 'RESTRICT'),
        ('penugasan_wali_kelas', 'penugasan_wali_kelas_id_semester_fkey', 'id_semester', 'semester', 'id_semester', 'RESTRICT'),
        ('periode_update', 'periode_update_dibuat_oleh_fkey', 'dibuat_oleh', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('periode_update', 'periode_update_id_tahun_ajaran_fkey', 'id_tahun_ajaran', 'tahun_ajaran', 'id_tahun_ajaran', 'RESTRICT'),
        ('rapor', 'rapor_id_anggota_fkey', 'id_anggota', 'keanggotaan_kelas', 'id_keanggotaan', 'RESTRICT'),
        ('rapor', 'rapor_id_penyusun_fkey', 'id_penyusun', 'pengguna', 'id_pengguna', 'RESTRICT'),
        ('rapor', 'rapor_id_semester_fkey', 'id_semester', 'semester', 'id_semester', 'RESTRICT'),
        ('ref_checklist_item', 'ref_checklist_item_id_jenis_dokumen_fkey', 'id_jenis_dokumen', 'jenis_dokumen', 'id_jenis_dokumen', 'CASCADE'),
        ('ref_field_data', 'ref_field_data_id_jenis_dokumen_bukti_fkey', 'id_jenis_dokumen_bukti', 'jenis_dokumen', 'id_jenis_dokumen', 'SET NULL'),
        ('role_hak_akses', 'role_hak_akses_id_hak_akses_fkey', 'id_hak_akses', 'hak_akses', 'id_hak_akses', 'CASCADE'),
        ('role_hak_akses', 'role_hak_akses_id_role_fkey', 'id_role', 'role', 'id_role', 'CASCADE'),
        ('semester', 'semester_id_tahun_ajaran_fkey', 'id_tahun_ajaran', 'tahun_ajaran', 'id_tahun_ajaran', 'RESTRICT'),
        ('sesi_absensi', 'sesi_absensi_id_guru_pencatat_fkey', 'id_guru_pencatat', 'guru', 'id_guru', 'RESTRICT'),
        ('sesi_absensi', 'sesi_absensi_id_jadwal_fkey', 'id_jadwal', 'jadwal_pelajaran', 'id_jadwal', 'RESTRICT'),
        ('siswa', 'siswa_id_agama_fkey', 'id_agama', 'agama', 'id_agama', 'SET NULL'),
        ('siswa', 'siswa_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('siswa', 'siswa_id_sekolah_asal_fkey', 'id_sekolah_asal', 'sekolah', 'id_sekolah', 'SET NULL'),
        ('siswa', 'siswa_id_status_siswa_fkey', 'id_status_siswa', 'status_siswa', 'id_status_siswa', 'RESTRICT'),
        ('staf', 'staf_id_pengguna_fkey', 'id_pengguna', 'pengguna', 'id_pengguna', 'SET NULL'),
        ('verifikasi_pendaftaran', 'verifikasi_pendaftaran_id_alasan_fkey', 'id_alasan', 'ref_alasan_pengembalian', 'id_alasan', 'SET NULL'),
        ('verifikasi_pendaftaran', 'verifikasi_pendaftaran_id_pendaftaran_fkey', 'id_pendaftaran', 'pendaftaran_ppdb', 'id_pendaftaran', 'CASCADE'),
        ('verifikasi_pendaftaran', 'verifikasi_pendaftaran_id_petugas_fkey', 'id_petugas', 'pengguna', 'id_pengguna', 'RESTRICT')
    ) AS candidates(child_table, constraint_name, child_column_names, parent_table, parent_column_names, on_delete)
    LOOP
        v_table_oid := to_regclass(format('public.%I', v.child_table));
        v_parent_oid := to_regclass(format('public.%I', v.parent_table));
        IF v_table_oid IS NULL OR v_parent_oid IS NULL THEN
            RAISE EXCEPTION 'Tabel FK tidak ditemukan: public.%.% -> public.%', v.child_table, v.constraint_name, v.parent_table;
        END IF;

        SELECT array_agg(a.attnum::smallint ORDER BY x.ordinality),
               string_agg(format('%I', btrim(x.column_name)), ', ' ORDER BY x.ordinality),
               string_agg(format('c.%I IS NOT NULL', btrim(x.column_name)), ' AND ' ORDER BY x.ordinality)
        INTO v_keys, v_columns, v_null_predicate
        FROM unnest(string_to_array(v.child_column_names, ',')) WITH ORDINALITY AS x(column_name, ordinality)
        JOIN pg_attribute a ON a.attrelid = v_table_oid AND a.attname = btrim(x.column_name) AND NOT a.attisdropped;

        SELECT array_agg(a.attnum::smallint ORDER BY x.ordinality),
               string_agg(format('%I', btrim(x.column_name)), ', ' ORDER BY x.ordinality)
        INTO v_parent_keys, v_parent_columns
        FROM unnest(string_to_array(v.parent_column_names, ',')) WITH ORDINALITY AS x(column_name, ordinality)
        JOIN pg_attribute a ON a.attrelid = v_parent_oid AND a.attname = btrim(x.column_name) AND NOT a.attisdropped;

        IF v_keys IS NULL
           OR v_parent_keys IS NULL
           OR cardinality(v_keys) <> cardinality(string_to_array(v.child_column_names, ','))
           OR cardinality(v_parent_keys) <> cardinality(string_to_array(v.parent_column_names, ','))
           OR cardinality(v_keys) <> cardinality(v_parent_keys) THEN
            RAISE EXCEPTION 'Kolom FK tidak cocok: public.%.% -> public.%', v.child_table, v.constraint_name, v.parent_table;
        END IF;

        IF v.on_delete = 'SET NULL' THEN
            SELECT EXISTS (
                SELECT 1
                FROM unnest(string_to_array(v.child_column_names, ',')) AS x(column_name)
                JOIN pg_attribute a ON a.attrelid = v_table_oid AND a.attname = btrim(x.column_name)
                WHERE a.attnotnull
            ) INTO v_has_issue;
            IF v_has_issue THEN
                RAISE EXCEPTION 'SET NULL tidak aman untuk kolom NOT NULL pada public.%.%', v.child_table, v.constraint_name;
            END IF;
        END IF;

        SELECT string_agg(format('c.%I = p.%I', btrim(child.column_name), btrim(parent.column_name)), ' AND ' ORDER BY child.ordinality)
        INTO v_match_predicate
        FROM unnest(string_to_array(v.child_column_names, ',')) WITH ORDINALITY AS child(column_name, ordinality)
        JOIN unnest(string_to_array(v.parent_column_names, ',')) WITH ORDINALITY AS parent(column_name, ordinality)
          ON parent.ordinality = child.ordinality;

        v_expected_action := CASE v.on_delete
            WHEN 'CASCADE' THEN 'c'::"char"
            WHEN 'SET NULL' THEN 'n'::"char"
            WHEN 'SET DEFAULT' THEN 'd'::"char"
            WHEN 'RESTRICT' THEN 'r'::"char"
            ELSE 'a'::"char"
        END;

        SELECT conkey, confrelid, confkey, confdeltype, confupdtype, contype, convalidated
        INTO v_existing_keys, v_existing_parent_oid, v_existing_parent_keys, v_existing_action, v_existing_update_action, v_existing_type, v_is_valid
        FROM pg_constraint
        WHERE conrelid = v_table_oid AND conname = v.constraint_name;

        IF FOUND THEN
            IF v_existing_type <> 'f' OR v_existing_keys IS DISTINCT FROM v_keys
               OR v_existing_parent_oid IS DISTINCT FROM v_parent_oid
               OR v_existing_parent_keys IS DISTINCT FROM v_parent_keys
               OR v_existing_action IS DISTINCT FROM v_expected_action
               OR v_existing_update_action <> 'a' THEN
                RAISE EXCEPTION 'Definisi FK existing berbeda: public.%.%', v.child_table, v.constraint_name;
            END IF;
        ELSE
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I FOREIGN KEY (%s) REFERENCES public.%I (%s) ON DELETE %s NOT VALID',
                v.child_table, v.constraint_name, v_columns, v.parent_table, v_parent_columns, v.on_delete);
            v_is_valid := false;
        END IF;

        EXECUTE format(
            'SELECT EXISTS (SELECT 1 FROM public.%I c WHERE %s AND NOT EXISTS (SELECT 1 FROM public.%I p WHERE %s))',
            v.child_table, v_null_predicate, v.parent_table, v_match_predicate
        ) INTO v_has_issue;

        IF v_has_issue THEN
            IF v_is_valid THEN
                RAISE EXCEPTION 'FK tervalidasi memiliki baris yatim: public.%.%', v.child_table, v.constraint_name;
            END IF;
            RAISE NOTICE 'FK tetap NOT VALID karena baris yatim: public.%.%', v.child_table, v.constraint_name;
        ELSIF NOT v_is_valid THEN
            EXECUTE format('ALTER TABLE public.%I VALIDATE CONSTRAINT %I', v.child_table, v.constraint_name);
        END IF;
    END LOOP;
END
$fase1$;
