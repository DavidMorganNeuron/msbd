# Baseline test Fase 1

## Metode

`phpunit.xml` menggunakan koneksi PostgreSQL dan database test terpisah. Untuk perbandingan Fase 1,
test dijalankan pada dua klon disposable yang dibuat dari schema serta data referensi pre/post
Tahap 1. Katalog kedua klon berisi 72 tabel sebelum test; baseline memiliki 8 PK/13 FK dan post
memiliki 72 PK/96 FK. Environment `DB_CONNECTION`/`DB_DATABASE` hanya diatur pada proses test;
`.env`, `phpunit.xml`, kode PHP, dan file tests tidak diubah.

Test suite memodifikasi schema klon saat teardown gagal: klon pre berakhir dengan 59 tabel dan klon
post dengan 60 tabel. Karena itu hasil di bawah adalah hasil eksekusi pada klon penuh saat mulai,
dan klon tidak digunakan ulang.

## Hasil aktual

Perintah: `php artisan test --compact`

<!-- BEGIN GENERATED TEST COMPARISON -->
| Eksekusi | Test (runner) | Passed | Failed | Errors | Incomplete | JUnit testcases | JUnit testcase nodes | JUnit failures | JUnit errors | JUnit skipped | Assertions | Durasi (s) | Exit | JUnit SHA256 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Baseline | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 43 | 68 | 2 | 174 | 10.358 | 2 | `e9a35aa1289304662cd094c36332fa735032ee79bcc83cb1fd9e5c289c986a86` |
| Sesudah | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 43 | 68 | 2 | 174 | 10.014 | 2 | `8793e461c8964ef2ba82d3a4cdebd0be3863f1401e86ab83fd1f3145e8c8eb82` |

Runner melaporkan 151 test; JUnit suite melaporkan 120 test dan memuat 120 node testcase. Selisih runner vs node JUnit adalah 31; seluruh counter failed/errors/incomplete runner cocok dengan JUnit suite, tetapi successful testcase yang tidak diserialisasi tidak dapat diidentifikasi satu per satu dari XML.

| Perbandingan identifier | Jumlah |
| --- | ---: |
| Test baru gagal/error | 0 |
| Test gagal/error terselesaikan | 0 |
| Diagnosis berubah pada test gagal/error yang sama | 47 |

Tidak ada test baru gagal/error; akar masalah baru tidak berlaku. Perubahan diagnosis yang sama tetap harus ditinjau pada tabel baseline dan dilaporkan sebagai perubahan penyebab, bukan regresi identifier.
<!-- END GENERATED TEST COMPARISON -->

Hasil perbandingan nama test dan diagnosis tersanitasi dibuat oleh `verify/bandingkan_test.ps1`
dari dua berkas JUnit; daftar testcase bermasalah disimpan di
`FASE1_BASELINE_GAGAL.txt`.

## Akar masalah yang teramati

| Hasil | Jumlah | Analisis |
| --- | ---: | --- |
| Assertion gagal pada `PpdbFormulirFeatureTest` | 43 | Semua 43 gagal pada assertion setup `PpdbFormulirFeatureTest.php:24`, yang mensyaratkan driver `sqlite`; klon uji menggunakan `pgsql`. |
| Error teardown drop tabel dengan dependency | 60 | 20 drop `guru`, 20 `siswa`, 7 `rapor`, 6 `status_siswa`, 5 `nilai_siswa`, dan 2 `detail_absensi`; view dan/atau foreign key klon mencegah penghapusan. |
| Error fixture membuat tabel `role` | 7 | Fixture mencoba membuat tabel yang sudah ada pada schema staging. |
| Error teardown drop `semester` | 1 | `jadwal_pelajaran` masih memiliki FK ke parent `semester`. |
| **Total errors** | **68** | Seluruhnya merupakan ketidakcocokan fixture/teardown dengan skema PostgreSQL staging. |

Tidak ada identifier test gagal/error baru atau terselesaikan (0/0). Namun 47 diagnosis untuk
identifier yang sama berubah setelah Tahap 1: 20 error drop `guru`, 20 drop `siswa`, 6 drop
`status_siswa`, dan 1 drop `semester`. Keenam error pada `MutasiSiswaWorkflowTest` beralih dari
teardown `keanggotaan_kelas` ke `status_siswa`; hasil post menunjukkan `siswa_id_status_siswa_fkey`
sebagai dependency. Perubahan diagnosis bukan test baru gagal. Daftar test dan diagnosis tersanitasi
ada di [FASE1_BASELINE_GAGAL.txt](./FASE1_BASELINE_GAGAL.txt).

Hasil ini tidak membuktikan seluruh fungsi aplikasi benar atau salah; ia membandingkan regresi nama
test pada dua klon PostgreSQL penuh dengan source test dan aplikasi yang sama.





