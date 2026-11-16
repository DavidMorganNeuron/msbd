# Panduan Eksekusi Fase 1

## Status yang terverifikasi

Tahap 1 sudah diterapkan hanya pada `msbd_tubes_kel7_staging`: perbaikan ID `status_siswa`,
constraint PK/FK/UNIQUE/CHECK yang ditinjau, dan sinkronisasi sequence. Database utama dan `.env`
tidak diubah.

Fase 1 **belum selesai**. Run terakhir `dry_run.ps1` menghasilkan 10 PASS dan 0 FAIL; skrip ini
mengecualikan `dcl/90_hapus_role_guru.sql`, yang dilaporkan SKIP dan tidak dijalankan atau di-dry-run.
Kandidat logic, view, trigger, role, dan privilege menghasilkan 167 PASS/0 FAIL pada verifier dalam
transaksi rollback, tetapi belum diterapkan permanen. Verifier katalog staging aktual masih 150 FAIL
dan 16 PASS. Contoh DML/TCL lulus pada klon disposable, dibersihkan, lalu klonnya dihapus. Database
utama dan `.env` tidak diubah. Rincian terdapat di [FASE1_LAPORAN.md](./FASE1_LAPORAN.md) dan
[ORPHAN_REPORT.md](./ORPHAN_REPORT.md).

## Urutan eksekusi aman

Jalankan dari root repository di PowerShell. Gunakan hanya staging atau database klon disposable.
Jangan mengubah `.env`, dan jangan memakai `migrate:fresh` pada database utama.

### 1. Backup dan verifikasi arsip

```powershell
$preDump = Join-Path $env:TEMP ("fase1_staging_pre_stage1_{0}.dump" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
.\database\sql\verify\backup_staging.ps1 -OutputPath $preDump
```

Script membaca koneksi dari `.env` tanpa mencetak credential, hanya menerima database staging,
menyimpan arsip di `%TEMP%`, dan memeriksa TOC dengan `pg_restore -l`.

### 2. Pastikan target dan preflight

```powershell
psql -X -w -v ON_ERROR_STOP=1 -d msbd_tubes_kel7_staging -c "SELECT current_database(), current_user, version();"
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\ddl\00_preflight_audit.sql
```

Hentikan jika nama database bukan `msbd_tubes_kel7_staging`. Simpan hasil audit `pg_catalog`, kandidat
PK/UNIQUE/CHECK, dan orphan sebelum perubahan.

### 3. Dry-run dan perbaikan status

```powershell
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\dml\00_repair_status_ids.sql -DryRun
.\database\sql\dry_run.ps1
```

Output terkini: 10 PASS, 0 FAIL, exit code 0. Runner menampilkan SKIP untuk
`dcl/90_hapus_role_guru.sql`; file tersebut tidak boleh dijalankan atau di-dry-run. Dry-run adalah
verifikasi transaksional, bukan penerapan permanen atau otorisasi untuk database utama.

### 4. Terapkan subset Tahap 1 yang telah diverifikasi

```powershell
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\dml\00_repair_status_ids.sql -Atomic
$prior = @{}
foreach ($name in @('DB_CONNECTION', 'DB_DATABASE', 'FASE1_TARGET_DB')) {
    $prior[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}
try {
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_DATABASE = 'msbd_tubes_kel7_staging'
    $env:FASE1_TARGET_DB = 'msbd_tubes_kel7_staging'
    php artisan migrate --path=database/migrations/2026_11_16_100000_fase1_database_architecture.php --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Migration Fase 1 gagal' }
} finally {
    foreach ($name in $prior.Keys) {
        [Environment]::SetEnvironmentVariable($name, $prior[$name], 'Process')
    }
}
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\dml\01_sync_sequences.sql
```

Wrapper migration mengecek `FASE1_TARGET_DB` dan hasil `current_database()` sebelum menjalankan
`ddl/01_restore_constraints.sql` dan `ddl/02_constraint_tambahan.sql`. Jalankan sinkronisasi sequence
setelah PK terpasang; `setval()` tidak dibatalkan oleh `ROLLBACK`, jadi file sequence tidak cocok untuk
dry-run transaksional.

### 5. Verifikasi pasca-eksekusi

```powershell
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\verify\01_uji_privilege.sql
.\database\sql\verify\hasilkan_objek.ps1 -BeforeDump $preDump
.\database\sql\verify\hasilkan_orphan_report.ps1
```

Generator membaca katalog staging, membandingkan `schema_backup.sql` (83 FK) dan menghitung orphan
per FK. Katalog aktual Tahap 1 berisi 72 PK dan 96 FK; tiga FK `NOT VALID` memiliki masing-masing
satu orphan. Verifier aktual menghasilkan 150 FAIL/16 PASS. Kandidat DDL/DCL diuji secara terpisah
dalam transaksi rollback dan menghasilkan 167 PASS/0 FAIL; jangan menafsirkan hasil kandidat sebagai
perubahan staging permanen atau menafsirkan exit code 0 saja sebagai semua pemeriksaan lulus.

Contoh seed, query, cleanup, dan TCL diuji pada klon disposable yang dibuat dari staging. Urutannya:
jalankan `ddl/00_preflight_audit.sql` dan `ddl/03_functions.sql`-`ddl/06_views.sql`, seed dengan
`dml/01_seed_demo.sql`, lima berkas `tcl/01`-`tcl/05`, `dml/02_query_demo.sql`, lalu
`dml/99_hapus_data_demo.sql`. Verifikasi akun/siswa/periode demo bernilai `0|0|0` setelah cleanup
dan hapus klon. Jangan jalankan contoh DML/TCL pada staging atau database utama.

### 6. Jalankan test pada klon disposable

Jalankan runner sebagai proses PowerShell anak; exit 2 saat ini mencerminkan baseline test yang gagal.
Runner memverifikasi koneksi Laravel ke klon PostgreSQL dan menghapus klon/dump sementara:

```powershell
powershell.exe -NoProfile -File .\database\sql\verify\test_klon.ps1 -SourceDump $preDump -JunitPath (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_baseline.log')
powershell.exe -NoProfile -File .\database\sql\verify\test_klon.ps1 -SourceDatabase msbd_tubes_kel7_staging -JunitPath (Join-Path $env:TEMP 'fase1_post.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_post.log')
.\database\sql\verify\bandingkan_test.ps1 -BeforeJunit (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -AfterJunit (Join-Path $env:TEMP 'fase1_post.junit.xml') -BeforeLog (Join-Path $env:TEMP 'fase1_baseline.log') -AfterLog (Join-Path $env:TEMP 'fase1_post.log')
```

Jangan memakai langsung staging, database utama, atau klon yang akan digunakan ulang.

## Perilaku runner SQL

`jalankan.ps1` mewajibkan `-Db` dan `-File`, memastikan file berada di `database\sql`, lalu
memverifikasi bahwa `current_database()` cocok dengan `-Db`. Runner membaca koneksi dari `.env`
tanpa mencetak nilainya; alternatifnya memakai environment `PGHOST`, `PGPORT`, `PGUSER`, dan
`PGPASSWORD`.

```powershell
.\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\verify\01_uji_privilege.sql
```

`-DryRun` menjalankan berkas dalam transaksi dan `ROLLBACK`; `-Atomic` menjalankan satu file dengan
`--single-transaction`. `-Konfirmasi` hanya mengizinkan target non-staging setelah verifikasi, bukan
pengganti backup atau otorisasi.
