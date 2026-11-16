-- Candidate UNIQUE constraints are based on schema_backup.sql and the staging data preflight.
-- Legacy keys that conflict with current class/history rules are intentionally omitted.
DO $fase1$
DECLARE
    v record;
    v_table_oid oid;
    v_keys smallint[];
    v_existing_keys smallint[];
    v_existing_type "char";
    v_index_name name;
    v_columns text;
    v_nonnull_predicate text;
    v_has_issue boolean;
BEGIN
    IF current_database() <> 'msbd_tubes_kel7_staging' THEN
        RAISE EXCEPTION 'Fase 1 UNIQUE hanya diizinkan pada msbd_tubes_kel7_staging';
    END IF;

    FOR v IN SELECT * FROM (VALUES
        ('agama', 'agama_nama_agama_key', 'nama_agama'),
        ('alamat_siswa', 'alamat_siswa_id_siswa_key', 'id_siswa'),
        ('cetak_rapor', 'cetak_rapor_nomor_seri_key', 'nomor_seri'),
        ('detail_absensi', 'detail_absensi_id_sesi_id_siswa_key', 'id_sesi,id_siswa'),
        ('detail_pengajuan', 'detail_pengajuan_id_pengajuan_nama_field_key', 'id_pengajuan,nama_field'),
        ('detail_rapor', 'detail_rapor_id_rapor_id_mapel_key', 'id_rapor,id_mapel'),
        ('failed_jobs', 'failed_jobs_uuid_unique', 'uuid'),
        ('guru', 'guru_id_pengguna_key', 'id_pengguna'),
        ('guru', 'guru_nip_key', 'nip'),
        ('hak_akses', 'hak_akses_kode_hak_akses_key', 'kode_hak_akses'),
        ('jenis_dokumen', 'jenis_dokumen_nama_dokumen_key', 'nama_dokumen'),
        ('jenjang', 'jenjang_nama_jenjang_key', 'nama_jenjang'),
        ('kecamatan', 'kecamatan_id_kota_nama_kecamatan_key', 'id_kota,nama_kecamatan'),
        ('kelurahan', 'kelurahan_id_kecamatan_nama_kelurahan_key', 'id_kecamatan,nama_kelurahan'),
        ('komponen_penilaian', 'komponen_penilaian_nama_komponen_key', 'nama_komponen'),
        ('konfirmasi_data', 'konfirmasi_data_id_periode_update_id_siswa_key', 'id_periode_update,id_siswa'),
        ('kota', 'kota_id_provinsi_nama_kota_key', 'id_provinsi,nama_kota'),
        ('mata_pelajaran', 'mata_pelajaran_kode_mapel_key', 'kode_mapel'),
        ('mata_pelajaran', 'mata_pelajaran_nama_mapel_key', 'nama_mapel'),
        ('nilai_siswa', 'nilai_siswa_id_anggota_id_mapel_id_semester_id_komponen_key', 'id_anggota,id_mapel,id_semester,id_komponen'),
        ('orang_tua_wali', 'orang_tua_wali_id_siswa_hubungan_key', 'id_siswa,hubungan'),
        ('pekerjaan', 'pekerjaan_nama_pekerjaan_key', 'nama_pekerjaan'),
        ('pendaftaran_ppdb', 'pendaftaran_ppdb_nomor_pendaftaran_key', 'nomor_pendaftaran'),
        ('pengaturan_sistem', 'pengaturan_sistem_kunci_key', 'kunci'),
        ('pengguna', 'pengguna_username_key', 'username'),
        ('penugasan_wali_kelas', 'penugasan_wali_kelas_id_kelas_id_semester_key', 'id_kelas,id_semester'),
        ('provinsi', 'provinsi_nama_provinsi_key', 'nama_provinsi'),
        ('rapor', 'rapor_id_anggota_id_semester_key', 'id_anggota,id_semester'),
        ('ref_alasan_pengembalian', 'ref_alasan_pengembalian_nama_alasan_key', 'nama_alasan'),
        ('ref_checklist_item', 'ref_checklist_item_id_jenis_dokumen_nama_item_key', 'id_jenis_dokumen,nama_item'),
        ('ref_field_data', 'ref_field_data_nama_tabel_nama_kolom_key', 'nama_tabel,nama_kolom'),
        ('role', 'role_nama_role_key', 'nama_role'),
        ('semester', 'semester_id_tahun_ajaran_nama_semester_key', 'id_tahun_ajaran,nama_semester'),
        ('sesi_absensi', 'sesi_absensi_id_jadwal_tanggal_key', 'id_jadwal,tanggal'),
        ('siswa', 'siswa_id_pengguna_key', 'id_pengguna'),
        ('siswa', 'siswa_nik_key', 'nik'),
        ('siswa', 'siswa_nis_key', 'nis'),
        ('siswa', 'siswa_nisn_key', 'nisn'),
        ('skala_predikat', 'skala_predikat_huruf_key', 'huruf'),
        ('staf', 'staf_id_pengguna_key', 'id_pengguna'),
        ('status_kehadiran', 'status_kehadiran_kode_key', 'kode'),
        ('status_siswa', 'status_siswa_kode_status_key', 'kode_status'),
        ('status_siswa', 'status_siswa_nama_status_key', 'nama_status'),
        ('tahun_ajaran', 'tahun_ajaran_nama_tahun_ajaran_key', 'nama_tahun_ajaran'),
        ('tingkat', 'tingkat_nama_tingkat_key', 'nama_tingkat'),
        ('tingkat', 'tingkat_urutan_key', 'urutan'),
        ('sekolah', 'uq_sekolah_npsn', 'npsn'),
        ('users', 'users_email_unique', 'email')
    ) AS candidates(table_name, constraint_name, column_names)
    LOOP
        v_table_oid := to_regclass(format('public.%I', v.table_name));
        IF v_table_oid IS NULL THEN
            RAISE EXCEPTION 'Tabel kandidat UNIQUE tidak ditemukan: public.%', v.table_name;
        END IF;

        SELECT array_agg(a.attnum::smallint ORDER BY x.ordinality),
               string_agg(format('%I', btrim(x.column_name)), ', ' ORDER BY x.ordinality),
               string_agg(format('%I IS NOT NULL', btrim(x.column_name)), ' AND ' ORDER BY x.ordinality)
        INTO v_keys, v_columns, v_nonnull_predicate
        FROM unnest(string_to_array(v.column_names, ',')) WITH ORDINALITY AS x(column_name, ordinality)
        JOIN pg_attribute a ON a.attrelid = v_table_oid AND a.attname = btrim(x.column_name) AND NOT a.attisdropped;

        IF v_keys IS NULL
           OR cardinality(v_keys) <> cardinality(string_to_array(v.column_names, ',')) THEN
            RAISE EXCEPTION 'Kolom kandidat UNIQUE tidak ditemukan: public.%.%', v.table_name, v.column_names;
        END IF;

        SELECT contype, conkey INTO v_existing_type, v_existing_keys
        FROM pg_constraint WHERE conrelid = v_table_oid AND conname = v.constraint_name;
        IF FOUND THEN
            IF v_existing_type NOT IN ('u', 'p') OR v_existing_keys IS DISTINCT FROM v_keys THEN
                RAISE EXCEPTION 'Definisi constraint existing berbeda: public.%.%', v.table_name, v.constraint_name;
            END IF;
            CONTINUE;
        END IF;

        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = v_table_oid AND contype IN ('u', 'p') AND conkey = v_keys) THEN
            CONTINUE;
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
          AND NOT i.indnullsnotdistinct
          AND am.amname = 'btree'
          AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid)
        ORDER BY idx.relname
        LIMIT 1;

        IF v_index_name IS NULL THEN
            EXECUTE format('SELECT EXISTS (SELECT 1 FROM public.%I WHERE %s GROUP BY %s HAVING count(*) > 1)',
                v.table_name, v_nonnull_predicate, v_columns) INTO v_has_issue;
            IF v_has_issue THEN
                RAISE EXCEPTION 'Kandidat UNIQUE public.%.% memiliki duplikasi', v.table_name, v.column_names;
            END IF;
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I UNIQUE (%s)',
                v.table_name, v.constraint_name, v_columns);
        ELSE
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I UNIQUE USING INDEX public.%I',
                v.table_name, v.constraint_name, v_index_name);
        END IF;
    END LOOP;
END
$fase1$;

-- CHECK constraints from schema_backup.sql; keanggotaan includes the application status used by promotion.
DO $fase1$
DECLARE
    v record;
    v_table_oid oid;
    v_constraint_type "char";
    v_is_valid boolean;
    v_has_issue boolean;
BEGIN
    IF current_database() <> 'msbd_tubes_kel7_staging' THEN
        RAISE EXCEPTION 'Fase 1 CHECK hanya diizinkan pada msbd_tubes_kel7_staging';
    END IF;

    FOR v IN SELECT * FROM (VALUES
        ('detail_rapor', 'detail_rapor_nilai_akhir_check', '(((nilai_akhir >= (0)::numeric) AND (nilai_akhir <= (100)::numeric)))'),
        ('dokumen_siswa', 'dokumen_siswa_check', '(((id_siswa IS NOT NULL) OR (id_pendaftaran IS NOT NULL)))'),
        ('dokumen_siswa', 'dokumen_siswa_status_verifikasi_check', '(((status_verifikasi)::text = ANY ((ARRAY[''menunggu''::character varying, ''valid''::character varying, ''ditolak''::character varying, ''perlu_perbaikan''::character varying])::text[])))'),
        ('izin_siswa', 'izin_siswa_check', '((tgl_selesai >= tgl_mulai))'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_check', '((jam_selesai > jam_mulai))'),
        ('jadwal_pelajaran', 'jadwal_pelajaran_hari_check', '(((hari >= 1) AND (hari <= 7)))'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_check', '(((tanggal_selesai IS NULL) OR (tanggal_selesai >= tanggal_mulai)))'),
        ('keanggotaan_kelas', 'keanggotaan_kelas_status_check', 'status IN (''aktif'',''pindah'',''naik_kelas'',''tidak_naik_kelas'',''lulus'',''keluar'')'),
        ('kelas', 'kelas_kapasitas_check', '(((kapasitas IS NULL) OR (kapasitas > 0)))'),
        ('komponen_penilaian', 'komponen_penilaian_bobot_persen_check', '(((bobot_persen >= (0)::numeric) AND (bobot_persen <= (100)::numeric)))'),
        ('konfirmasi_data', 'konfirmasi_data_status_check', '(((status)::text = ANY ((ARRAY[''belum''::character varying, ''dikonfirmasi''::character varying, ''perlu_perubahan''::character varying])::text[])))'),
        ('log_audit', 'log_audit_operasi_check', '(((operasi)::text = ANY ((ARRAY[''INSERT''::character varying, ''UPDATE''::character varying, ''DELETE''::character varying])::text[])))'),
        ('nilai_siswa', 'nilai_siswa_nilai_check', '(((nilai >= (0)::numeric) AND (nilai <= (100)::numeric)))'),
        ('orang_tua_wali', 'orang_tua_wali_hubungan_check', '(((hubungan)::text = ANY ((ARRAY[''ayah''::character varying, ''ibu''::character varying, ''wali''::character varying])::text[])))'),
        ('pendaftaran_ppdb', 'pendaftaran_ppdb_status_pendaftaran_check', '(((status_pendaftaran)::text = ANY ((ARRAY[''menunggu''::character varying, ''diverifikasi''::character varying, ''diterima''::character varying, ''ditolak''::character varying, ''perlu_perbaikan''::character varying])::text[])))'),
        ('pengajuan_perubahan', 'pengajuan_perubahan_status_pengajuan_check', '(((status_pengajuan)::text = ANY ((ARRAY[''menunggu''::character varying, ''disetujui''::character varying, ''ditolak''::character varying, ''perlu_perbaikan''::character varying])::text[])))'),
        ('periode_update', 'periode_update_check', '((tanggal_selesai >= tanggal_mulai))'),
        ('periode_update', 'periode_update_status_check', '(((status)::text = ANY ((ARRAY[''draft''::character varying, ''aktif''::character varying, ''ditutup''::character varying])::text[])))'),
        ('rapor', 'rapor_check', '(((jumlah_sakit >= 0) AND (jumlah_izin >= 0) AND (jumlah_alpa >= 0)))'),
        ('rapor', 'rapor_peringkat_check', '(((peringkat IS NULL) OR (peringkat > 0)))'),
        ('semester', 'semester_check', '(((tanggal_selesai IS NULL) OR (tanggal_mulai IS NULL) OR (tanggal_selesai > tanggal_mulai)))'),
        ('siswa', 'siswa_jenis_kelamin_check', '((jenis_kelamin = ANY (ARRAY[''L''::bpchar, ''P''::bpchar])))'),
        ('skala_predikat', 'skala_predikat_check', '((nilai_min <= nilai_maks))'),
        ('tahun_ajaran', 'tahun_ajaran_check', '((tanggal_selesai > tanggal_mulai))'),
        ('tingkat', 'tingkat_urutan_check', '((urutan > 0))')
    ) AS candidates(table_name, constraint_name, check_expression)
    LOOP
        v_table_oid := to_regclass(format('public.%I', v.table_name));
        IF v_table_oid IS NULL THEN
            RAISE EXCEPTION 'Tabel kandidat CHECK tidak ditemukan: public.%', v.table_name;
        END IF;

        SELECT contype, convalidated INTO v_constraint_type, v_is_valid
        FROM pg_constraint WHERE conrelid = v_table_oid AND conname = v.constraint_name;
        IF FOUND THEN
            IF v_constraint_type <> 'c' THEN
                RAISE EXCEPTION 'Nama constraint existing bukan CHECK: public.%.%', v.table_name, v.constraint_name;
            END IF;
        ELSE
            EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I CHECK %s NOT VALID',
                v.table_name, v.constraint_name, v.check_expression);
            v_is_valid := false;
        END IF;

        EXECUTE format('SELECT EXISTS (SELECT 1 FROM public.%I WHERE NOT (%s))',
            v.table_name, v.check_expression) INTO v_has_issue;
        IF v_has_issue THEN
            IF v_is_valid THEN
                RAISE EXCEPTION 'CHECK tervalidasi memiliki pelanggaran: public.%.%', v.table_name, v.constraint_name;
            END IF;
            RAISE NOTICE 'CHECK tetap NOT VALID karena data lama: public.%.%', v.table_name, v.constraint_name;
        ELSIF NOT v_is_valid THEN
            EXECUTE format('ALTER TABLE public.%I VALIDATE CONSTRAINT %I', v.table_name, v.constraint_name);
        END IF;
    END LOOP;
END
$fase1$;
