# Laporan Fase 2 — Sistem Data Siswa & PPDB

**Pembaruan terakhir:** 17 November 2026
**Database sasaran:** `msbd_tubes_kel7`, PostgreSQL 18.6
**Batasan:** Database testing, staging, dan klon tidak digunakan.

## Ringkasan eksekutif

Skema utama, hak akses PostgreSQL, trigger, fungsi, prosedur, dan integritas foreign key diperiksa
langsung pada database `msbd_tubes_kel7`. Setelah grant runtime diperbarui, verifier privilege
dijalankan ulang pada koneksi DBeaver ke target dan menghasilkan 235 PASS/0 FAIL. Seluruh 13 view
dapat dibaca sebagai superuser, enam function aplikasi menghasilkan nilai sampel, dan keempat
procedure diuji dalam transaksi yang di-`ROLLBACK`.

Verifikasi terbaru: 156 test, 154 passed, 2 skipped, 3 incomplete, 0 failure/error, 867 assertions,
dan 1 warning; route list menampilkan 128 route, `view:cache` dan `optimize:clear` berhasil, serta
endpoint `/masuk` memberi HTTP 200. Database memiliki 29 trigger non-internal yang semuanya enabled
dan 1147 entri ACL. Satu orphan pada log login sengaja dipertahankan sebagai pengecualian audit.
Backup custom-format terbaru diverifikasi sebelum seeding. Seeder kemudian sukses dalam transaksi
menggunakan koneksi pemeliharaan sementara; tiga akun demo terhitung masing-masing satu dan akun
siswa terhubung ke satu record siswa demo. Koneksi Laravel sudah dikembalikan dan diverifikasi
sebagai `app_login`. Percobaan autentikasi aplikasi berhasil untuk siswa, TU, dan kepsek; smoke test
wali kelas dan seluruh halaman/aksi empat role belum lengkap.

Perubahan lanjutan pada `AuditCatat` meniadakan INSERT audit langsung saat memakai PostgreSQL agar
tidak melanggar privilege atau menggandakan audit trigger. DDL audit tambahan sudah diterapkan pada
database utama; katalog terkini menunjukkan 29 trigger aktif. Backup final setelah seeding masih
belum dibuat. Backup terbaru dari pemilik proyek memiliki 719 entri TOC dan SHA-256 cocok; backup
tersebut dibuat sebelum seeding. Seeder lokal-only untuk akun `demo_siswa`, `demo_tu`, dan
`demo_kepsek` berhasil dijalankan dalam transaksi lewat koneksi pemeliharaan lokal. Tidak ada
password yang dicatat atau ditampilkan. Gerbang D **GAGAL** karena backup final pascaseeding,
smoke test empat role lengkap, serta alur end-to-end belum dibuktikan.

## Status gerbang

| Gerbang | Status | Bukti dan alasan |
|---|---|---|
| A — DB utama | **LULUS** | Backup custom-format terbaru berhasil diverifikasi dengan `pg_restore -l` dan SHA-256 cocok; katalog mencatat 72 tabel, 72 PK, 96 FK, 13 view, 6 function, dan 4 procedure; verifier privilege pada target menghasilkan 235 PASS/0 FAIL; `search_path` `app_login` benar. Backup tersebut dibuat sebelum seeding; backup pascaseeding tetap wajib untuk butir D. |
| B — Integritas | **LULUS DENGAN PENGECUALIAN** | 95 FK `VALID`; `log_login_id_pengguna_fkey` tetap `NOT VALID` dengan satu orphan yang dipertahankan karena log immutable. Pengecualian didokumentasikan; query katalog terkini mengonfirmasi hanya FK tersebut yang belum tervalidasi. |
| C — Aplikasi | **GAGAL (parsial)** | 156 test, 154 passed, 2 skipped, 3 incomplete, 0 failure/error, 867 assertions, 1 warning. `route:list` menampilkan 128 route; `view:cache` dan `optimize:clear` berhasil; `/masuk` memberi HTTP 200; autentikasi berhasil untuk tiga akun demo. Smoke test wali kelas dan akses halaman/aksi untuk empat role belum selesai. |
| D — Penerimaan akhir | **GAGAL** | Backup terbaru diverifikasi sebelum seeding, tetapi backup final pascaseeding belum dibuat. Autentikasi tiga akun demo teruji; wali kelas dan alur end-to-end seluruh fitur belum diuji. Orphan audit satu baris dan selisih jumlah baris dari baseline masih menjadi pengecualian/temuan terdokumentasi. |

## Keadaan database dan bukti

### Objek katalog

Hasil `database/sql/verify/00_hitung_objek.sql` pada database utama:

| Objek | Aktual |
|---|---:|
| Tabel | 72 |
| Primary key | 72 |
| Foreign key | 96 |
| View | 13 |
| Function non-trigger | 6 |
| Trigger function | 5 |
| Trigger non-internal | 29 |
| Procedure | 4 |
| UNIQUE / CHECK | 56 / 25 |
| Entri ACL | 1147 |
| Role cluster / role Fase 1 | 25 / 6 |

Semua 29 trigger non-internal berstatus enabled (`tgenabled='O'`). Enam role Fase 1 tersedia; hanya `app_login`
yang dapat login, dan role database bernama `guru` tidak ada.

### Jumlah baris per tabel sebelum seeding akun demo

Penghitungan langsung `count(*)` dinamis per tabel pada 17 November 2026 sebelum seeding akun demo
menghasilkan 1227 baris di 72 tabel. Total pascaseeding dan autentikasi belum dihitung ulang, sehingga
angka tabel berikut adalah baseline sebelum akun demo dibuat:

| Tabel | Baris | Tabel | Baris |
|---|---:|---|---:|
| agama | 7 | alamat_siswa | 7 |
| cache | 1 | cache_locks | 0 |
| catatan_wali_kelas | 4 | cetak_rapor | 0 |
| detail_absensi | 4 | detail_pengajuan | 5 |
| detail_rapor | 4 | detail_verifikasi | 0 |
| dokumen_siswa | 4 | ekspor_laporan | 0 |
| failed_jobs | 0 | guru | 5 |
| hak_akses | 9 | izin_siswa | 0 |
| jadwal_pelajaran | 48 | jenis_dokumen | 6 |
| jenjang | 1 | job_batches | 0 |
| jobs | 0 | jurusan | 2 |
| keanggotaan_kelas | 9 | kecamatan | 0 |
| kelas | 120 | kelurahan | 0 |
| komponen_penilaian | 3 | konfirmasi_data | 1 |
| kota | 0 | log_audit | 510 |
| log_login | 198 | mata_pelajaran | 31 |
| migrations | 24 | mutasi_siswa | 2 |
| nilai_siswa | 12 | notifikasi | 10 |
| orang_tua_wali | 15 | password_reset_tokens | 0 |
| pekerjaan | 13 | pendaftaran_ppdb | 6 |
| pengajuan_perubahan | 5 | pengaturan_sistem | 10 |
| pengguna | 11 | penugasan_wali_kelas | 5 |
| periode_update | 1 | personal_access_tokens | 0 |
| ppdb_alamat | 4 | ppdb_beasiswa | 0 |
| ppdb_keluar | 0 | ppdb_orang_tua | 12 |
| ppdb_periodik | 5 | ppdb_prestasi | 0 |
| provinsi | 0 | rapor | 4 |
| ref_alasan_pengembalian | 5 | ref_checklist_item | 11 |
| ref_field_data | 29 | role | 4 |
| role_hak_akses | 14 | sekolah | 5 |
| semester | 2 | sesi_absensi | 1 |
| sessions | 2 | siswa | 9 |
| skala_predikat | 4 | staf | 1 |
| status_kehadiran | 4 | status_siswa | 10 |
| tahun_ajaran | 1 | tingkat | 3 |
| users | 0 | verifikasi_pendaftaran | 4 |

Baseline yang dicatat pada Fase 1 adalah 1233 baris (selisih -6 sebelum seeding). Catatan pengukuran interim yang
lebih lama menyebut 1239; kedua catatan tersebut tidak dapat direkonsiliasi dari artefak yang
tersedia. Tidak ada penyebab yang disimpulkan tanpa bukti. `log_audit` juga bertambah satu baris
untuk mencatat penonaktifan akun tanpa penugasan yang dilakukan pada Fase 2.

### Privilege, role, dan `search_path`

`database/sql/verify/01_uji_privilege.sql` dijalankan ulang pada target `msbd_tubes_kel7` melalui
koneksi DBeaver dengan guard target aktif setelah grant runtime terbaru:
**235 PASS, 0 FAIL**. Ringkasan ini terlihat pada result grid verifier. Run terbaru dilanjutkan
dengan audit foreign key baca-saja; query katalog mengonfirmasi 96 FK, 95 `VALID`, dan satu
`NOT VALID` (`log_login_id_pengguna_fkey`).

`app_login` mempunyai `statement_timeout=30s`, `idle_in_transaction_session_timeout=30s`, dan
konfigurasi per database `search_path=pg_catalog, public, pg_temp`. Koneksi Laravel saat ini
terverifikasi langsung ke `msbd_tubes_kel7` sebagai `app_login` pada PostgreSQL 18.6; query runtime
menghasilkan `search_path=pg_catalog, public, pg_temp` dan kedua timeout `30s`. Sebelumnya konfigurasi
Laravel memaksa `search_path=public`; `config/database.php` kini diselaraskan dengan pengaturan role.
Grant runtime setelah pemeriksaan awal menambahkan hak yang dibutuhkan pada `sessions`, `cache`,
`cache_locks`, `jobs`, `job_batches`, `failed_jobs`, token, sequence terkait, kolom metadata login,
serta `SELECT` pada `kelas`, `pengaturan_sistem`, `semester`, dan `tahun_ajaran`. Sesudah grant,
route publik `/`, `/masuk`, `/ppdb`, dan `/ppdb/status` merespons HTTP 200. Belum ada bukti login
terautentikasi empat role. `app_login` belum diberi `INSERT` langsung ke `log_audit`; helper
`AuditCatat` kini sengaja tidak melakukan INSERT langsung pada PostgreSQL. Trigger audit yang ada
mencakup 25 tabel setelah sembilan trigger tambahan diterapkan dari `database/sql/ddl/05_trigger.sql`.
Katalog terkini membuktikan 29 trigger non-internal enabled. Keberhasilan seluruh aksi tulis
aplikasi tetap belum dibuktikan melalui smoke test per role. Grant `INSERT` luas tidak diterapkan
karena dapat membuka pemalsuan audit.

Permintaan password `0` ditolak. Lima role grup tetap `NOLOGIN`. Password `app_login` tidak diubah
atau dicatat dalam laporan. Runtime Laravel kini memakai `app_login`, bukan `postgres` superuser;
RLS belum diterapkan.

### Integritas orphan

`database/sql/verify/02_orphan_audit.sql` terakhir menemukan satu orphan pada
`log_login_id_pengguna_fkey`; constraint ini tetap `NOT VALID`. Baris log dipertahankan, bukan
dihapus atau diubah, demi sifat append-only dan jejak audit. Orphan sebelumnya pada `siswa` dan
`notifikasi` telah ditangani sebelum audit akhir dan dua FK terkait telah divalidasi. Output orphan
tidak menyertakan nilai identitas personal.

### Fungsi, view, trigger, procedure, transaksi

- Semua **13 view** berhasil di-`SELECT ... LIMIT 1` sebagai superuser.
- Enam function aplikasi dipanggil dengan argumen dari data nyata/sampel: jumlah siswa kelas 4,
  kehadiran 100.00%, konfirmasi 11.11%, predikat nilai 88 = A, rata-rata sampel 75.6667, dan
  semester aktif 1.
- Empat procedure berhasil diuji memakai argumen valid dalam satu transaksi lalu di-`ROLLBACK`:
  `sp_setujui_pengajuan`, `sp_finalisasi_rapor`, `sp_naik_kelas`, dan `sp_proses_mutasi`. Fixture
  pengajuan dibuat sementara dari data yang ada, hasil prosedur dan audit diperiksa, lalu seluruh
  perubahan tabel dibatalkan. Sequence dapat maju pada rollback; pemeriksaan pasca-uji menemukan
  62 sequence dan 0 sequence tertinggal.
- Verifier menguji audit trigger, penolakan UPDATE/DELETE pada log, dan status enabled. Tidak ada
  data uji yang tertinggal.
- TCL `01..03` berjalan tanpa error. Data demo Fase 1 yang menjadi prasyarat skrip tidak ada, sehingga
  contoh commit/rollback tersebut tidak melakukan perubahan data bermakna. TCL `04` bersifat
  demonstrasi baca-saja; `05` tidak dijalankan agar tidak memicu deadlock pada database utama.

## Perubahan database yang dikomitkan

Semua perubahan database utama dilakukan dalam transaksi:

1. Memperbaiki `search_path` role `app_login` untuk `msbd_tubes_kel7`.
2. Memulihkan relasi `siswa.id_pengguna` yang yatim menjadi `NULL` karena tidak ditemukan akun
   sah, menghapus satu notifikasi yatim, lalu memvalidasi kedua FK terkait.
3. Mempertahankan orphan `log_login` sebagai pengecualian audit dan membiarkan constraint-nya
   `NOT VALID`.
4. Menonaktifkan satu akun Wali Kelas aktif tanpa penugasan semester aktif; mencabut sesi/token
   yang cocok (0 sesi dan 0 token ditemukan) dan memastikan trigger menulis satu baris audit.
5. Menguji verifier, TCL, dan procedure dengan transaksi yang di-rollback.
6. Menambahkan grant runtime untuk `app_login` pada tabel infrastruktur dan metadata yang diperlukan
   PPDB; sumber matriks dan SQL hasil generator diperbarui untuk empat tabel metadata.

Migrasi penghapusan role `guru` **tidak dijalankan ulang** pada database utama. Sumber
`database/sql/dcl/90_hapus_role_guru.sql` diperbaiki agar, untuk penerapan di lingkungan baru,
akun tanpa penugasan semester aktif dinonaktifkan dan sesi/token terkait dicabut.

### Backup yang diberikan pemilik proyek

Backup terbaru yang diberikan pemilik proyek diverifikasi secara lokal. Snapshot ini dibuat sebelum
seeder menambahkan akun demo, sehingga bukan backup final pascaseeding:

- File: `C:\Users\Adrian Varerus\Documents\dump-backup\msbd_tubes_kel7_backup_3.dump`
- Format `CUSTOM`; arsip menyebut database `msbd_tubes_kel7` dan PostgreSQL sumber 18.6; dibuat
  17 November 2026 pukul 03:16:07.
- `pg_restore -l` exit code 0; header menyatakan 719 entri TOC. Output berisi 729 baris total
  termasuk header dan separator.
- SHA-256 aktual cocok dengan nilai yang diberikan:
  `ADF07F02A7F92DF035A0658AE2366CA7B4AAB9765CD17DB718CD63AA64712CD0`.

Dump sebelumnya yang diberikan pemilik proyek memiliki selisih 705 entri daftar dan 710 pada
header; hasil itu tidak digunakan sebagai backup terbaru.

- File: `C:\Users\Adrian Varerus\Documents\dump-backup\msbd_tubes_kel7_backup.dump`
- Format: `CUSTOM`; database arsip `msbd_tubes_kel7`; dibuat 17 November 2026 pukul 01:54:37; ukuran 294185 byte.
- `pg_restore -l` exit code 0; daftar berisi 705 baris entri numerik, sedangkan header menyatakan `TOC Entries: 710` (selisih 5 belum terjelaskan).
- SHA-256 aktual cocok dengan nilai yang diberikan: `1F627826028EF28B464625C6606ECA81FFC945B5252B3603D58AF831F85BADB5`.

Percobaan backup sebelumnya memakai `app_login` dan gagal dengan `permission denied for table
alamat_siswa`; dump parsial sudah dihapus. Percobaan backup final melalui `pg_dump -w` pada shell
gagal dengan `fe_sendauth: no password supplied`; dump parsial dihapus dan rahasia tidak dicetak.
Backup final pascaseeding masih perlu dibuat melalui koneksi admin DBeaver atau mekanisme
autentikasi lokal yang aman.

Arsip lama yang dibuat sebelum grant runtime terbaru tetap tersimpan sebagai snapshot historis:

- `C:\Users\Adrian Varerus\Documents\MSBD-KEL7-Backups\msbd_tubes_kel7_fase2_final_20261117_000809.dump` — 595 entri TOC, SHA-256 `8A0C842B6127EF3B11DB070160C5F75AB1E20B2B1738130409D7C7903225EF06`.
- `C:\Users\Adrian Varerus\Documents\dump-backup\msbd_tubes_kel7.dump` — 698 entri TOC, SHA-256 `2CCF66F0673605D172FF95871ACD3DD16EFD78264B72CCBB70417A9D5170698A`.

## Perubahan aplikasi dan test

- `app/Services/PengajuanPerubahanService.php`: keputusan pengajuan kini hanya menerima petugas
  TU/Wali Kelas; Wali Kelas dibatasi ke siswa yang benar-benar berada di kelas asuh semester aktif.
  Tes regresi membuktikan keputusan untuk kelas lain ditolak tanpa mengubah status pengajuan.
- `app/Http/Controllers/PageController.php`: preview dokumen TU hanya mengambil file dari disk
  `private`; file public-only menghasilkan 404.
- `database/sql/dcl/90_hapus_role_guru.sql`: migrasi menghindari promosi aktif akun tanpa penugasan
  semester aktif serta membatasi pencabutan sesi/token ke akun yang terkena.
- `tests/Feature/Nomor3PengajuanPerubahanDataSiswaTest.php` dan
  `tests/Feature/UploadBerkasAuditFeatureTest.php`: test untuk otorisasi wali dan preview private.
- `phpunit.xml` dan `tests/TestCase.php`: suite dipaksa menggunakan SQLite `:memory:` dan fail-fast
  jika koneksi test bukan SQLite memory. `php artisan config:show database.default` dengan
  `APP_ENV=testing` di luar runner menampilkan `pgsql`; PHPUnit memakai variabel paksa dari
  `phpunit.xml`, dan guard pada `TestCase` memastikan driver/database aktual sebelum tes berjalan.
- `config/database.php`: `search_path` koneksi PostgreSQL diselaraskan menjadi
  `pg_catalog, public, pg_temp`; koneksi Laravel live sudah diverifikasi memakai urutan tersebut.
- `database/sql/dcl/MATRIKS_PRIVILEGE.csv` dan `database/sql/dcl/02_privileges.sql`: grant `SELECT`
  untuk `app_login` pada `kelas`, `pengaturan_sistem`, `semester`, dan `tahun_ajaran`; SQL privilege
  dibangkitkan ulang dari matriks.
- `.env.example`: `APP_DEBUG=false`. `.env` lokal demo disetel `APP_DEBUG=false` dan diverifikasi
  lewat `php artisan config:show app.debug`; file tersebut tetap di-ignore Git dan nilainya tidak
  memengaruhi berkas rahasia lain.
- `app/Support/AuditCatat.php`, `database/sql/ddl/05_trigger.sql`,
  `database/migrations/2026_11_17_020754_extend_audit_triggers_for_manual_audits.php`, dan
  `database/sql/verify/01_uji_privilege.sql`: meniadakan INSERT audit manual pada PostgreSQL,
  menambahkan sembilan trigger untuk tabel yang sebelumnya hanya memakai helper, serta memperluas
  verifikasi trigger audit. DDL diterapkan langsung melalui DBeaver; migrasi Laravel belum
  dijalankan pada database utama.
- `database/seeders/DemoRoleAccountsSeeder.php`, `config/demo.php`, dan
  `tests/Feature/DemoRoleAccountsSeederTest.php`: membuat tiga akun demo lokal secara idempoten,
  menghubungkan akun siswa ke satu data siswa demo, menyimpan sandi dengan Hash, dan menolak
  environment non-local atau koneksi PostgreSQL tanpa hak tulis. Seeder dijalankan sukses setelah
  backup terverifikasi dengan koneksi pemeliharaan lokal; query hitung sesudahnya menghasilkan satu
  akun untuk tiap role demo dan satu record siswa yang tertaut. Password tetap hanya di `.env` lokal
  yang di-ignore Git.

Hasil terbaru Pest: **156 test, 154 passed, 2 skipped, 3 incomplete, 0 failed, 0 errors, 867
assertions, 1 warning**.
Dua skip adalah pemeriksaan skema PostgreSQL-only. Tiga incomplete adalah prosedur PostgreSQL pada test
SQLite, URL storage publik yang memerlukan symlink/web server nyata, dan simulasi atomic rollback
file+database yang belum tersedia. Prosedur PostgreSQL tetap diuji terpisah di database utama dalam
transaksi rollback.

Selisih historis 151 hasil runner vs 120 node JUnit tidak dapat dijelaskan karena artefak JUnit lama
tidak tersedia. Run terbaru menghasilkan 156 test pada runner; JUnit terbaru belum diperiksa.

`route:list --except-vendor` berhasil (128 route), `view:cache` berhasil, lalu `optimize:clear`
berhasil pada verifikasi terbaru. HTTP smoke check ke `/masuk` mengembalikan 200. Autentikasi
berhasil untuk `demo_siswa`, `demo_tu`, dan `demo_kepsek` menggunakan sandi konfigurasi tanpa
menampilkan nilainya. Koneksi aktif sesudah seeding diverifikasi kembali sebagai `app_login` pada
`msbd_tubes_kel7`. Server sementara dihentikan setelah smoke check. Pest kembali dijalankan:
156 test, 154 passed, 2 skipped, 3 incomplete, 0 failed/error, 867 assertions, 1 warning. Pint
`--dirty --format agent` berhasil. Satu warning Pest tidak menyertakan rincian pada output runner.

## Temuan keamanan dan risiko sisa

**Ditangani:** keputusan pengajuan wali lintas kelas, preview fallback public pada route TU, akun
wali aktif tanpa penugasan, konfigurasi `search_path` berkoma, serta isolasi database test.

**Belum ditangani/terverifikasi:**

- Laravel kini terkoneksi sebagai `app_login` (bukan superuser). Grant runtime terbatas telah
  diperluas untuk infrastruktur dan metadata PPDB, tetapi seluruh aksi tulis belum diverifikasi.
  Jalur audit PostgreSQL mengandalkan trigger; 29 trigger terpasang dan enabled. Tier 2
  belum diaktifkan.
- RLS belum ada; isolasi baris masih bergantung pada authorization aplikasi.
- Autentikasi akun demo siswa, TU, dan kepsek teruji; wali kelas dan smoke test halaman/aksi empat
  role belum lengkap. Seeder menggunakan koneksi pemeliharaan sementara setelah backup baru
  terverifikasi; Laravel sudah dikembalikan ke `app_login`. Tidak ada grant tulis permanen baru untuk
  keperluan seeding.
- Satu orphan pada log login dipertahankan untuk integritas audit.
- Selisih jumlah baris dari baseline belum terjelaskan.
- `APP_DEBUG=false` sudah aktif pada `.env` lokal demo dan contoh konfigurasi; verifikasi ulang
  nilai ini saat menyiapkan environment lain.
- Backup terbaru memiliki 719 entri TOC dan hash cocok; backup final pascaseeding masih perlu dibuat.
  Arsip lebih lama memiliki selisih 705 entri daftar dan 710 pada header.
- `PageController` dan `AksiFormController` tetap memiliki utang teknis berupa controller besar.
- Perubahan repository sebelumnya sudah staged sebelum pekerjaan ini. Tidak ada commit yang dibuat;
  `.env`, `backup.env`, dan dump tidak ditemukan sebagai file tracked.

## Pemetaan ke laporan MSBD

- **Bab III.5:** tabel, PK/FK, view — angka katalog dan exception FK di atas.
- **Bab III.6:** trigger, function, procedure — uji function, trigger, dan procedure rollback.
- **Bab III.7:** user, privilege, keamanan, transaksi — matriks privilege, role login, `search_path`,
  append-only audit, dan demo TCL.
- **Bab III.9:** sequence, session, orphan, dan batasan RLS.
- **Bab IV.2:** implementasi SQL — file `dcl`, `verify`, dan pemeliharaan orphan.
- **Bab IV.4:** pengujian — Pest SQLite memory, verifikasi PostgreSQL, hasil smoke test yang belum
  dijalankan, serta alasan skip/incomplete.
