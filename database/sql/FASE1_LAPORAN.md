# Laporan Fase 1 - bukti eksekusi pada klon

## Status dan ruang lingkup

Paket SQL Fase 1 telah diterapkan dalam satu transaksi pada `msbd_latihan_utama`, yang
memiliki marker database `FASE1_TRAINING_CLONE`. Katalog, privilege, orphan, dan test suite
diverifikasi pada klon tersebut atau pada klon test disposable. Paket **belum diterapkan** pada
`msbd_tubes_kel7`.

Pengecualian penting: PostgreSQL roles bersifat cluster-wide. Paket membuat enam role PostgreSQL
yang kini terlihat dari database utama juga. Snapshot sebelum paket mencatat 19 role; katalog
cluster saat verifikasi mencatat 25. Role `app_login` hanya memiliki pengaturan timeout dan
`search_path` pada `msbd_latihan_utama`; query `pg_db_role_setting` tidak menemukan pengaturan
global untuk role tersebut. Pemeriksaan `pg_catalog` saat ini pada `msbd_tubes_kel7` masih
menunjukkan schema pra-paket (72 tabel, 8 PK, 13 FK, 6 view, dan 3 ACL entry), tetapi role cluster
telah berubah.

Skrip paket juga menjalankan `dcl/90_hapus_role_guru.sql` pada klon: baris role aplikasi `guru`
tidak lagi ada di `public.role` klon dan masih ada di `public.role` database utama. Skrip itu dapat
menghapus isi tabel `sessions` pada target jika tabel tersebut ada; karena itu jangan jalankan
paket pada database utama tanpa persetujuan dan pemeriksaan data tersendiri. `.env` tidak diubah.

## 1. Jumlah objek dari `pg_catalog`

Kategori schema dibaca dengan [00_hitung_objek.sql](./verify/00_hitung_objek.sql). Snapshot
sebelum paket berasal dari `pg_catalog` pada `msbd_tubes_kel7` sebelum pembuatan role pada 16
November 2026; hasilnya tersimpan di `%TEMP%\fase1_primary_catalog_pre.txt`. Dump sumber yang
dipulihkan ke klon adalah `%TEMP%\fase1_primary_20261116_180302.dump` (SHA256
`714f22fe19cab8ff93c13856cf789d03e9c17df5473b6559f37dd06aa031ab04`, 355 entri TOC, restore
terverifikasi). Hasil sesudah dibaca langsung dari `msbd_latihan_utama`. Tabel menghitung relasi
tabel `public`; routine non-trigger mengecualikan
function trigger dan function milik extension. `GRANT ACL privilege entries` menghitung entri ACL
eksplisit melalui `aclexplode` pada schema, relasi, routine, dan database aktif, bukan jumlah
perintah `GRANT`.

| Objek                       | Sebelum | Sesudah |
| --------------------------- | ------: | ------: |
| Tabel                       |      72 |      72 |
| Primary key                 |       8 |      72 |
| Foreign key                 |      13 |      96 |
| View                        |       6 |      13 |
| Stored function non-trigger |       0 |       6 |
| Trigger function            |       1 |       5 |
| Trigger non-internal        |      10 |      20 |
| Procedure                   |       1 |       4 |
| Role cluster (`pg_roles`)   |      19 |      25 |
| Role Fase 1                 |       0 |       6 |
| GRANT ACL privilege entries |       3 |    1105 |
| UNIQUE constraint           |       8 |      56 |
| CHECK constraint            |      25 |      25 |

Angka role sesudah adalah jumlah cluster-wide, bukan jumlah role yang khusus berada dalam database
klon. Enam role yang ditambahkan ialah `app_owner`, `app_login`, `role_siswa`, `role_tu`,
`role_kepsek`, dan `role_wali_kelas`. Database utama sekarang juga melihat enam role tersebut,
meskipun schema database utamanya belum menerima paket.

## 2. Keluaran lengkap `verify/01_uji_privilege.sql`

Skrip dijalankan pada `msbd_latihan_utama` sebagai koneksi administrator, dengan marker klon
terverifikasi. Keluaran lengkap berjumlah 215 baris dan berakhir dengan `ROLLBACK`; pemeriksaan
baris hasil menunjukkan **173 PASS, 0 FAIL**, exit code psql **0**. SHA256 berkas keluaran:
`21c959e1b2f4338bb4cc04f50bfabff68273c8147b782633b8d8742940f0654f`; sumber capture:
`%TEMP%\fase1_verify_privilege_training_final.txt`.

```text
BEGIN
DO
CREATE TABLE
CREATE FUNCTION
CREATE FUNCTION
CREATE FUNCTION
CREATE FUNCTION
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
DO
 status | jumlah
--------+--------
 PASS   |    173
(1 row)

 no  |    kelompok    |                                       pemeriksaan                                       |  harapan  |         hasil          | status
-----+----------------+-----------------------------------------------------------------------------------------+-----------+------------------------+--------
   1 | ROLE           | role_siswa ada                                                                          | ADA       | ADA                    | PASS
   2 | ROLE           | role_siswa bukan superuser                                                              | YA        | YA                     | PASS
   3 | ROLE           | role_siswa bisa login                                                                   | TIDAK     | TIDAK                  | PASS
   4 | ROLE           | role_tu ada                                                                             | ADA       | ADA                    | PASS
   5 | ROLE           | role_tu bukan superuser                                                                 | YA        | YA                     | PASS
   6 | ROLE           | role_tu bisa login                                                                      | TIDAK     | TIDAK                  | PASS
   7 | ROLE           | role_kepsek ada                                                                         | ADA       | ADA                    | PASS
   8 | ROLE           | role_kepsek bukan superuser                                                             | YA        | YA                     | PASS
   9 | ROLE           | role_kepsek bisa login                                                                  | TIDAK     | TIDAK                  | PASS
  10 | ROLE           | role_wali_kelas ada                                                                     | ADA       | ADA                    | PASS
  11 | ROLE           | role_wali_kelas bukan superuser                                                         | YA        | YA                     | PASS
  12 | ROLE           | role_wali_kelas bisa login                                                              | TIDAK     | TIDAK                  | PASS
  13 | ROLE           | app_owner ada                                                                           | ADA       | ADA                    | PASS
  14 | ROLE           | app_owner bukan superuser                                                               | YA        | YA                     | PASS
  15 | ROLE           | app_owner bisa login                                                                    | TIDAK     | TIDAK                  | PASS
  16 | ROLE           | app_login ada                                                                           | ADA       | ADA                    | PASS
  17 | ROLE           | app_login bukan superuser                                                               | YA        | YA                     | PASS
  18 | ROLE           | app_login bisa login                                                                    | YA        | YA                     | PASS
  19 | ROLE           | app_login NOINHERIT                                                                     | YA        | YA                     | PASS
  20 | ROLE           | tidak ada role DB bernama guru                                                          | 0         | 0                      | PASS
  21 | TULIS-AKADEMIK | role_tu INSERT pada public.nilai_siswa                                                  | TIDAK ADA | TIDAK ADA              | PASS
  22 | TULIS-AKADEMIK | role_tu UPDATE pada public.nilai_siswa                                                  | TIDAK ADA | TIDAK ADA              | PASS
  23 | TULIS-AKADEMIK | role_tu DELETE pada public.nilai_siswa                                                  | TIDAK ADA | TIDAK ADA              | PASS
  24 | TULIS-AKADEMIK | role_kepsek INSERT pada public.nilai_siswa                                              | TIDAK ADA | TIDAK ADA              | PASS
  25 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.nilai_siswa                                              | TIDAK ADA | TIDAK ADA              | PASS
  26 | TULIS-AKADEMIK | role_kepsek DELETE pada public.nilai_siswa                                              | TIDAK ADA | TIDAK ADA              | PASS
  27 | TULIS-AKADEMIK | role_siswa INSERT pada public.nilai_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  28 | TULIS-AKADEMIK | role_siswa UPDATE pada public.nilai_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  29 | TULIS-AKADEMIK | role_siswa DELETE pada public.nilai_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  30 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.nilai_siswa                                          | ADA       | ADA                    | PASS
  31 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.nilai_siswa                                          | ADA       | ADA                    | PASS
  32 | TULIS-AKADEMIK | role_tu INSERT pada public.sesi_absensi                                                 | TIDAK ADA | TIDAK ADA              | PASS
  33 | TULIS-AKADEMIK | role_tu UPDATE pada public.sesi_absensi                                                 | TIDAK ADA | TIDAK ADA              | PASS
  34 | TULIS-AKADEMIK | role_tu DELETE pada public.sesi_absensi                                                 | TIDAK ADA | TIDAK ADA              | PASS
  35 | TULIS-AKADEMIK | role_kepsek INSERT pada public.sesi_absensi                                             | TIDAK ADA | TIDAK ADA              | PASS
  36 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.sesi_absensi                                             | TIDAK ADA | TIDAK ADA              | PASS
  37 | TULIS-AKADEMIK | role_kepsek DELETE pada public.sesi_absensi                                             | TIDAK ADA | TIDAK ADA              | PASS
  38 | TULIS-AKADEMIK | role_siswa INSERT pada public.sesi_absensi                                              | TIDAK ADA | TIDAK ADA              | PASS
  39 | TULIS-AKADEMIK | role_siswa UPDATE pada public.sesi_absensi                                              | TIDAK ADA | TIDAK ADA              | PASS
  40 | TULIS-AKADEMIK | role_siswa DELETE pada public.sesi_absensi                                              | TIDAK ADA | TIDAK ADA              | PASS
  41 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.sesi_absensi                                         | ADA       | ADA                    | PASS
  42 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.sesi_absensi                                         | ADA       | ADA                    | PASS
  43 | TULIS-AKADEMIK | role_tu INSERT pada public.detail_absensi                                               | TIDAK ADA | TIDAK ADA              | PASS
  44 | TULIS-AKADEMIK | role_tu UPDATE pada public.detail_absensi                                               | TIDAK ADA | TIDAK ADA              | PASS
  45 | TULIS-AKADEMIK | role_tu DELETE pada public.detail_absensi                                               | TIDAK ADA | TIDAK ADA              | PASS
  46 | TULIS-AKADEMIK | role_kepsek INSERT pada public.detail_absensi                                           | TIDAK ADA | TIDAK ADA              | PASS
  47 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.detail_absensi                                           | TIDAK ADA | TIDAK ADA              | PASS
  48 | TULIS-AKADEMIK | role_kepsek DELETE pada public.detail_absensi                                           | TIDAK ADA | TIDAK ADA              | PASS
  49 | TULIS-AKADEMIK | role_siswa INSERT pada public.detail_absensi                                            | TIDAK ADA | TIDAK ADA              | PASS
  50 | TULIS-AKADEMIK | role_siswa UPDATE pada public.detail_absensi                                            | TIDAK ADA | TIDAK ADA              | PASS
  51 | TULIS-AKADEMIK | role_siswa DELETE pada public.detail_absensi                                            | TIDAK ADA | TIDAK ADA              | PASS
  52 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.detail_absensi                                       | ADA       | ADA                    | PASS
  53 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.detail_absensi                                       | ADA       | ADA                    | PASS
  54 | TULIS-AKADEMIK | role_tu INSERT pada public.rapor                                                        | TIDAK ADA | TIDAK ADA              | PASS
  55 | TULIS-AKADEMIK | role_tu UPDATE pada public.rapor                                                        | TIDAK ADA | TIDAK ADA              | PASS
  56 | TULIS-AKADEMIK | role_tu DELETE pada public.rapor                                                        | TIDAK ADA | TIDAK ADA              | PASS
  57 | TULIS-AKADEMIK | role_kepsek INSERT pada public.rapor                                                    | TIDAK ADA | TIDAK ADA              | PASS
  58 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.rapor                                                    | TIDAK ADA | TIDAK ADA              | PASS
  59 | TULIS-AKADEMIK | role_kepsek DELETE pada public.rapor                                                    | TIDAK ADA | TIDAK ADA              | PASS
  60 | TULIS-AKADEMIK | role_siswa INSERT pada public.rapor                                                     | TIDAK ADA | TIDAK ADA              | PASS
  61 | TULIS-AKADEMIK | role_siswa UPDATE pada public.rapor                                                     | TIDAK ADA | TIDAK ADA              | PASS
  62 | TULIS-AKADEMIK | role_siswa DELETE pada public.rapor                                                     | TIDAK ADA | TIDAK ADA              | PASS
  63 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.rapor                                                | ADA       | ADA                    | PASS
  64 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.rapor                                                | ADA       | ADA                    | PASS
  65 | TULIS-AKADEMIK | role_tu INSERT pada public.detail_rapor                                                 | TIDAK ADA | TIDAK ADA              | PASS
  66 | TULIS-AKADEMIK | role_tu UPDATE pada public.detail_rapor                                                 | TIDAK ADA | TIDAK ADA              | PASS
  67 | TULIS-AKADEMIK | role_tu DELETE pada public.detail_rapor                                                 | TIDAK ADA | TIDAK ADA              | PASS
  68 | TULIS-AKADEMIK | role_kepsek INSERT pada public.detail_rapor                                             | TIDAK ADA | TIDAK ADA              | PASS
  69 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.detail_rapor                                             | TIDAK ADA | TIDAK ADA              | PASS
  70 | TULIS-AKADEMIK | role_kepsek DELETE pada public.detail_rapor                                             | TIDAK ADA | TIDAK ADA              | PASS
  71 | TULIS-AKADEMIK | role_siswa INSERT pada public.detail_rapor                                              | TIDAK ADA | TIDAK ADA              | PASS
  72 | TULIS-AKADEMIK | role_siswa UPDATE pada public.detail_rapor                                              | TIDAK ADA | TIDAK ADA              | PASS
  73 | TULIS-AKADEMIK | role_siswa DELETE pada public.detail_rapor                                              | TIDAK ADA | TIDAK ADA              | PASS
  74 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.detail_rapor                                         | ADA       | ADA                    | PASS
  75 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.detail_rapor                                         | ADA       | ADA                    | PASS
  76 | TULIS-AKADEMIK | role_tu INSERT pada public.catatan_wali_kelas                                           | TIDAK ADA | TIDAK ADA              | PASS
  77 | TULIS-AKADEMIK | role_tu UPDATE pada public.catatan_wali_kelas                                           | TIDAK ADA | TIDAK ADA              | PASS
  78 | TULIS-AKADEMIK | role_tu DELETE pada public.catatan_wali_kelas                                           | TIDAK ADA | TIDAK ADA              | PASS
  79 | TULIS-AKADEMIK | role_kepsek INSERT pada public.catatan_wali_kelas                                       | TIDAK ADA | TIDAK ADA              | PASS
  80 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.catatan_wali_kelas                                       | TIDAK ADA | TIDAK ADA              | PASS
  81 | TULIS-AKADEMIK | role_kepsek DELETE pada public.catatan_wali_kelas                                       | TIDAK ADA | TIDAK ADA              | PASS
  82 | TULIS-AKADEMIK | role_siswa INSERT pada public.catatan_wali_kelas                                        | TIDAK ADA | TIDAK ADA              | PASS
  83 | TULIS-AKADEMIK | role_siswa UPDATE pada public.catatan_wali_kelas                                        | TIDAK ADA | TIDAK ADA              | PASS
  84 | TULIS-AKADEMIK | role_siswa DELETE pada public.catatan_wali_kelas                                        | TIDAK ADA | TIDAK ADA              | PASS
  85 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.catatan_wali_kelas                                   | ADA       | ADA                    | PASS
  86 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.catatan_wali_kelas                                   | ADA       | ADA                    | PASS
  87 | TULIS-AKADEMIK | role_tu INSERT pada public.izin_siswa                                                   | TIDAK ADA | TIDAK ADA              | PASS
  88 | TULIS-AKADEMIK | role_tu UPDATE pada public.izin_siswa                                                   | TIDAK ADA | TIDAK ADA              | PASS
  89 | TULIS-AKADEMIK | role_tu DELETE pada public.izin_siswa                                                   | TIDAK ADA | TIDAK ADA              | PASS
  90 | TULIS-AKADEMIK | role_kepsek INSERT pada public.izin_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  91 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.izin_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  92 | TULIS-AKADEMIK | role_kepsek DELETE pada public.izin_siswa                                               | TIDAK ADA | TIDAK ADA              | PASS
  93 | TULIS-AKADEMIK | role_siswa INSERT pada public.izin_siswa                                                | TIDAK ADA | TIDAK ADA              | PASS
  94 | TULIS-AKADEMIK | role_siswa UPDATE pada public.izin_siswa                                                | TIDAK ADA | TIDAK ADA              | PASS
  95 | TULIS-AKADEMIK | role_siswa DELETE pada public.izin_siswa                                                | TIDAK ADA | TIDAK ADA              | PASS
  96 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.izin_siswa                                           | ADA       | ADA                    | PASS
  97 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.izin_siswa                                           | ADA       | ADA                    | PASS
  98 | TULIS-AKADEMIK | role_wali_kelas DELETE pada public.nilai_siswa                                          | ADA       | ADA                    | PASS
  99 | BACA           | role_tu SELECT pada public.nilai_siswa                                                  | ADA       | ADA                    | PASS
 100 | BACA           | role_kepsek SELECT pada public.nilai_siswa                                              | ADA       | ADA                    | PASS
 101 | LOG            | role_siswa UPDATE pada public.log_audit                                                 | TIDAK ADA | TIDAK ADA              | PASS
 102 | LOG            | role_siswa DELETE pada public.log_audit                                                 | TIDAK ADA | TIDAK ADA              | PASS
 103 | LOG            | role_siswa TRUNCATE pada public.log_audit                                               | TIDAK ADA | TIDAK ADA              | PASS
 104 | LOG            | role_tu UPDATE pada public.log_audit                                                    | TIDAK ADA | TIDAK ADA              | PASS
 105 | LOG            | role_tu DELETE pada public.log_audit                                                    | TIDAK ADA | TIDAK ADA              | PASS
 106 | LOG            | role_tu TRUNCATE pada public.log_audit                                                  | TIDAK ADA | TIDAK ADA              | PASS
 107 | LOG            | role_kepsek UPDATE pada public.log_audit                                                | TIDAK ADA | TIDAK ADA              | PASS
 108 | LOG            | role_kepsek DELETE pada public.log_audit                                                | TIDAK ADA | TIDAK ADA              | PASS
 109 | LOG            | role_kepsek TRUNCATE pada public.log_audit                                              | TIDAK ADA | TIDAK ADA              | PASS
 110 | LOG            | role_wali_kelas UPDATE pada public.log_audit                                            | TIDAK ADA | TIDAK ADA              | PASS
 111 | LOG            | role_wali_kelas DELETE pada public.log_audit                                            | TIDAK ADA | TIDAK ADA              | PASS
 112 | LOG            | role_wali_kelas TRUNCATE pada public.log_audit                                          | TIDAK ADA | TIDAK ADA              | PASS
 113 | LOG            | app_login UPDATE pada public.log_audit                                                  | TIDAK ADA | TIDAK ADA              | PASS
 114 | LOG            | app_login DELETE pada public.log_audit                                                  | TIDAK ADA | TIDAK ADA              | PASS
 115 | LOG            | app_login TRUNCATE pada public.log_audit                                                | TIDAK ADA | TIDAK ADA              | PASS
 116 | LOG            | role_siswa UPDATE pada public.log_login                                                 | TIDAK ADA | TIDAK ADA              | PASS
 117 | LOG            | role_siswa DELETE pada public.log_login                                                 | TIDAK ADA | TIDAK ADA              | PASS
 118 | LOG            | role_siswa TRUNCATE pada public.log_login                                               | TIDAK ADA | TIDAK ADA              | PASS
 119 | LOG            | role_tu UPDATE pada public.log_login                                                    | TIDAK ADA | TIDAK ADA              | PASS
 120 | LOG            | role_tu DELETE pada public.log_login                                                    | TIDAK ADA | TIDAK ADA              | PASS
 121 | LOG            | role_tu TRUNCATE pada public.log_login                                                  | TIDAK ADA | TIDAK ADA              | PASS
 122 | LOG            | role_kepsek UPDATE pada public.log_login                                                | TIDAK ADA | TIDAK ADA              | PASS
 123 | LOG            | role_kepsek DELETE pada public.log_login                                                | TIDAK ADA | TIDAK ADA              | PASS
 124 | LOG            | role_kepsek TRUNCATE pada public.log_login                                              | TIDAK ADA | TIDAK ADA              | PASS
 125 | LOG            | role_wali_kelas UPDATE pada public.log_login                                            | TIDAK ADA | TIDAK ADA              | PASS
 126 | LOG            | role_wali_kelas DELETE pada public.log_login                                            | TIDAK ADA | TIDAK ADA              | PASS
 127 | LOG            | role_wali_kelas TRUNCATE pada public.log_login                                          | TIDAK ADA | TIDAK ADA              | PASS
 128 | LOG            | app_login UPDATE pada public.log_login                                                  | TIDAK ADA | TIDAK ADA              | PASS
 129 | LOG            | app_login DELETE pada public.log_login                                                  | TIDAK ADA | TIDAK ADA              | PASS
 130 | LOG            | app_login TRUNCATE pada public.log_login                                                | TIDAK ADA | TIDAK ADA              | PASS
 131 | LOG            | role_kepsek SELECT pada public.log_audit                                                | ADA       | ADA                    | PASS
 132 | LOG            | role_siswa SELECT pada public.log_audit                                                 | TIDAK ADA | TIDAK ADA              | PASS
 133 | LOG            | role_wali_kelas SELECT pada public.log_audit                                            | TIDAK ADA | TIDAK ADA              | PASS
 134 | LOG            | app_login INSERT pada public.log_login                                                  | ADA       | ADA                    | PASS
 135 | KOLOM          | role_siswa SELECT pengguna.password_hash                                                | TIDAK ADA | TIDAK ADA              | PASS
 136 | KOLOM          | role_tu SELECT pengguna.password_hash                                                   | TIDAK ADA | TIDAK ADA              | PASS
 137 | KOLOM          | role_kepsek SELECT pengguna.password_hash                                               | TIDAK ADA | TIDAK ADA              | PASS
 138 | KOLOM          | role_wali_kelas SELECT pengguna.password_hash                                           | TIDAK ADA | TIDAK ADA              | PASS
 139 | KOLOM          | role_tu UPDATE pengguna.password_hash                                                   | ADA       | ADA                    | PASS
 140 | PUBLIC         | jumlah GRANT ke PUBLIC pada tabel/view skema public                                     | 0         | 0                      | PASS
 141 | PUBLIC         | EXECUTE sp_setujui_pengajuan untuk role_wali_kelas                                      | ADA       | ADA                    | PASS
 142 | PUBLIC         | EXECUTE sp_setujui_pengajuan untuk role_siswa                                           | TIDAK ADA | TIDAK ADA              | PASS
 143 | PUBLIC         | EXECUTE sp_setujui_pengajuan untuk app_login                                            | TIDAK ADA | TIDAK ADA              | PASS
 144 | DEFINER        | fn_audit_trail SECURITY DEFINER + search_path + pemilik app_owner                       | YA        | YA                     | PASS
 145 | DEFINER        | sp_setujui_pengajuan SECURITY DEFINER + search_path + pemilik app_owner                 | YA        | YA                     | PASS
 146 | VIEW           | view vw_* tanpa security_invoker                                                        | 0         | 0                      | PASS
 147 | VIEW           | kolom password_hash pada view                                                           | 0         | 0                      | PASS
 148 | OBJEK          | tabel tanpa PRIMARY KEY                                                                 | 0         | 0                      | PASS
 149 | OBJEK          | FOREIGN KEY (min 83)                                                                    | CUKUP     | CUKUP                  | PASS
 150 | OBJEK          | view (min 12)                                                                           | CUKUP     | CUKUP                  | PASS
 151 | OBJEK          | trigger (non-internal) (min 18)                                                         | CUKUP     | CUKUP                  | PASS
 152 | OBJEK          | stored function (bukan trigger) (min 6)                                                 | CUKUP     | CUKUP                  | PASS
 153 | OBJEK          | trigger function (min 5)                                                                | CUKUP     | CUKUP                  | PASS
 154 | OBJEK          | stored procedure (min 4)                                                                | CUKUP     | CUKUP                  | PASS
 155 | AUDIT          | trigger fn_audit_trail pada nilai_siswa                                                 | 1         | 1                      | PASS
 156 | AUDIT          | trigger fn_audit_trail pada rapor                                                       | 1         | 1                      | PASS
 157 | AUDIT          | trigger fn_audit_trail pada mutasi_siswa                                                | 1         | 1                      | PASS
 158 | AUDIT          | trigger fn_audit_trail pada pendaftaran_ppdb                                            | 1         | 1                      | PASS
 159 | AUDIT          | trigger fn_audit_trail pada konfirmasi_data                                             | 1         | 1                      | PASS
 160 | AUDIT          | trigger fn_audit_trail pada periode_update                                              | 1         | 1                      | PASS
 161 | DML-NYATA      | role_tu: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_         | DITOLAK   | DITOLAK                | PASS
 162 | DML-NYATA      | role_kepsek: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_     | DITOLAK   | DITOLAK                | PASS
 163 | DML-NYATA      | role_siswa: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_      | DITOLAK   | DITOLAK                | PASS
 164 | DML-NYATA      | role_wali_kelas: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_ | LOLOS     | LOLOS                  | PASS
 165 | DML-NYATA      | role_kepsek: UPDATE public.log_audit SET nama_tabel = nama_tabel WHERE false            | DITOLAK   | DITOLAK                | PASS
 166 | DML-NYATA      | role_siswa: SELECT password_hash FROM public.pengguna LIMIT 0                           | DITOLAK   | DITOLAK                | PASS
 167 | DML-NYATA      | role_tu: SELECT password_hash FROM public.pengguna LIMIT 0                              | DITOLAK   | DITOLAK                | PASS
 168 | DML-NYATA      | role_kepsek: SELECT password_hash FROM public.pengguna LIMIT 0                          | DITOLAK   | DITOLAK                | PASS
 169 | DML-NYATA      | role_wali_kelas: SELECT password_hash FROM public.pengguna LIMIT 0                      | DITOLAK   | DITOLAK                | PASS
 170 | DML-NYATA      | role_tu: UPDATE public.pengguna SET password_hash = 'uji' WHERE false                   | LOLOS     | LOLOS                  | PASS
 171 | DML-NYATA      | role_siswa: CALL public.sp_setujui_pengajuan(0, 0, NULL)                                | DITOLAK   | DITOLAK                | PASS
 172 | DML-NYATA      | role_wali_kelas: CALL public.sp_setujui_pengajuan(0, 0, NULL)                           | LOLOS     | LOLOS (SQLSTATE P0001) | PASS
 173 | TRIGGER        | DELETE log_audit oleh superuser                                                         | DIBLOKIR  | DIBLOKIR               | PASS
(173 rows)

ROLLBACK
```

## 3. Hasil `php artisan test` dibanding baseline

Baseline dipulihkan dari dump sebelum paket ke database test disposable; hasil sesudah berasal dari
klon disposable yang dibuat dari `msbd_latihan_utama`. Kedua run memakai source aplikasi dan tests
yang sama. Koneksi Laravel diverifikasi menunjuk database test disposable, bukan database utama
atau klon sumber. Karena PostgreSQL roles bersifat cluster-wide, dump baseline tidak mengembalikan
keadaan `pg_roles` sebelum enam role Fase 1 dibuat; perbandingan ini mengisolasi schema/data
database, bukan perubahan role cluster. Rincian hasil serta daftar diagnosis tersanitasi ada di
[FASE1_BASELINE.md](./FASE1_BASELINE.md) dan [FASE1_BASELINE_GAGAL.txt](./FASE1_BASELINE_GAGAL.txt).

| Hasil runner                |                                                           Baseline |                                                            Sesudah |
| --------------------------- | -----------------------------------------------------------------: | -----------------------------------------------------------------: |
| Test yang dilaporkan runner |                                                                151 |                                                                151 |
| Passed                      |                                                                 40 |                                                                 40 |
| Failed                      |                                                                 43 |                                                                 43 |
| Errors                      |                                                                 68 |                                                                 68 |
| Incomplete                  |                                                                  2 |                                                                  2 |
| Exit code                   |                                                                  2 |                                                                  2 |
| Testcase node pada JUnit    |                                                                120 |                                                                120 |
| SHA256 JUnit                | `21cede60a9a400fe284f429acaba29902c07dba72eb5779b63d7f19f3c843efc` | `fa1571edde36559554c96e084a1b31c832f0750f0193658b07d9aa35ced90e5f` |

Perbandingan identifier menghasilkan **0 test baru gagal/error**, **0 terselesaikan**, dan **52
diagnosis berubah** pada test yang memang sudah gagal/error. Karena tidak ada identifier baru yang
gagal, tidak ada akar masalah regresi per-test baru. Suite tetap **tidak lulus**.

Akar masalah hasil aktual sesudah paket:

| Kategori                                                       | Jumlah | Akar masalah                                                                                                                                                            |
| -------------------------------------------------------------- | -----: | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Assertion `PpdbFormulirFeatureTest`                            |     43 | Semua gagal pada assertion setup `tests/Feature/PpdbFormulirFeatureTest.php:24`: test mengharapkan driver `sqlite`, sedangkan koneksi yang diverifikasi adalah `pgsql`. |
| Teardown `DROP TABLE` ditolak (SQLSTATE `2BP01`)               |     61 | Dependency constraint/view mencegah teardown: `guru` 20, `siswa` 20, `mutasi_siswa` 11, `rapor` 7, `detail_absensi` 2, `log_audit` 1.                                   |
| Fixture membuat tabel `role` yang sudah ada (SQLSTATE `42P07`) |      7 | Fixture mencoba membuat ulang `public.role` pada schema hasil restore.                                                                                                  |
| **Total errors**                                               | **68** | Seluruhnya merupakan kegagalan setup/teardown fixture yang teramati.                                                                                                    |

Ke-52 diagnosis yang berubah terdiri dari 20 saat teardown `guru`, 20 `siswa`, 11
`mutasi_siswa`, dan 1 `log_audit`. Diagnosis pada `mutasi_siswa` dan `log_audit` menyebut view
`vw_riwayat_mutasi` dan `vw_log_audit_ringkas`; perubahan diagnosis lain menunjukkan dependency
FK/view pada schema paket. Ini mengubah penyebab error teardown yang sama, bukan menambah test
gagal baru. Runner melaporkan 151 test sedangkan XML JUnit memuat 120 testcase; selisih 31
successful test belum dapat dipetakan satu per satu dari XML dan tetap menjadi temuan.

## 4. ORPHAN_REPORT dan FK `NOT VALID`

Audit final pada klon menghitung 96 FK: 93 `VALID` dan 3 `NOT VALID`. Anti-join menemukan tiga
baris yatim, masing-masing satu pada FK berikut:

| Foreign key                   | Status    | Baris yatim |
| ----------------------------- | --------- | ----------: |
| `log_login_id_pengguna_fkey`  | NOT VALID |           1 |
| `notifikasi_id_pengguna_fkey` | NOT VALID |           1 |
| `siswa_id_pengguna_fkey`      | NOT VALID |           1 |

Tidak ada baris yatim yang dihapus atau diperbaiki oleh audit. Rincian seluruh 96 FK, tabel/kolom
child-parent, aksi `ON DELETE`, status, dan jumlah orphan per FK ada di
[ORPHAN_REPORT.md](./ORPHAN_REPORT.md).

## 5. TODO-REVIEW

1. Tidak ada row-level security (RLS). Hak tabel tidak mengisolasi siswa, kelas, atau kepemilikan
   baris; data path yang relevan masih bergantung pada aplikasi.
2. Koneksi Laravel masih memakai akun database berprivilege tinggi dan belum memetakan role
   Laravel ke `SET ROLE`. Jangan pindahkan koneksi ke `app_login` sebelum model switching role dan
   isolasi baris diterapkan serta diuji.
3. `ReferensiService` mencatat audit langsung melalui `AuditCatat`, sedangkan matriks hanya
   memberi `INSERT log_audit` kepada `app_owner`. Pindahkan penulisan ke routine definer terbatas
   atau tinjau ulang batas integritas audit sebelum mengaktifkan hak `role_tu`.
4. Audit caller dan kolom untuk write `Admin\PenggunaController` serta procedure non-approval
   belum lengkap.
5. Perlu test end-to-end di bawah role PostgreSQL yang sesuai untuk perubahan password siswa,
   notifikasi, PPDB, report, view, dan akses role-spesifik lainnya.

## 6. Temuan untuk Fase 2

- Tiga FK `NOT VALID` masih memiliki orphan dan belum dapat divalidasi; identitas yatim perlu
  dipetakan dan keputusan perbaikan data disetujui sebelum `VALIDATE CONSTRAINT`.
- Matriks privilege yang hijau membuktikan pemeriksaan grant pada klon, bukan isolasi per baris
  atau kompatibilitas seluruh alur Laravel.
- Test suite belum lulus karena fixture memaksa SQLite dan teardown gagal terhadap dependency
  PostgreSQL. Selisih 31 antara runner dan node JUnit juga belum dijelaskan.
- Role PostgreSQL dibuat cluster-wide. Penerapan paket pada klon sudah menambah enam role yang
  terlihat oleh database lain pada cluster; rencana Fase 2 harus memasukkan pengelolaan global
  role ini.
- Penerapan ke database utama belum disetujui dalam laporan ini. Selesaikan TODO-REVIEW,
  keputusan atas orphan, dan gerbang test terlebih dahulu.

## 7. Menjalankan ulang dari nol pada klon disposable

Langkah berikut ditujukan untuk lingkungan uji baru dan tidak menjalankan paket pada database
utama. Perintah `pg_dump`/`pg_restore`/`psql` memerlukan koneksi PostgreSQL lokal yang sudah
disiapkan dari kredensial lokal; jangan mencetak atau menaruh password di berkas laporan.
`msbd_latihan_utama` harus belum ada. Jika database tersebut sudah ada (seperti pada lingkungan
run ini), **berhenti dan jangan menimpa atau menghapusnya** tanpa persetujuan eksplisit.

1. Buat backup pre-package dari sumber, lalu verifikasi arsipnya:

    ```powershell
    $preDump = Join-Path $env:TEMP ('fase1_primary_pre_{0}.dump' -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
    pg_dump -w --format=custom --no-owner --no-acl -f $preDump -d msbd_tubes_kel7
    if ($LASTEXITCODE -ne 0) { throw 'pg_dump gagal' }
    $toc = pg_restore -l $preDump
    if ($LASTEXITCODE -ne 0 -or @($toc).Count -lt 2) { throw 'pg_restore -l gagal memvalidasi backup' }
    ```

2. Buat database klon kosong, pulihkan backup, dan pasang marker yang dipersyaratkan runner:

    ```powershell
    createdb -w msbd_latihan_utama
    if ($LASTEXITCODE -ne 0) { throw 'Klon tidak dapat dibuat; jangan mencoba menimpa database yang sudah ada' }
    pg_restore -w --exit-on-error --no-owner --no-acl -d msbd_latihan_utama $preDump
    if ($LASTEXITCODE -ne 0) { throw 'Restore klon gagal' }
    psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "COMMENT ON DATABASE msbd_latihan_utama IS 'FASE1_TRAINING_CLONE';"
    if ($LASTEXITCODE -ne 0) { throw 'Marker klon gagal dipasang' }
    psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -c "SELECT current_database(), shobj_description(oid, 'pg_database') FROM pg_database WHERE datname=current_database();"
    ```

3. Simpan hitungan `pg_catalog` sebelum paket. Jalankan query read-only:

    ```powershell
    psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -f .\database\sql\verify\00_hitung_objek.sql
    ```

    Pada cluster baru, catat juga jumlah `pg_roles` sebelum paket karena roles cluster-wide dan
    tidak disimpan dalam dump database. Pada cluster run ini jumlah historis sebelum paket adalah
    19; setelah paket role cluster berjumlah 25.

4. Jalankan dry-run paket, kemudian terapkan secara atomik hanya setelah target/marker terverifikasi:

    ```powershell
    .\database\sql\verify\jalankan_fase1.ps1 -Database msbd_latihan_utama -AllowTrainingClone -DryRun
    .\database\sql\verify\jalankan_fase1.ps1 -Database msbd_latihan_utama -AllowTrainingClone
    ```

    Runner membuat backup klon di `%TEMP%`, memvalidasi TOC, memeriksa marker, dan menerapkan
    berkas paket dalam satu transaksi. Paket mencakup migrasi role aplikasi `guru`; tinjau efek
    `dcl/90_hapus_role_guru.sql` pada data `sessions` klon sebelum apply.

5. Verifikasi katalog, privilege, dan orphan:

    ```powershell
    psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -f .\database\sql\verify\00_hitung_objek.sql
    psql -X -w -v ON_ERROR_STOP=1 -d msbd_latihan_utama -f .\database\sql\verify\01_uji_privilege.sql
    .\database\sql\verify\hasilkan_orphan_report.ps1 -Database msbd_latihan_utama -AllowTrainingClone
    ```

    Verifier privilege harus dihitung dari seluruh baris `PASS`/`FAIL`, bukan hanya exit code.
    Audit orphan bersifat baca-saja dan tidak memperbaiki baris yatim.

6. Jalankan baseline sebelum apply dan post sesudah apply pada database test disposable. Baseline
   menggunakan dump pre-package; post menggunakan klon bertanda. Exit code 2 saat ini adalah
   hasil test gagal/error yang harus dilaporkan, bukan dianggap lulus:

    ```powershell
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\sql\verify\test_klon.ps1 -SourceDump $preDump -AllowPrimarySource -JunitPath (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_baseline.log')
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\sql\verify\test_klon.ps1 -SourceDatabase msbd_latihan_utama -AllowTrainingClone -JunitPath (Join-Path $env:TEMP 'fase1_post.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_post.log')
    .\database\sql\verify\bandingkan_test.ps1 -BeforeJunit (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -AfterJunit (Join-Path $env:TEMP 'fase1_post.junit.xml') -BeforeLog (Join-Path $env:TEMP 'fase1_baseline.log') -AfterLog (Join-Path $env:TEMP 'fase1_post.log')
    ```

Runner test membuat dan menghapus database test disposable sendiri. Jangan menjalankan fixture
test pada `msbd_tubes_kel7` atau menggunakan klon sumber sebagai database test.

## 8. Pemulihan schema-only ke `finish_msbd_tubes_kel7`

Pada 16 November 2026, database tujuan `finish_msbd_tubes_kel7` diverifikasi kosong dari objek
schema pengguna sebelum restore. Source `msbd_latihan_utama` diverifikasi memiliki marker
`FASE1_TRAINING_CLONE`; keduanya PostgreSQL 18.6 dengan encoding/collation yang sama. Dump
`pg_dump --schema-only --format=custom` dipulihkan dengan `pg_restore --single-transaction`;
data tabel tidak disalin.

Backup tujuan sebelum restore: `%TEMP%\finish_msbd_tubes_kel7_before_schema_20261116_192150.dump`,
SHA256 `aa2566f044e23619435e102b7f9f29fa8c3968b4569cda5c65a133379608c404`.
Dump schema dari klon: `%TEMP%\fase1_msbd_latihan_utama_schema_20261116_192150.dump`,
559 entri TOC, SHA256 `71afc243ade9adfced8b403d006a9510d2b9b8647f6a695330ee4173db602145`.

Restore awal menghasilkan 641 ACL entry; matriks kanonis
[02_privileges.sql](./dcl/02_privileges.sql) kemudian diterapkan dalam satu transaksi. Hasil
`pg_catalog` tujuan cocok dengan sumber pada 1105 ACL entry dan seluruh jumlah objek berikut:
72 tabel, 72 PK, 96 FK (3 `NOT VALID`), 13 view, 6 stored function non-trigger, 5 trigger
function, 20 trigger, 4 procedure, 56 UNIQUE, dan 25 CHECK. Pengaturan `app_login` diverifikasi
khusus untuk `finish_msbd_tubes_kel7` (timeout 30 detik, idle transaction timeout 30 detik, dan
`search_path` `pg_catalog, public, pg_temp`); tidak ada pengaturan global baru yang dibuat.

Pemeriksaan hitung baris memastikan 72 tabel publik tujuan kosong (`nonempty_tables=0`,
`total_rows=0`), sesuai permintaan schema-only. Karena data tidak disalin, tidak ada baris child
yang dapat menjadi orphan pada tujuan; constraint `NOT VALID` tetap `NOT VALID` dan tidak
divalidasi. Pemeriksaan ACL sampel cocok dengan sumber: `role_siswa` dapat membaca `siswa`;
`role_tu` tidak dapat membaca `pengguna.password_hash` tetapi dapat memperbaruinya. Verifier penuh
`01_uji_privilege.sql` tidak dijalankan pada database tujuan dalam langkah ini.

## 9. Restore penuh Fase 1 ke database utama

Pada 16 November 2026, isi penuh `msbd_latihan_utama` dipulihkan ke `msbd_tubes_kel7`.
Source clone memiliki marker `FASE1_TRAINING_CLONE`. Full custom dump dipulihkan ke database
sementara dengan `pg_restore --single-transaction`, lalu kandidat diverifikasi sebelum ditukar
menjadi database utama. Source clone tidak diubah. Matriks kanonis
[02_privileges.sql](./dcl/02_privileges.sql) diterapkan pada kandidat karena restore menghasilkan
641 ACL entry; sesudahnya jumlahnya 1105, sama dengan source. Pengaturan `app_login` khusus
database dan hardening schema `public` juga diverifikasi cocok dengan source.

Sesudah penukaran, pemeriksaan `pg_catalog` pada `msbd_tubes_kel7` menunjukkan 72 tabel, 72 PK,
96 FK, 13 view, 6 stored function non-trigger, 5 trigger function, 20 trigger, 4 procedure,
25 role cluster, 6 role Fase 1, 1105 ACL entry, 56 UNIQUE, dan 25 CHECK. Hitungan baris setiap
tabel publik cocok dengan source: 72 tabel dan total 1233 baris. Pengaturan `app_login`:
`statement_timeout=30s`, `idle_in_transaction_session_timeout=30s`, dan
`search_path=pg_catalog, public, pg_temp`.

Audit FK baca-saja pada database utama menemukan 96 FK: 93 `VALID` dan 3 `NOT VALID`. Ketiga
FK `NOT VALID` masing-masing masih memiliki satu baris orphan: `log_login_id_pengguna_fkey`,
`notifikasi_id_pengguna_fkey`, dan `siswa_id_pengguna_fkey`. Tidak ada baris yang diperbaiki atau
dihapus oleh audit. Ini sama dengan keadaan source clone.

Perbedaan data yang teramati sebelum penukaran: database utama lama memiliki 1237 baris, source
1233; `sessions` berisi 7 baris pada database utama lama dan 0 pada source; `log_audit` 502
versus 507; `migrations` 23 versus 24; `role` 5 versus 4; `role_hak_akses` 16 versus 14.
Penggantian mengikuti source, sehingga 7 sesi lama tidak ikut dipindahkan. Database utama lama
kemudian dihapus sesuai persetujuan pengguna. Backup sebelum migrasi telah diuji dengan restore
ke database sementara (72 tabel, 1237 baris) dan disimpan di
`C:\Users\Adrian Varerus\Documents\MSBD-KEL7-Backups\msbd_tubes_kel7_pre_migrasi_20261116_195830.dump`,
SHA256 `2792524c11eb0583c670afe08169ed6746788fbcb04a2ab9905f985ae55390fa`.

Enam role Fase 1 sudah ada cluster-wide; password role tidak diubah. Permintaan menggunakan
password bersama satu karakter `0` tidak diterapkan karena tidak aman. Full verifier
`01_uji_privilege.sql` dan test Laravel tidak dijalankan pada database utama; jangan menganggap
database lulus kedua pengujian tersebut. Pemeriksaan privilege sampel saja cocok dengan source.
