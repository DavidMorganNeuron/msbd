# Panduan Eksekusi SQL MSBD

## Status Fase 2 (pembaruan 17 November 2026)

Reset yang diminta telah selesai pada database utama `msbd_tubes_kel7` (PostgreSQL 18.6) setelah
backup pra-reset diverifikasi. `php artisan migrate:fresh --seed` berhasil; katalog berisi 72 tabel,
13 view, 27 migrasi, dan 29 trigger aktif. Empat akun demo aktif dan satu record siswa tertaut
dibuat ulang; seluruh data aplikasi sebelum reset telah terhapus sesuai perilaku `migrate:fresh`.
Trigger audit mencatat 251 baris selama proses seeding.

Role PostgreSQL dipulihkan. `app_login` dan empat role bisnis dapat login sebagai non-superuser;
`app_owner` tetap `NOLOGIN`. Laravel kembali terhubung sebagai `app_login`, dan perintah `migrate`
berjalan setelah izin ledger migrasi diperbaiki. Verifier privilege menghasilkan 240 PASS/0 FAIL;
empat login bisnis diuji langsung. Full smoke test UI/aksi empat role belum diulang setelah reset,
jadi Fase 2 belum dinyatakan diterima penuh. Baca [FASE2_LAPORAN.md](./FASE2_LAPORAN.md) untuk
bukti, batasan, dan status tiap gerbang.

Backup sebelum reset dan backup pascaseeding berada di `%LOCALAPPDATA%\\MSBD-KEL7-Backups`;
keduanya lolos pemeriksaan TOC. Hash keduanya dicatat pada progres eksekusi lokal.

## Laravel fresh-install/reset

`database/schema/pgsql-schema.sql` adalah baseline skema untuk `php artisan migrate:fresh --seed`.
Dump tersebut memuat struktur objek PostgreSQL dan ledger migrasi, bukan data aplikasi. Perintah
fresh menghapus seluruh data tabel sebelum menjalankan seeder. Jalankan pada database lokal yang
benar setelah backup diverifikasi. Role PostgreSQL bersifat cluster-wide dan privilege objek tidak
termasuk dalam schema dump; setelah seeding, jalankan `dcl/01_roles.sql`,
`dcl/04_login_roles_dev.sql`, `dcl/02_privileges.sql`, lalu `dcl/03_hardening.sql` sebagai admin.
`02_privileges.sql` memberi `app_login` izin ledger migrasi agar perintah Artisan berikutnya tetap
bisa membaca/menulis tabel `migrations`. Koneksi Laravel memakai `search_path=public`; PostgreSQL
tetap memprioritaskan `pg_catalog` secara implisit. Script role dev menjaga `app_login` sebagai
`NOSUPERUSER`. Jalur fresh diuji ulang pada clone disposable dengan `app_login` non-superuser.

## Riwayat baseline Fase 1

Saat baseline Fase 1 dibuat, paket telah diterapkan secara atomik pada database klon bertanda
`msbd_latihan_utama` (`FASE1_TRAINING_CLONE`). Pada klon, katalog sesudah berisi 72 tabel, 72 PK,
96 FK, 13 view, 6 stored function non-trigger, 5 trigger function, 20 trigger, dan 4 procedure.
Verifier privilege menghasilkan 173 PASS/0 FAIL. Audit FK menemukan 3 FK `NOT VALID` dengan total
3 baris orphan. Test suite menghasilkan 40 passed, 43 failed, 68 errors, 2 incomplete, exit code 2;
hasilnya **tidak lulus**.

Catatan lama bahwa paket belum diterapkan pada `msbd_tubes_kel7` adalah snapshot sebelum Fase 2,
bukan status saat ini. Role PostgreSQL bersifat cluster-wide; enam role Fase 1 masih terlihat pada
cluster dan jumlah keseluruhan role adalah 25. Pengaturan per-database `app_login` kini diverifikasi
untuk `msbd_tubes_kel7`.

Rincian lengkap, termasuk keluaran verifier tanpa pemotongan, akar masalah test, seluruh FK, dan
langkah dari nol ada di [FASE1_LAPORAN.md](./FASE1_LAPORAN.md). Tabel orphan lengkap ada di
[ORPHAN_REPORT.md](./ORPHAN_REPORT.md).

## Menjalankan pada klon baru

Semua perintah di bawah ini untuk lingkungan uji disposable, bukan database utama. Klon
`msbd_latihan_utama` sudah ada pada lingkungan run ini; **jangan membuat ulang, menimpa, atau
menghapusnya tanpa persetujuan eksplisit**. Runner menolak database latihan tanpa marker yang benar.

1. Siapkan backup custom-format pre-package dari sumber dan verifikasi TOC:

   ```powershell
   $preDump = Join-Path $env:TEMP ('fase1_primary_pre_{0}.dump' -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
   pg_dump -w --format=custom --no-owner --no-acl -f $preDump -d msbd_tubes_kel7
   if ($LASTEXITCODE -ne 0) { throw 'pg_dump gagal' }
   $toc = pg_restore -l $preDump
   if ($LASTEXITCODE -ne 0 -or @($toc).Count -lt 2) { throw 'Backup tidak lolos pg_restore -l' }
   ```

   `pg_dump` dan tool PostgreSQL lain perlu kredensial yang sudah dimuat secara aman ke sesi lokal.
   Jangan menampilkan nilai password ke terminal atau menyimpannya dalam repository.

2. Pada lingkungan baru, pulihkan dump ke database latihan kosong dan pasang marker:

   ```powershell
   createdb -w msbd_latihan_utama
   if ($LASTEXITCODE -ne 0) { throw 'Database klon tidak dapat dibuat; jangan menimpa database yang sudah ada' }
   pg_restore -w --exit-on-error --no-owner --no-acl -d msbd_latihan_utama $preDump
   if ($LASTEXITCODE -ne 0) { throw 'Restore klon gagal' }
   psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "COMMENT ON DATABASE msbd_latihan_utama IS 'FASE1_TRAINING_CLONE';"
   if ($LASTEXITCODE -ne 0) { throw 'Marker klon gagal dipasang' }
   ```

3. Jalankan dry-run paket, periksa seluruh output, lalu apply ke klon saja:

   ```powershell
   .\database\sql\verify\jalankan_fase1.ps1 -Database msbd_latihan_utama -AllowTrainingClone -DryRun
   .\database\sql\verify\jalankan_fase1.ps1 -Database msbd_latihan_utama -AllowTrainingClone
   ```

   Runner memverifikasi marker, membuat backup klon di `%TEMP%`, memvalidasi TOC, dan memakai satu
   transaksi untuk paket. Paket menyertakan `dcl/90_hapus_role_guru.sql`, yang memindahkan akun
   aplikasi dari role `guru` dan menghapus seluruh baris tabel `sessions` jika tabel itu ada.
   Tinjau efeknya pada data klon sebelum apply.

4. Verifikasi hasil katalog, privilege, dan orphan:

   ```powershell
   psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -f .\database\sql\verify\00_hitung_objek.sql
   psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -f .\database\sql\verify\01_uji_privilege.sql
   .\database\sql\verify\hasilkan_orphan_report.ps1 -Database msbd_latihan_utama -AllowTrainingClone
   ```

   Hitung semua baris `PASS` dan `FAIL`; exit code 0 saja bukan bukti semua pemeriksaan lulus.
   Audit orphan bersifat baca-saja dan tidak memperbaiki data.

5. Jalankan baseline sebelum apply dan post sesudah apply memakai test runner yang membuat database
   disposable. Simpan kedua JUnit/log di `%TEMP%`:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\sql\verify\test_klon.ps1 -SourceDump $preDump -AllowPrimarySource -JunitPath (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_baseline.log')
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\sql\verify\test_klon.ps1 -SourceDatabase msbd_latihan_utama -AllowTrainingClone -JunitPath (Join-Path $env:TEMP 'fase1_post.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_post.log')
   .\database\sql\verify\bandingkan_test.ps1 -BeforeJunit (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -AfterJunit (Join-Path $env:TEMP 'fase1_post.junit.xml') -BeforeLog (Join-Path $env:TEMP 'fase1_baseline.log') -AfterLog (Join-Path $env:TEMP 'fase1_post.log')
   ```

   Exit code 2 adalah hasil gagal/error suite yang harus ditangani, bukan tanda lulus. Runner
   menghapus database test disposable setelah run; jangan arahkan Laravel ke database sumber,
   staging, atau utama.

## Batas database utama

`jalankan_fase1.ps1` memiliki konfirmasi eksplisit untuk database utama, tetapi keberadaan flag
tersebut bukan persetujuan untuk deploy. Jangan jalankan dengan `-ConfirmPrimary` sampai gate
privilege, orphan, test, TODO-REVIEW, backup/restore, dan otorisasi deploy dinyatakan lulus.
