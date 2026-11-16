-- Tahap 0: audit read-only; baseline FK berasal dari schema_backup.sql.

SELECT
    c.relname AS table_name
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relkind IN ('r', 'p')
  AND NOT EXISTS (
      SELECT 1
      FROM pg_constraint k
      WHERE k.conrelid = c.oid
        AND k.contype = 'p'
  )
ORDER BY c.relname;

WITH expected_fk(constraint_name, child_table) AS (
    VALUES
        ('alamat_siswa_id_siswa_fkey', 'alamat_siswa'),
        ('catatan_wali_kelas_id_guru_fkey', 'catatan_wali_kelas'),
        ('catatan_wali_kelas_id_siswa_fkey', 'catatan_wali_kelas'),
        ('catatan_wali_kelas_id_tahun_ajaran_fkey', 'catatan_wali_kelas'),
        ('cetak_rapor_id_pencetak_fkey', 'cetak_rapor'),
        ('cetak_rapor_id_rapor_fkey', 'cetak_rapor'),
        ('detail_absensi_id_sesi_fkey', 'detail_absensi'),
        ('detail_absensi_id_siswa_fkey', 'detail_absensi'),
        ('detail_absensi_id_status_hadir_fkey', 'detail_absensi'),
        ('detail_pengajuan_id_pengajuan_fkey', 'detail_pengajuan'),
        ('detail_rapor_id_mapel_fkey', 'detail_rapor'),
        ('detail_rapor_id_predikat_fkey', 'detail_rapor'),
        ('detail_rapor_id_rapor_fkey', 'detail_rapor'),
        ('detail_verifikasi_id_item_fkey', 'detail_verifikasi'),
        ('detail_verifikasi_id_verifikasi_fkey', 'detail_verifikasi'),
        ('dokumen_siswa_id_pendaftaran_fkey', 'dokumen_siswa'),
        ('dokumen_siswa_id_siswa_fkey', 'dokumen_siswa'),
        ('ekspor_laporan_id_kelas_fkey', 'ekspor_laporan'),
        ('ekspor_laporan_id_pengguna_fkey', 'ekspor_laporan'),
        ('ekspor_laporan_id_tahun_ajaran_fkey', 'ekspor_laporan'),
        ('fk_kelas_tahun_ajaran', 'kelas'),
        ('fk_tingkat_jenjang', 'tingkat'),
        ('guru_id_pengguna_fkey', 'guru'),
        ('izin_siswa_id_dokumen_bukti_fkey', 'izin_siswa'),
        ('izin_siswa_id_penyetuju_fkey', 'izin_siswa'),
        ('izin_siswa_id_siswa_fkey', 'izin_siswa'),
        ('izin_siswa_id_status_hadir_fkey', 'izin_siswa'),
        ('jadwal_pelajaran_id_guru_fkey', 'jadwal_pelajaran'),
        ('jadwal_pelajaran_id_kelas_fkey', 'jadwal_pelajaran'),
        ('jadwal_pelajaran_id_mata_pelajaran_fkey', 'jadwal_pelajaran'),
        ('jadwal_pelajaran_id_semester_fkey', 'jadwal_pelajaran'),
        ('keanggotaan_kelas_id_kelas_fkey', 'keanggotaan_kelas'),
        ('keanggotaan_kelas_id_semester_fkey', 'keanggotaan_kelas'),
        ('keanggotaan_kelas_id_siswa_fkey', 'keanggotaan_kelas'),
        ('kecamatan_id_kota_fkey', 'kecamatan'),
        ('kelas_id_tingkat_fkey', 'kelas'),
        ('kelurahan_id_kecamatan_fkey', 'kelurahan'),
        ('konfirmasi_data_id_periode_update_fkey', 'konfirmasi_data'),
        ('konfirmasi_data_id_siswa_fkey', 'konfirmasi_data'),
        ('kota_id_provinsi_fkey', 'kota'),
        ('log_audit_id_pengguna_pengubah_fkey', 'log_audit'),
        ('log_login_id_pengguna_fkey', 'log_login'),
        ('mutasi_siswa_id_pengaju_fkey', 'mutasi_siswa'),
        ('mutasi_siswa_id_penyetuju_fkey', 'mutasi_siswa'),
        ('mutasi_siswa_id_sekolah_tujuan_fkey', 'mutasi_siswa'),
        ('mutasi_siswa_id_siswa_fkey', 'mutasi_siswa'),
        ('nilai_siswa_id_anggota_fkey', 'nilai_siswa'),
        ('nilai_siswa_id_komponen_fkey', 'nilai_siswa'),
        ('nilai_siswa_id_mapel_fkey', 'nilai_siswa'),
        ('nilai_siswa_id_penginput_fkey', 'nilai_siswa'),
        ('nilai_siswa_id_semester_fkey', 'nilai_siswa'),
        ('notifikasi_id_pengguna_fkey', 'notifikasi'),
        ('orang_tua_wali_id_pekerjaan_fkey', 'orang_tua_wali'),
        ('orang_tua_wali_id_siswa_fkey', 'orang_tua_wali'),
        ('pendaftaran_ppdb_id_pengguna_pemeriksa_fkey', 'pendaftaran_ppdb'),
        ('pengajuan_perubahan_id_pemeriksa_fkey', 'pengajuan_perubahan'),
        ('pengajuan_perubahan_id_periode_update_fkey', 'pengajuan_perubahan'),
        ('pengajuan_perubahan_id_siswa_fkey', 'pengajuan_perubahan'),
        ('pengaturan_sistem_id_pengguna_pengubah_fkey', 'pengaturan_sistem'),
        ('pengguna_id_role_fkey', 'pengguna'),
        ('penugasan_wali_kelas_id_guru_fkey', 'penugasan_wali_kelas'),
        ('penugasan_wali_kelas_id_kelas_fkey', 'penugasan_wali_kelas'),
        ('penugasan_wali_kelas_id_semester_fkey', 'penugasan_wali_kelas'),
        ('periode_update_dibuat_oleh_fkey', 'periode_update'),
        ('periode_update_id_tahun_ajaran_fkey', 'periode_update'),
        ('rapor_id_anggota_fkey', 'rapor'),
        ('rapor_id_penyusun_fkey', 'rapor'),
        ('rapor_id_semester_fkey', 'rapor'),
        ('ref_checklist_item_id_jenis_dokumen_fkey', 'ref_checklist_item'),
        ('ref_field_data_id_jenis_dokumen_bukti_fkey', 'ref_field_data'),
        ('role_hak_akses_id_hak_akses_fkey', 'role_hak_akses'),
        ('role_hak_akses_id_role_fkey', 'role_hak_akses'),
        ('semester_id_tahun_ajaran_fkey', 'semester'),
        ('sesi_absensi_id_guru_pencatat_fkey', 'sesi_absensi'),
        ('sesi_absensi_id_jadwal_fkey', 'sesi_absensi'),
        ('siswa_id_agama_fkey', 'siswa'),
        ('siswa_id_pengguna_fkey', 'siswa'),
        ('siswa_id_sekolah_asal_fkey', 'siswa'),
        ('siswa_id_status_siswa_fkey', 'siswa'),
        ('staf_id_pengguna_fkey', 'staf'),
        ('verifikasi_pendaftaran_id_alasan_fkey', 'verifikasi_pendaftaran'),
        ('verifikasi_pendaftaran_id_pendaftaran_fkey', 'verifikasi_pendaftaran'),
        ('verifikasi_pendaftaran_id_petugas_fkey', 'verifikasi_pendaftaran')
),
actual_fk AS (
    SELECT k.conname, child.relname AS child_table
    FROM pg_constraint k
    JOIN pg_class child ON child.oid = k.conrelid
    JOIN pg_namespace n ON n.oid = child.relnamespace
    WHERE k.contype = 'f'
      AND n.nspname = 'public'
)
SELECT expected_fk.constraint_name AS missing_constraint,
       expected_fk.child_table
FROM expected_fk
LEFT JOIN actual_fk
  ON actual_fk.conname = expected_fk.constraint_name
 AND actual_fk.child_table = expected_fk.child_table
WHERE actual_fk.conname IS NULL
ORDER BY expected_fk.child_table, expected_fk.constraint_name;

SELECT object_type, object_count
FROM (
    SELECT 'table'::text AS object_type, COUNT(*)::bigint AS object_count
    FROM pg_class c
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p')
    UNION ALL
    SELECT 'view', COUNT(*)::bigint
    FROM pg_class c
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public' AND c.relkind = 'v'
    UNION ALL
    SELECT 'stored_function_non_trigger', COUNT(*)::bigint
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.prokind = 'f'
      AND p.prorettype <> 'trigger'::regtype
      AND NOT EXISTS (
          SELECT 1 FROM pg_depend d WHERE d.objid = p.oid AND d.deptype = 'e'
      )
    UNION ALL
    SELECT 'trigger_function', COUNT(*)::bigint
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.prorettype = 'trigger'::regtype
      AND NOT EXISTS (
          SELECT 1 FROM pg_depend d WHERE d.objid = p.oid AND d.deptype = 'e'
      )
    UNION ALL
    SELECT 'procedure', COUNT(*)::bigint
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public' AND p.prokind = 'p'
    UNION ALL
    SELECT 'trigger', COUNT(*)::bigint
    FROM pg_trigger t
    JOIN pg_class c ON c.oid = t.tgrelid
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public' AND NOT t.tgisinternal
    UNION ALL
    SELECT 'role', COUNT(*)::bigint FROM pg_roles
    UNION ALL
    SELECT 'target_role', COUNT(*)::bigint
    FROM pg_roles
    WHERE rolname IN ('app_owner', 'app_login', 'role_siswa', 'role_tu', 'role_kepsek', 'role_wali_kelas')
) object_counts
ORDER BY object_type;
