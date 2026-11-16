# ORPHAN_REPORT - staging

## Metode dan ringkasan hasil

Audit dijalankan pada `msbd_tubes_kel7_staging` (PostgreSQL 18.6) setelah Tahap 1. Katalog FK
dibaca dari `pg_constraint`; setiap FK diperiksa dengan anti-join terhadap seluruh kolom referensi.
NULL mengikuti semantik `MATCH` PostgreSQL: `MATCH SIMPLE` mengabaikan kunci child yang mengandung
NULL, sedangkan `MATCH FULL` menghitung kombinasi NULL parsial sebagai pelanggaran. Status
`NOT VALID` berasal dari `convalidated = false`. Audit memakai tabel temporer pada koneksi psql dan
transaksi `READ ONLY`; tidak mengubah data atau schema persisten.

<!-- BEGIN GENERATED FK SUMMARY -->
- Basis schema_backup.sql: **83 FK**; definisi cocok penuh dengan katalog staging: **83**; definisi berbeda: **0**; tidak ditemukan: **0**; FK katalog di luar basis: **13**.
- Katalog staging: **96 FK** (93 `VALID`, 3 `NOT VALID`); anti-join menemukan **3 baris yatim**.
- Tidak ada FK baseline yang hilang dari katalog staging.
- Tidak ada perbedaan tabel/kolom/parent/ON DELETE pada FK baseline yang ditemukan.
- Tidak ada row yatim yang diperbaiki/dihapus oleh audit ini. FK `NOT VALID` dipertahankan sampai pemetaan bisnis dan pembersihan data disetujui; menjalankan `VALIDATE CONSTRAINT` sebelum orphan menjadi nol tidak dilakukan.
<!-- END GENERATED FK SUMMARY -->
- PK baseline: 64 kandidat diperiksa; setelah pemetaan `status_siswa`, tidak ada nilai NULL atau
  grup duplikat, dan 64 PK kandidat berhasil ditambahkan. Bersama 8 PK yang sudah ada, katalog
  berisi 72 PK.
- UNIQUE baseline: 51 kandidat diperiksa. Sebanyak 48 ditambahkan; tiga sengaja dilewatkan:
  `kelas_id_tingkat_nama_kelas_key` berkonflik dengan aturan unik kelas per tahun ajaran;
  `keanggotaan_kelas_id_siswa_id_semester_key` bertentangan dengan dukungan riwayat/multiple
  membership dan guard test aplikasi; `penugasan_wali_kelas_id_guru_id_semester_id_kelas_key`
  redundan terhadap aturan unik pasangan `(id_kelas, id_semester)`. Katalog berisi 56 UNIQUE,
  termasuk 8 yang sudah ada.
- CHECK: 25 kandidat diperiksa dan tidak menemukan baris pelanggar; katalog memuat 25 CHECK.
  Angka ini adalah jumlah katalog terverifikasi, bukan klaim bahwa 25 constraint semuanya baru.

## Rincian seluruh FK

<!-- BEGIN GENERATED FK DETAILS -->
| FK | Definisi cocok dengan schema_backup.sql | Tabel child | Kolom child | Tabel parent | Kolom parent | ON DELETE | Status | MATCH | Baris yatim | Catatan hasil/kebijakan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | ---: | --- |
| `alamat_siswa_id_siswa_fkey` | Ya | `public.alamat_siswa` | `id_siswa` | `public.siswa` | `id_siswa` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `catatan_wali_kelas_id_guru_fkey` | Ya | `public.catatan_wali_kelas` | `id_guru` | `public.guru` | `id_guru` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `catatan_wali_kelas_id_siswa_fkey` | Ya | `public.catatan_wali_kelas` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `catatan_wali_kelas_id_tahun_ajaran_fkey` | Ya | `public.catatan_wali_kelas` | `id_tahun_ajaran` | `public.tahun_ajaran` | `id_tahun_ajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `cetak_rapor_id_pencetak_fkey` | Ya | `public.cetak_rapor` | `id_pencetak` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `cetak_rapor_id_rapor_fkey` | Ya | `public.cetak_rapor` | `id_rapor` | `public.rapor` | `id_rapor` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_absensi_id_sesi_fkey` | Ya | `public.detail_absensi` | `id_sesi` | `public.sesi_absensi` | `id_sesi` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_absensi_id_siswa_fkey` | Ya | `public.detail_absensi` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_absensi_id_status_hadir_fkey` | Ya | `public.detail_absensi` | `id_status_hadir` | `public.status_kehadiran` | `id_status_hadir` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_pengajuan_id_pengajuan_fkey` | Ya | `public.detail_pengajuan` | `id_pengajuan` | `public.pengajuan_perubahan` | `id_pengajuan` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_rapor_id_mapel_fkey` | Ya | `public.detail_rapor` | `id_mapel` | `public.mata_pelajaran` | `id_mata_pelajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_rapor_id_predikat_fkey` | Ya | `public.detail_rapor` | `id_predikat` | `public.skala_predikat` | `id_predikat` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_rapor_id_rapor_fkey` | Ya | `public.detail_rapor` | `id_rapor` | `public.rapor` | `id_rapor` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_verifikasi_id_item_fkey` | Ya | `public.detail_verifikasi` | `id_item` | `public.ref_checklist_item` | `id_item` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `detail_verifikasi_id_verifikasi_fkey` | Ya | `public.detail_verifikasi` | `id_verifikasi` | `public.verifikasi_pendaftaran` | `id_verifikasi` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `dokumen_siswa_id_pendaftaran_fkey` | Ya | `public.dokumen_siswa` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `dokumen_siswa_id_siswa_fkey` | Ya | `public.dokumen_siswa` | `id_siswa` | `public.siswa` | `id_siswa` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ekspor_laporan_id_kelas_fkey` | Ya | `public.ekspor_laporan` | `id_kelas` | `public.kelas` | `id_kelas` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ekspor_laporan_id_pengguna_fkey` | Ya | `public.ekspor_laporan` | `id_pengguna` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ekspor_laporan_id_tahun_ajaran_fkey` | Ya | `public.ekspor_laporan` | `id_tahun_ajaran` | `public.tahun_ajaran` | `id_tahun_ajaran` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `guru_id_pengguna_fkey` | Ya | `public.guru` | `id_pengguna` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `izin_siswa_id_dokumen_bukti_fkey` | Ya | `public.izin_siswa` | `id_dokumen_bukti` | `public.dokumen_siswa` | `id_dokumen` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `izin_siswa_id_penyetuju_fkey` | Ya | `public.izin_siswa` | `id_penyetuju` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `izin_siswa_id_siswa_fkey` | Ya | `public.izin_siswa` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `izin_siswa_id_status_hadir_fkey` | Ya | `public.izin_siswa` | `id_status_hadir` | `public.status_kehadiran` | `id_status_hadir` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `jadwal_pelajaran_id_guru_fkey` | Ya | `public.jadwal_pelajaran` | `id_guru` | `public.guru` | `id_guru` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `jadwal_pelajaran_id_kelas_fkey` | Ya | `public.jadwal_pelajaran` | `id_kelas` | `public.kelas` | `id_kelas` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `jadwal_pelajaran_id_mata_pelajaran_fkey` | Ya | `public.jadwal_pelajaran` | `id_mata_pelajaran` | `public.mata_pelajaran` | `id_mata_pelajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `jadwal_pelajaran_id_semester_fkey` | Ya | `public.jadwal_pelajaran` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `keanggotaan_kelas_id_kelas_fkey` | Ya | `public.keanggotaan_kelas` | `id_kelas` | `public.kelas` | `id_kelas` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `keanggotaan_kelas_id_semester_fkey` | Ya | `public.keanggotaan_kelas` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `keanggotaan_kelas_id_siswa_fkey` | Ya | `public.keanggotaan_kelas` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `kecamatan_id_kota_fkey` | Ya | `public.kecamatan` | `id_kota` | `public.kota` | `id_kota` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `fk_kelas_tahun_ajaran` | Ya | `public.kelas` | `id_tahun_ajaran` | `public.tahun_ajaran` | `id_tahun_ajaran` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `kelas_id_jurusan_foreign` | Tidak | `public.kelas` | `id_jurusan` | `public.jurusan` | `id_jurusan` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `kelas_id_tingkat_fkey` | Ya | `public.kelas` | `id_tingkat` | `public.tingkat` | `id_tingkat` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `kelurahan_id_kecamatan_fkey` | Ya | `public.kelurahan` | `id_kecamatan` | `public.kecamatan` | `id_kecamatan` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `konfirmasi_data_id_periode_update_fkey` | Ya | `public.konfirmasi_data` | `id_periode_update` | `public.periode_update` | `id_periode_update` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `konfirmasi_data_id_siswa_fkey` | Ya | `public.konfirmasi_data` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `kota_id_provinsi_fkey` | Ya | `public.kota` | `id_provinsi` | `public.provinsi` | `id_provinsi` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `log_audit_id_pengguna_pengubah_fkey` | Ya | `public.log_audit` | `id_pengguna_pengubah` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `log_login_id_pengguna_fkey` | Ya | `public.log_login` | `id_pengguna` | `public.pengguna` | `id_pengguna` | SET NULL | NOT VALID | SIMPLE | 1 | NOT VALID dipertahankan: orphan masih ada; tidak ada row diubah. |
| `mutasi_siswa_id_pengaju_fkey` | Ya | `public.mutasi_siswa` | `id_pengaju` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `mutasi_siswa_id_penyetuju_fkey` | Ya | `public.mutasi_siswa` | `id_penyetuju` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `mutasi_siswa_id_sekolah_tujuan_fkey` | Ya | `public.mutasi_siswa` | `id_sekolah_tujuan` | `public.sekolah` | `id_sekolah` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `mutasi_siswa_id_siswa_fkey` | Ya | `public.mutasi_siswa` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `nilai_siswa_id_anggota_fkey` | Ya | `public.nilai_siswa` | `id_anggota` | `public.keanggotaan_kelas` | `id_keanggotaan` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `nilai_siswa_id_komponen_fkey` | Ya | `public.nilai_siswa` | `id_komponen` | `public.komponen_penilaian` | `id_komponen` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `nilai_siswa_id_mapel_fkey` | Ya | `public.nilai_siswa` | `id_mapel` | `public.mata_pelajaran` | `id_mata_pelajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `nilai_siswa_id_penginput_fkey` | Ya | `public.nilai_siswa` | `id_penginput` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `nilai_siswa_id_semester_fkey` | Ya | `public.nilai_siswa` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `notifikasi_id_pengguna_fkey` | Ya | `public.notifikasi` | `id_pengguna` | `public.pengguna` | `id_pengguna` | CASCADE | NOT VALID | SIMPLE | 1 | NOT VALID dipertahankan: orphan masih ada; tidak ada row diubah. |
| `orang_tua_wali_id_pekerjaan_fkey` | Ya | `public.orang_tua_wali` | `id_pekerjaan` | `public.pekerjaan` | `id_pekerjaan` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `orang_tua_wali_id_siswa_fkey` | Ya | `public.orang_tua_wali` | `id_siswa` | `public.siswa` | `id_siswa` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pendaftaran_ppdb_id_agama_foreign` | Tidak | `public.pendaftaran_ppdb` | `id_agama` | `public.agama` | `id_agama` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pendaftaran_ppdb_id_kelas_diterima_foreign` | Tidak | `public.pendaftaran_ppdb` | `id_kelas_diterima` | `public.kelas` | `id_kelas` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pendaftaran_ppdb_id_pengguna_pemeriksa_fkey` | Ya | `public.pendaftaran_ppdb` | `id_pengguna_pemeriksa` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengajuan_perubahan_id_alasan_pengembalian_foreign` | Tidak | `public.pengajuan_perubahan` | `id_alasan_pengembalian` | `public.ref_alasan_pengembalian` | `id_alasan` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengajuan_perubahan_id_pemeriksa_fkey` | Ya | `public.pengajuan_perubahan` | `id_pemeriksa` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengajuan_perubahan_id_periode_update_fkey` | Ya | `public.pengajuan_perubahan` | `id_periode_update` | `public.periode_update` | `id_periode_update` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengajuan_perubahan_id_siswa_fkey` | Ya | `public.pengajuan_perubahan` | `id_siswa` | `public.siswa` | `id_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengaturan_sistem_id_pengguna_pengubah_fkey` | Ya | `public.pengaturan_sistem` | `id_pengguna_pengubah` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `pengguna_id_role_fkey` | Ya | `public.pengguna` | `id_role` | `public.role` | `id_role` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `penugasan_wali_kelas_id_guru_fkey` | Ya | `public.penugasan_wali_kelas` | `id_guru` | `public.guru` | `id_guru` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `penugasan_wali_kelas_id_kelas_fkey` | Ya | `public.penugasan_wali_kelas` | `id_kelas` | `public.kelas` | `id_kelas` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `penugasan_wali_kelas_id_semester_fkey` | Ya | `public.penugasan_wali_kelas` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `periode_update_dibuat_oleh_fkey` | Ya | `public.periode_update` | `dibuat_oleh` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `periode_update_id_tahun_ajaran_fkey` | Ya | `public.periode_update` | `id_tahun_ajaran` | `public.tahun_ajaran` | `id_tahun_ajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_alamat_id_pendaftaran_foreign` | Tidak | `public.ppdb_alamat` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_beasiswa_id_pendaftaran_foreign` | Tidak | `public.ppdb_beasiswa` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_keluar_id_pendaftaran_foreign` | Tidak | `public.ppdb_keluar` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_orang_tua_id_pekerjaan_foreign` | Tidak | `public.ppdb_orang_tua` | `id_pekerjaan` | `public.pekerjaan` | `id_pekerjaan` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_orang_tua_id_pendaftaran_foreign` | Tidak | `public.ppdb_orang_tua` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_periodik_id_pendaftaran_foreign` | Tidak | `public.ppdb_periodik` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ppdb_prestasi_id_pendaftaran_foreign` | Tidak | `public.ppdb_prestasi` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `rapor_id_anggota_fkey` | Ya | `public.rapor` | `id_anggota` | `public.keanggotaan_kelas` | `id_keanggotaan` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `rapor_id_penyusun_fkey` | Ya | `public.rapor` | `id_penyusun` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `rapor_id_semester_fkey` | Ya | `public.rapor` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ref_checklist_item_id_jenis_dokumen_fkey` | Ya | `public.ref_checklist_item` | `id_jenis_dokumen` | `public.jenis_dokumen` | `id_jenis_dokumen` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `ref_field_data_id_jenis_dokumen_bukti_fkey` | Ya | `public.ref_field_data` | `id_jenis_dokumen_bukti` | `public.jenis_dokumen` | `id_jenis_dokumen` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `role_hak_akses_id_hak_akses_fkey` | Ya | `public.role_hak_akses` | `id_hak_akses` | `public.hak_akses` | `id_hak_akses` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `role_hak_akses_id_role_fkey` | Ya | `public.role_hak_akses` | `id_role` | `public.role` | `id_role` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `semester_id_tahun_ajaran_fkey` | Ya | `public.semester` | `id_tahun_ajaran` | `public.tahun_ajaran` | `id_tahun_ajaran` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `sesi_absensi_id_guru_pencatat_fkey` | Ya | `public.sesi_absensi` | `id_guru_pencatat` | `public.guru` | `id_guru` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `sesi_absensi_id_jadwal_fkey` | Ya | `public.sesi_absensi` | `id_jadwal` | `public.jadwal_pelajaran` | `id_jadwal` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `sesi_absensi_id_kelas_foreign` | Tidak | `public.sesi_absensi` | `id_kelas` | `public.kelas` | `id_kelas` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `sesi_absensi_id_semester_foreign` | Tidak | `public.sesi_absensi` | `id_semester` | `public.semester` | `id_semester` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `siswa_id_agama_fkey` | Ya | `public.siswa` | `id_agama` | `public.agama` | `id_agama` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `siswa_id_pengguna_fkey` | Ya | `public.siswa` | `id_pengguna` | `public.pengguna` | `id_pengguna` | SET NULL | NOT VALID | SIMPLE | 1 | NOT VALID dipertahankan: orphan masih ada; tidak ada row diubah. |
| `siswa_id_sekolah_asal_fkey` | Ya | `public.siswa` | `id_sekolah_asal` | `public.sekolah` | `id_sekolah` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `siswa_id_status_siswa_fkey` | Ya | `public.siswa` | `id_status_siswa` | `public.status_siswa` | `id_status_siswa` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `staf_id_pengguna_fkey` | Ya | `public.staf` | `id_pengguna` | `public.pengguna` | `id_pengguna` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `fk_tingkat_jenjang` | Ya | `public.tingkat` | `id_jenjang` | `public.jenjang` | `id_jenjang` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `verifikasi_pendaftaran_id_alasan_fkey` | Ya | `public.verifikasi_pendaftaran` | `id_alasan` | `public.ref_alasan_pengembalian` | `id_alasan` | SET NULL | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `verifikasi_pendaftaran_id_pendaftaran_fkey` | Ya | `public.verifikasi_pendaftaran` | `id_pendaftaran` | `public.pendaftaran_ppdb` | `id_pendaftaran` | CASCADE | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
| `verifikasi_pendaftaran_id_petugas_fkey` | Ya | `public.verifikasi_pendaftaran` | `id_petugas` | `public.pengguna` | `id_pengguna` | RESTRICT | VALID | SIMPLE | 0 | VALID; anti-join tidak menemukan orphan. |
<!-- END GENERATED FK DETAILS -->




