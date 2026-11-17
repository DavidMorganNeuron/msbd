# Baseline test Fase 1

## Metode

`test_klon.ps1` menjalankan `php artisan test --log-junit=... --display-incomplete` pada dua
database disposable: baseline dipulihkan dari dump sebelum paket, sedangkan post disalin dari
`msbd_latihan_utama` setelah paket. Katalog saat mulai masing-masing berisi 72 tabel; baseline
memiliki 8 PK/13 FK dan post memiliki 72 PK/96 FK. Kedua run memakai source aplikasi dan tests
yang sama. Environment database hanya diatur pada proses test; `.env` dan `phpunit.xml` tidak
diubah. Runner menghapus database test setelah setiap run. Karena baseline dijalankan pada cluster
setelah enam role Fase 1 dibuat, dump pre-package tidak mengembalikan keadaan `pg_roles` global;
perbandingan ini mengisolasi schema/data database, bukan perubahan role cluster.

## Hasil aktual

Perintah runner: `php artisan test --log-junit=<file-temp> --display-incomplete`

<!-- BEGIN GENERATED TEST COMPARISON -->
| Eksekusi | Test (runner) | Passed | Failed | Errors | Incomplete | JUnit testcases | JUnit testcase nodes | JUnit failures | JUnit errors | JUnit skipped | Assertions | Durasi (s) | Exit | JUnit SHA256 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Baseline | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 43 | 68 | 2 | 174 | 8.693 | 2 | `21cede60a9a400fe284f429acaba29902c07dba72eb5779b63d7f19f3c843efc` |
| Sesudah | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 43 | 68 | 2 | 174 | 8.96 | 2 | `fa1571edde36559554c96e084a1b31c832f0750f0193658b07d9aa35ced90e5f` |

Runner melaporkan 151 test; JUnit suite melaporkan 120 test dan memuat 120 node testcase. Selisih runner vs node JUnit adalah 31; seluruh counter failed/errors/incomplete runner cocok dengan JUnit suite, tetapi successful testcase yang tidak diserialisasi tidak dapat diidentifikasi satu per satu dari XML.

| Perbandingan identifier | Jumlah |
| --- | ---: |
| Test baru gagal/error | 0 |
| Test gagal/error terselesaikan | 0 |
| Diagnosis berubah pada test gagal/error yang sama | 52 |

Tidak ada test baru gagal/error; akar masalah baru tidak berlaku. Perubahan diagnosis yang sama tetap harus ditinjau pada tabel baseline dan dilaporkan sebagai perubahan penyebab, bukan regresi identifier.
<!-- END GENERATED TEST COMPARISON -->

Hasil perbandingan nama test dan diagnosis tersanitasi dibuat oleh `verify/bandingkan_test.ps1`
dari dua berkas JUnit; daftar testcase bermasalah disimpan di
`FASE1_BASELINE_GAGAL.txt`.

## Akar masalah yang teramati

| Hasil | Jumlah | Analisis |
| --- | ---: | --- |
| Assertion gagal pada `PpdbFormulirFeatureTest` | 43 | Semua 43 gagal pada `tests/Feature/PpdbFormulirFeatureTest.php:24`: assertion mengharapkan driver `sqlite`, sedangkan koneksi test yang diverifikasi adalah `pgsql`. |
| Error teardown `DROP TABLE` dengan dependency (SQLSTATE `2BP01`) | 61 | 20 drop `guru`, 20 `siswa`, 11 `mutasi_siswa`, 7 `rapor`, 2 `detail_absensi`, dan 1 `log_audit`; constraint/view database mencegah fixture menghapus tabel. |
| Error fixture membuat tabel `role` (SQLSTATE `42P07`) | 7 | Fixture mencoba membuat `public.role` yang sudah ada pada schema yang dipulihkan. |
| **Total errors** | **68** | Semua error yang tertangkap berasal dari setup/teardown fixture, bukan identifier test baru. |

Tidak ada identifier test gagal/error baru atau terselesaikan (0/0), sehingga tidak ada akar masalah
per-test baru untuk dianalisis. Ada 52 perubahan diagnosis pada identifier gagal/error yang sama:
20 error saat menghapus `guru`, 20 `siswa`, 11 `mutasi_siswa`, dan 1 `log_audit`. Diagnosis
`mutasi_siswa` dan `log_audit` secara eksplisit menunjuk view `vw_riwayat_mutasi` dan
`vw_log_audit_ringkas`; yang lain menunjuk dependency FK/view pada schema paket. Perubahan ini
menjelaskan error teardown yang berbeda, bukan regresi identifier baru. Daftar nama test dan
diagnosis tersanitasi ada di [FASE1_BASELINE_GAGAL.txt](./FASE1_BASELINE_GAGAL.txt).

Kedua run melaporkan 151 test kepada runner, sedangkan JUnit memuat 120 testcase. Selisih 31
successful test tidak dapat dipetakan satu per satu dari XML dan belum terjelaskan; karena itu
angka runner dan JUnit dicatat terpisah. Hasil suite tetap tidak lulus: exit code 2.

Hasil ini tidak membuktikan seluruh fungsi aplikasi benar atau salah; ia membandingkan regresi nama
test pada dua klon PostgreSQL penuh dengan source test dan aplikasi yang sama.
