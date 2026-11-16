# Laporan Fase 1 - hasil eksekusi nyata

## Status dan ruang lingkup

Tahap 1 diterapkan pada `msbd_tubes_kel7_staging` (PostgreSQL 18.6) sebagai role koneksi `postgres`.
Database utama `msbd_tubes_kel7` dan file `.env` tidak diubah. Tidak ada perubahan pada PHP, routes,
atau tests. Migration Fase 1 tercatat pada tabel `migrations` staging, batch 12; wrapper memeriksa
`FASE1_TARGET_DB` dan `current_database()` sebelum menjalankan SQL.

Tahap 1 memperbaiki ID lookup `status_siswa`, menambahkan PK/FK/constraint yang lolos pemeriksaan,
dan menyinkronkan sequence. Sepuluh file DDL/DCL yang dipilih (tidak termasuk
`dcl/90_hapus_role_guru.sql`) kini lulus dry-run 10 PASS/0 FAIL. Kandidat logic dan privilege juga
lulus verifier 167 PASS/0 FAIL dalam transaksi yang di-ROLLBACK, tetapi belum diterapkan permanen.
Karena katalog staging tetap belum memiliki role dan objek kandidat tersebut, verifier staging
aktual masih 150 FAIL/16 PASS. Fase 1 keseluruhan **belum selesai** dan tidak dinyatakan siap untuk
database utama.

Backup sebelum Tahap 1 dibuat sebagai arsip custom-format berukuran 184.600 byte. `pg_restore -l`
berhasil membaca 370 entri TOC. Hasil tetap berada di `%TEMP%\fase1_staging_pre_stage1_20261116_152253.dump`.

## 1. Jumlah objek dari `pg_catalog`

Tabel berikut dihasilkan oleh `verify/hasilkan_objek.ps1` dari
`verify/00_hitung_objek.sql`. Kategori tabel, constraint, view, dan rutin dibatasi ke schema
`public`; role dibaca dari `pg_roles` (cluster-wide). GRANT adalah jumlah privilege entry ACL
eksplisit pada schema, relation, routine, dan database aktif, bukan jumlah perintah GRANT.

<!-- BEGIN GENERATED KATALOG COUNTS -->
| Objek | Sebelum | Sesudah |
| --- | ---: | ---: |
| Tabel | 72 | 72 |
| Primary key | 8 | 72 |
| Foreign key | 13 | 96 |
| View | 6 | 6 |
| Stored function non-trigger | 0 | 0 |
| Trigger function | 1 | 1 |
| Trigger non-internal | 10 | 10 |
| Procedure | 1 | 1 |
| Role cluster | 19 | 19 |
| Role Fase 1 | 0 | 0 |
| GRANT ACL privilege entries | 3 | 3 |
| UNIQUE constraint | 8 | 56 |
| CHECK constraint | 25 | 25 |
<!-- END GENERATED KATALOG COUNTS -->

Sebelum dan sesudah merujuk snapshot dari `pg_catalog` staging pre-Tahap 1 dan katalog staging
sesudah Tahap 1. Generator menyimpan snapshot pertama pada `verify/katalog_sebelum.txt`; angka
perlu diperbarui dengan menjalankan generator setelah gerbang yang mengubah katalog.

## 2. Perubahan data dan constraint yang diterapkan

### Perbaikan `status_siswa`

Precondition diverifikasi pada staging sebelum DML. Pemetaan yang diterapkan:

| `id_status_siswa` | `kode_status` |
| ---: | --- |
| 1 | `calon` |
| 2 | `aktif` |
| 3 | `cuti` |
| 4 | `mutasi` |
| 5 | `lulus` |
| 6 | `keluar` |
| 7 | `mengundurkan_diri` |
| 8 | `dikeluarkan` |
| 9 | `putus_sekolah` |
| 10 | `meninggal_dunia` |

Query sesudah penerapan menghasilkan 10 baris status dengan 10 ID unik (min 1, max 10); tabel
`siswa` berisi 9 baris dan join `siswa` ke `status_siswa` menghasilkan 9 baris. Jumlah siswa dengan
ID 2 tetap 8 dan ID 4 tetap 1, kini masing-masing merujuk status yang tidak ambigu.

### Constraint dan sequence

- DDL diterapkan melalui migration wrapper yang membatasi target ke staging. Katalog berubah dari
  8 menjadi 72 PK dan dari 13 menjadi 96 FK.
- Dari 96 FK sesudahnya, 93 `VALID` dan 3 `NOT VALID`. Ketiganya memiliki satu baris yatim masing-masing;
  tidak ada data yatim yang dihapus atau diperbaiki otomatis.
- 62 sequence/identity sequence disinkronkan setelah PK dibuat. Verifikasi pasca-eksekusi melaporkan
  0 sequence mismatch.
- Dry-run terkini `dry_run.ps1`: 10 PASS, 0 FAIL, exit code 0. `dcl/90_hapus_role_guru.sql`
  dilaporkan SKIP dan tidak dijalankan atau di-dry-run.
- Kandidat `ddl/03_functions.sql` sampai `ddl/06_views.sql` dan `dcl/01_roles.sql` sampai
  `dcl/03_hardening.sql` diuji bersama verifier dalam transaksi staging yang di-ROLLBACK:
  167 PASS, 0 FAIL. Setelah rollback, query katalog mengonfirmasi 0 role target dibuat.
  Ini bukan hasil aktual staging setelah penerapan permanen.
- Runtime smoke dalam rollback lulus untuk enam function, 13 view, empat procedure, audit trigger,
  pembuatan konfirmasi otomatis, dan penolakan perubahan konfirmasi periode tertutup.
- Contoh DML/TCL diuji pada klon disposable: DDL preflight dan `03`-`06`, seed, lima skrip TCL,
  query demo, lalu cleanup semuanya lulus. Setelah cleanup, jumlah siswa, akun demo, dan periode demo
  adalah `0|0|0`; klon dihapus. Uji awal menemukan kolom role yang salah, ketergantungan `pgcrypto`
  yang tidak tersedia, serta target `ON CONFLICT` tanpa unique constraint; seed telah diselaraskan
  dengan katalog dan diuji ulang hingga lulus.

## 3. Hasil lengkap `verify/01_uji_privilege.sql`

Verifier dijalankan pada staging dalam transaksi yang diakhiri `ROLLBACK`; exit code psql **0**.
Exit code 0 hanya berarti skrip selesai, bukan semua pemeriksaan lulus. Ringkasan hasil: **150 FAIL,
16 PASS** dari 166 pemeriksaan. Berikut keluaran lengkap yang ditangkap, termasuk semua baris FAIL
dan PASS:

```text
FAIL | 150
PASS | 16
1 | ROLE | role_siswa ada | ADA | TIDAK ADA | FAIL
4 | ROLE | role_tu ada | ADA | TIDAK ADA | FAIL
7 | ROLE | role_kepsek ada | ADA | TIDAK ADA | FAIL
10 | ROLE | role_wali_kelas ada | ADA | TIDAK ADA | FAIL
13 | ROLE | app_owner ada | ADA | TIDAK ADA | FAIL
16 | ROLE | app_login ada | ADA | TIDAK ADA | FAIL
18 | ROLE | app_login bisa login | YA | TIDAK | FAIL
20 | TULIS-AKADEMIK | role_tu INSERT pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
21 | TULIS-AKADEMIK | role_tu UPDATE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
22 | TULIS-AKADEMIK | role_tu DELETE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
23 | TULIS-AKADEMIK | role_kepsek INSERT pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
24 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
25 | TULIS-AKADEMIK | role_kepsek DELETE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
26 | TULIS-AKADEMIK | role_siswa INSERT pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
27 | TULIS-AKADEMIK | role_siswa UPDATE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
28 | TULIS-AKADEMIK | role_siswa DELETE pada public.nilai_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
29 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.nilai_siswa | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
30 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.nilai_siswa | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
31 | TULIS-AKADEMIK | role_tu INSERT pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
32 | TULIS-AKADEMIK | role_tu UPDATE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
33 | TULIS-AKADEMIK | role_tu DELETE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
34 | TULIS-AKADEMIK | role_kepsek INSERT pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
35 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
36 | TULIS-AKADEMIK | role_kepsek DELETE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
37 | TULIS-AKADEMIK | role_siswa INSERT pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
38 | TULIS-AKADEMIK | role_siswa UPDATE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
39 | TULIS-AKADEMIK | role_siswa DELETE pada public.sesi_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
40 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.sesi_absensi | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
41 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.sesi_absensi | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
42 | TULIS-AKADEMIK | role_tu INSERT pada public.detail_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
43 | TULIS-AKADEMIK | role_tu UPDATE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
44 | TULIS-AKADEMIK | role_tu DELETE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
45 | TULIS-AKADEMIK | role_kepsek INSERT pada public.detail_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
46 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
47 | TULIS-AKADEMIK | role_kepsek DELETE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
48 | TULIS-AKADEMIK | role_siswa INSERT pada public.detail_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
49 | TULIS-AKADEMIK | role_siswa UPDATE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
50 | TULIS-AKADEMIK | role_siswa DELETE pada public.detail_absensi | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
51 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.detail_absensi | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
52 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.detail_absensi | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
53 | TULIS-AKADEMIK | role_tu INSERT pada public.rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
54 | TULIS-AKADEMIK | role_tu UPDATE pada public.rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
55 | TULIS-AKADEMIK | role_tu DELETE pada public.rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
56 | TULIS-AKADEMIK | role_kepsek INSERT pada public.rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
57 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
58 | TULIS-AKADEMIK | role_kepsek DELETE pada public.rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
59 | TULIS-AKADEMIK | role_siswa INSERT pada public.rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
60 | TULIS-AKADEMIK | role_siswa UPDATE pada public.rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
61 | TULIS-AKADEMIK | role_siswa DELETE pada public.rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
62 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.rapor | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
63 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.rapor | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
64 | TULIS-AKADEMIK | role_tu INSERT pada public.detail_rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
65 | TULIS-AKADEMIK | role_tu UPDATE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
66 | TULIS-AKADEMIK | role_tu DELETE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
67 | TULIS-AKADEMIK | role_kepsek INSERT pada public.detail_rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
68 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
69 | TULIS-AKADEMIK | role_kepsek DELETE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
70 | TULIS-AKADEMIK | role_siswa INSERT pada public.detail_rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
71 | TULIS-AKADEMIK | role_siswa UPDATE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
72 | TULIS-AKADEMIK | role_siswa DELETE pada public.detail_rapor | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
73 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.detail_rapor | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
74 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.detail_rapor | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
75 | TULIS-AKADEMIK | role_tu INSERT pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
76 | TULIS-AKADEMIK | role_tu UPDATE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
77 | TULIS-AKADEMIK | role_tu DELETE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
78 | TULIS-AKADEMIK | role_kepsek INSERT pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
79 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
80 | TULIS-AKADEMIK | role_kepsek DELETE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
81 | TULIS-AKADEMIK | role_siswa INSERT pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
82 | TULIS-AKADEMIK | role_siswa UPDATE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
83 | TULIS-AKADEMIK | role_siswa DELETE pada public.catatan_wali_kelas | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
84 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.catatan_wali_kelas | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
85 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.catatan_wali_kelas | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
86 | TULIS-AKADEMIK | role_tu INSERT pada public.izin_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
87 | TULIS-AKADEMIK | role_tu UPDATE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
88 | TULIS-AKADEMIK | role_tu DELETE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
89 | TULIS-AKADEMIK | role_kepsek INSERT pada public.izin_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
90 | TULIS-AKADEMIK | role_kepsek UPDATE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
91 | TULIS-AKADEMIK | role_kepsek DELETE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
92 | TULIS-AKADEMIK | role_siswa INSERT pada public.izin_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
93 | TULIS-AKADEMIK | role_siswa UPDATE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
94 | TULIS-AKADEMIK | role_siswa DELETE pada public.izin_siswa | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
95 | TULIS-AKADEMIK | role_wali_kelas INSERT pada public.izin_siswa | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
96 | TULIS-AKADEMIK | role_wali_kelas UPDATE pada public.izin_siswa | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
97 | TULIS-AKADEMIK | role_wali_kelas DELETE pada public.nilai_siswa | ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
98 | BACA | role_tu SELECT pada public.nilai_siswa | ADA | ERROR: role "role_tu" does not exist | FAIL
99 | BACA | role_kepsek SELECT pada public.nilai_siswa | ADA | ERROR: role "role_kepsek" does not exist | FAIL
100 | LOG | role_siswa UPDATE pada public.log_audit | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
101 | LOG | role_siswa DELETE pada public.log_audit | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
102 | LOG | role_siswa TRUNCATE pada public.log_audit | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
103 | LOG | role_tu UPDATE pada public.log_audit | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
104 | LOG | role_tu DELETE pada public.log_audit | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
105 | LOG | role_tu TRUNCATE pada public.log_audit | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
106 | LOG | role_kepsek UPDATE pada public.log_audit | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
107 | LOG | role_kepsek DELETE pada public.log_audit | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
108 | LOG | role_kepsek TRUNCATE pada public.log_audit | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
109 | LOG | role_wali_kelas UPDATE pada public.log_audit | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
110 | LOG | role_wali_kelas DELETE pada public.log_audit | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
111 | LOG | role_wali_kelas TRUNCATE pada public.log_audit | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
112 | LOG | app_login UPDATE pada public.log_audit | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
113 | LOG | app_login DELETE pada public.log_audit | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
114 | LOG | app_login TRUNCATE pada public.log_audit | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
115 | LOG | role_siswa UPDATE pada public.log_login | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
116 | LOG | role_siswa DELETE pada public.log_login | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
117 | LOG | role_siswa TRUNCATE pada public.log_login | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
118 | LOG | role_tu UPDATE pada public.log_login | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
119 | LOG | role_tu DELETE pada public.log_login | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
120 | LOG | role_tu TRUNCATE pada public.log_login | TIDAK ADA | ERROR: role "role_tu" does not exist | FAIL
121 | LOG | role_kepsek UPDATE pada public.log_login | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
122 | LOG | role_kepsek DELETE pada public.log_login | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
123 | LOG | role_kepsek TRUNCATE pada public.log_login | TIDAK ADA | ERROR: role "role_kepsek" does not exist | FAIL
124 | LOG | role_wali_kelas UPDATE pada public.log_login | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
125 | LOG | role_wali_kelas DELETE pada public.log_login | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
126 | LOG | role_wali_kelas TRUNCATE pada public.log_login | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
127 | LOG | app_login UPDATE pada public.log_login | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
128 | LOG | app_login DELETE pada public.log_login | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
129 | LOG | app_login TRUNCATE pada public.log_login | TIDAK ADA | ERROR: role "app_login" does not exist | FAIL
130 | LOG | role_kepsek SELECT pada public.log_audit | ADA | ERROR: role "role_kepsek" does not exist | FAIL
131 | LOG | role_siswa SELECT pada public.log_audit | TIDAK ADA | ERROR: role "role_siswa" does not exist | FAIL
132 | LOG | role_wali_kelas SELECT pada public.log_audit | TIDAK ADA | ERROR: role "role_wali_kelas" does not exist | FAIL
133 | LOG | app_login INSERT pada public.log_login | ADA | ERROR: role "app_login" does not exist | FAIL
134 | KOLOM | role_siswa SELECT pengguna.password_hash | TIDAK ADA | ROLE TIDAK ADA | FAIL
135 | KOLOM | role_wali_kelas SELECT pengguna.password_hash | TIDAK ADA | ROLE TIDAK ADA | FAIL
137 | PUBLIC | EXECUTE sp_setujui_pengajuan untuk role_wali_kelas | ADA | ROLE TIDAK ADA | FAIL
138 | PUBLIC | EXECUTE sp_setujui_pengajuan untuk role_siswa | TIDAK ADA | ROLE TIDAK ADA | FAIL
139 | PUBLIC | EXECUTE sp_setujui_pengajuan untuk app_login | TIDAK ADA | ROLE TIDAK ADA | FAIL
140 | DEFINER | fn_audit_trail SECURITY DEFINER + search_path + pemilik app_owner | YA | TIDAK | FAIL
141 | DEFINER | sp_setujui_pengajuan SECURITY DEFINER + search_path + pemilik app_owner | YA | TIDAK | FAIL
142 | VIEW | view vw_* tanpa security_invoker | 0 | 6 | FAIL
146 | OBJEK | view (min 12) | CUKUP | KURANG: 6 | FAIL
147 | OBJEK | trigger (non-internal) (min 18) | CUKUP | KURANG: 10 | FAIL
148 | OBJEK | stored function (bukan trigger) (min 6) | CUKUP | KURANG: 0 | FAIL
149 | OBJEK | trigger function (min 5) | CUKUP | KURANG: 1 | FAIL
150 | OBJEK | stored procedure (min 4) | CUKUP | KURANG: 1 | FAIL
151 | AUDIT | trigger fn_audit_trail pada nilai_siswa | 1 | 0 | FAIL
152 | AUDIT | trigger fn_audit_trail pada rapor | 1 | 0 | FAIL
153 | AUDIT | trigger fn_audit_trail pada mutasi_siswa | 1 | 0 | FAIL
154 | AUDIT | trigger fn_audit_trail pada pendaftaran_ppdb | 1 | 0 | FAIL
155 | AUDIT | trigger fn_audit_trail pada konfirmasi_data | 1 | 0 | FAIL
156 | AUDIT | trigger fn_audit_trail pada periode_update | 1 | 0 | FAIL
157 | DML-NYATA | role_tu: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_ | DITOLAK | ROLE TIDAK ADA | FAIL
158 | DML-NYATA | role_kepsek: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_ | DITOLAK | ROLE TIDAK ADA | FAIL
159 | DML-NYATA | role_siswa: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_ | DITOLAK | ROLE TIDAK ADA | FAIL
160 | DML-NYATA | role_wali_kelas: INSERT INTO public.nilai_siswa (id_anggota, id_mapel, id_semester, id_ | LOLOS | ROLE TIDAK ADA | FAIL
161 | DML-NYATA | role_kepsek: UPDATE public.log_audit SET nama_tabel = nama_tabel WHERE false | DITOLAK | ROLE TIDAK ADA | FAIL
162 | DML-NYATA | role_siswa: SELECT password_hash FROM public.pengguna LIMIT 0 | DITOLAK | ROLE TIDAK ADA | FAIL
163 | DML-NYATA | role_wali_kelas: SELECT password_hash FROM public.pengguna LIMIT 0 | DITOLAK | ROLE TIDAK ADA | FAIL
164 | DML-NYATA | role_siswa: CALL public.sp_setujui_pengajuan(0, 0, NULL) | DITOLAK | ROLE TIDAK ADA | FAIL
165 | DML-NYATA | role_wali_kelas: CALL public.sp_setujui_pengajuan(0, 0, NULL) | LOLOS | ROLE TIDAK ADA | FAIL
166 | TRIGGER | DELETE log_audit oleh superuser | DIBLOKIR | TIDAK DIBLOKIR | FAIL
2 | ROLE | role_siswa bukan superuser | YA | YA | PASS
3 | ROLE | role_siswa bisa login | TIDAK | TIDAK | PASS
5 | ROLE | role_tu bukan superuser | YA | YA | PASS
6 | ROLE | role_tu bisa login | TIDAK | TIDAK | PASS
8 | ROLE | role_kepsek bukan superuser | YA | YA | PASS
9 | ROLE | role_kepsek bisa login | TIDAK | TIDAK | PASS
11 | ROLE | role_wali_kelas bukan superuser | YA | YA | PASS
12 | ROLE | role_wali_kelas bisa login | TIDAK | TIDAK | PASS
14 | ROLE | app_owner bukan superuser | YA | YA | PASS
15 | ROLE | app_owner bisa login | TIDAK | TIDAK | PASS
17 | ROLE | app_login bukan superuser | YA | YA | PASS
19 | ROLE | tidak ada role DB bernama guru | 0 | 0 | PASS
136 | PUBLIC | jumlah GRANT ke PUBLIC pada tabel/view skema public | 0 | 0 | PASS
143 | VIEW | kolom password_hash pada view | 0 | 0 | PASS
144 | OBJEK | tabel tanpa PRIMARY KEY | 0 | 0 | PASS
145 | OBJEK | FOREIGN KEY (min 83) | CUKUP | CUKUP | PASS
```

Keluaran di atas adalah verifier pada katalog staging yang benar-benar diterapkan. Sebagai pembanding,
kandidat DDL/DCL setelah perbaikan menghasilkan **167 PASS, 0 FAIL** saat diuji dalam transaksi yang
di-ROLLBACK; tidak ada role/object kandidat yang tertinggal. Kandidat itu belum mengubah hasil aktual
staging.

## 4. Hasil `php artisan test` dibanding baseline

Test dijalankan dengan environment proses diarahkan ke klon PostgreSQL disposable yang dibuat dari
arsip pre/post Tahap 1 dan data referensi. Kedua klon memiliki 72 tabel sebelum test. Test runner
mengubah schema klon (teardown menghapus sebagian tabel), sehingga klon tersebut tidak dipakai ulang.
`verify/test_klon.ps1` menjalankan `php artisan test --log-junit=<TEMP> --display-incomplete` dan
memverifikasi database Laravel efektif menunjuk ke klon. `phpunit.xml` dan `.env` tidak diubah.
Sesudah verifikasi, query baca-saja ke katalog `pg_database` dari staging menemukan **0** database
dengan prefix `fase1_test_` atau `fase1_catalog_pre_`.

| Eksekusi | Test runner | Passed | Failed | Errors | Incomplete | JUnit tests | JUnit nodes | Assertions | Durasi | Exit | JUnit SHA256 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Baseline pre-Tahap 1 | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 174 | 10.358 s | 2 | `e9a35aa1289304662cd094c36332fa735032ee79bcc83cb1fd9e5c289c986a86` |
| Post-Tahap 1 | 151 | 40 | 43 | 68 | 2 | 120 | 120 | 174 | 10.014 s | 2 | `8793e461c8964ef2ba82d3a4cdebd0be3863f1401e86ab83fd1f3145e8c8eb82` |

Ringkasan runner JSON melaporkan 151 test, sedangkan atribut JUnit suite melaporkan 120 test dan
XML memuat 120 node testcase (selisih runner/JUnit 31). Counter failure/error/incomplete runner
cocok dengan atribut suite JUnit (43/68/2). Dua incomplete `UploadBerkasAuditFeatureTest` berasal
dari `markTestIncomplete()` di source; JUnit tidak menyediakan node testcase yang dapat digunakan
untuk mengidentifikasi keduanya. Selisih 31 dicatat apa adanya, tidak ditafsirkan sebagai test yang
lulus atau hilang tanpa bukti.

Perbandingan identifier fail/error menunjukkan **0 test baru gagal/error** dan **0 terselesaikan**.
Dengan demikian tidak ada akar masalah regresi untuk dianalisis. Error baseline yang masih terjadi:

- 43 assertion pada `PpdbFormulirFeatureTest` gagal di assertion setup baris 24: test mensyaratkan
  driver `sqlite`, sementara klon uji memang menggunakan `pgsql`.
- 60 error teardown gagal menghapus tabel dengan dependency: 20 pada `guru`, 20 pada `siswa`,
  7 pada `rapor`, 6 pada `status_siswa`, 5 pada `nilai_siswa`, dan 2 pada `detail_absensi`.
- 7 error fixture membuat tabel `role` yang sudah ada.
- 1 error teardown menghapus `semester` saat masih direferensikan FK `jadwal_pelajaran`.

Sebanyak 47 diagnosis berubah pada identifier error yang sama (20 `guru`, 20 `siswa`, 6
`status_siswa`, 1 `semester`); ini bukan test baru gagal. Enam error `MutasiSiswaWorkflowTest`
memiliki nama yang sama sebelum dan sesudah Tahap 1, tetapi objek yang menghalangi teardown berubah:

| Test | Baseline | Sesudah Tahap 1 | Akar masalah |
| --- | --- | --- | --- |
| `it_registers_incoming_students_directly_for_TU_and_assigns_the_selected_active_class` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | teardown menjatuhkan parent sebelum tabel `siswa` yang kini memiliki FK `siswa_id_status_siswa_fkey` |
| `it_sends_outgoing_requests_to_the_principal_and_stores_their_attachment` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | sama: urutan drop tidak mengikuti dependensi FK |
| `it_restricts_outgoing_decisions_to_the_principal_and_requires_a_rejection_note` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | sama |
| `it_records_the_principal_rejection_note_and_notifies_the_requesting_TU` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | sama |
| `it_updates_student_status_and_class_history_when_the_principal_approves_exit_cases` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | sama |
| `it_allows_TU_to_revise_and_cancel_only_pending_outgoing_requests` | drop `keanggotaan_kelas` gagal | drop `status_siswa` gagal | sama |

Ini bukan enam test baru gagal: keenamnya sudah error pada baseline. FK baru membuat error berikutnya
yang tampak pada teardown bergeser dari `keanggotaan_kelas` ke `status_siswa`. Perbedaan seluruh 47
diagnosis dan daftar test tersanitasi ada di [FASE1_BASELINE.md](./FASE1_BASELINE.md) dan
[FASE1_BASELINE_GAGAL.txt](./FASE1_BASELINE_GAGAL.txt).

## 5. ORPHAN_REPORT

Audit anti-join atas seluruh 96 FK menemukan 93 FK `VALID`, 3 FK `NOT VALID`, serta tepat 3 baris
yatim. Seluruh 83 FK dalam `schema_backup.sql` cocok dengan katalog staging; terdapat 13 FK tambahan
di katalog. Masing-masing FK yatim berikut memiliki satu baris:

| FK | Child | Parent | Status | Yatim |
| --- | --- | --- | --- | ---: |
| `log_login_id_pengguna_fkey` | `log_login` | `pengguna` | NOT VALID | 1 |
| `notifikasi_id_pengguna_fkey` | `notifikasi` | `pengguna` | NOT VALID | 1 |
| `siswa_id_pengguna_fkey` | `siswa` | `pengguna` | NOT VALID | 1 |

Daftar seluruh 96 FK, status validasi, dan orphan per FK ada di
[ORPHAN_REPORT.md](./ORPHAN_REPORT.md), termasuk kolom child/parent, `ON DELETE`, kecocokan baseline,
status `MATCH`/validasi, jumlah orphan, dan catatan kebijakan. Audit yang dapat dijalankan ulang:
`verify/02_orphan_audit.sql` melalui `verify/hasilkan_orphan_report.ps1`. Tiga baris yatim dibiarkan
dan tidak dikoreksi otomatis.

## 6. TODO-REVIEW

- **Tinjau tiga FK yatim `NOT VALID`:** tentukan keputusan bisnis untuk orphan pada
  `log_login`, `notifikasi`, dan `siswa`; jangan validasi sebelum data dibersihkan dan hasil anti-join
  menjadi nol.
- **Tinjau tiga UNIQUE yang dilewatkan:** konfirmasi aturan kelas per tahun ajaran, riwayat/multiple
  keanggotaan siswa, dan kunci unik penugasan wali kelas sebelum mengubah pengecualian.
- **Penerapan kandidat belum dilakukan:** logic, view, trigger, role, dan grants lulus dry-run serta
  verifier transaksional, tetapi staging aktual belum memuat perubahan tersebut. Tinjau diff SQL dan
  rencana penerapan sebelum mengubah staging; jangan menyamakan hasil rollback dengan penerapan.
- **Kredensial `app_login`:** role kandidat dibuat dengan `LOGIN` tanpa password. Provisioning
  kredensial harus dilakukan melalui proses rahasia deployment, bukan ditambahkan ke SQL atau laporan.
- **Stored logic:** smoke runtime kandidat lulus pada rollback, tetapi logic dan grants belum tersimpan
  permanen; ulangi smoke test setelah penerapan staging yang disetujui.
- **Verifier staging:** 150 FAIL/16 PASS saat ini mencerminkan role dan logic kandidat yang belum
  diterapkan; 167 PASS/0 FAIL hanya hasil kandidat transaksional.
- **Kecocokan report test:** ringkasan runner mencatat 151 test, tetapi JUnit memuat 120 node;
  counter failure/error/incomplete cocok, sedangkan selisih 31 node belum dapat dijelaskan dari XML.
  Jangan anggap daftar testcase lulus lengkap sampai perilaku serialisasi reporter ditinjau.
- **Regresi test:** perbaiki teardown `MutasiSiswaWorkflowTest` agar menjatuhkan tabel sesuai dependensi
  FK, dan putuskan konfigurasi test driver agar tidak mengharapkan SQLite saat memakai PostgreSQL.
- **DML/TCL demo:** satu siklus seed, lima TCL, query, dan cleanup lulus pada klon terisolasi;
  jalankan ulang pada klon baru setelah perubahan script, jangan pada staging atau database utama.
- **Perubahan fase aplikasi:** jangan menghapus role `guru` sebelum migrasi referensi kode Fase 2.

## 7. Temuan Fase 2 dari pemeriksaan kode

- Ada 8 titik join `status_siswa`: 2 pada `AksiFormController`, 5 pada `PageController`, dan 1 pada
  `SiswaController`. Tidak ada PHP yang diubah pada pekerjaan ini.
- [routes/web.php](../../routes/web.php) dan [Pengguna.php](../../app/Models/Pengguna.php) masih
  merujuk role aplikasi `guru`. Jangan menghapus role terkait sebelum rujukan tersebut dimigrasikan.
- [EnsureRole.php](../../app/Http/Middleware/EnsureRole.php) memuat model pengguna tanpa memilih kolom,
  sehingga query mengambil `password_hash`; `$hidden` hanya mempengaruhi serialisasi, bukan SQL SELECT.
  Ini perlu diselaraskan dengan privilege kolom.
- [SetAuditContext.php](../../app/Http/Middleware/SetAuditContext.php) menggunakan
  `set_config(..., false)` (nilai session) dan membersihkan konteks di `terminate()`, sementara
  [AuditContext.php](../../app/Support/AuditContext.php) memakai konteks lokal transaksi. Lifecycle
  koneksi persisten serta jalur exception perlu diuji pada fase berikutnya.

## 8. Langkah menjalankan dari nol dengan aman

Langkah berikut mereproduksi bagian Tahap 1 yang benar-benar dijalankan. Kandidat DDL/DCL lulus
dry-run tetapi belum diterapkan permanen; perlakukan penerapannya sebagai gate terpisah setelah review
dan provisioning kredensial. Semua perintah harus memakai staging atau klon disposable; database
utama tidak menjadi target.

1. **Buat dan verifikasi backup staging sebelum perubahan.**

   ```powershell
   $preDump = Join-Path $env:TEMP ("fase1_staging_pre_stage1_{0}.dump" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
   .\database\sql\verify\backup_staging.ps1 -OutputPath $preDump
   ```

   Script hanya menerima staging, memuat koneksi dari `.env` tanpa mencetak credential, membuat dump
   custom-format di `%TEMP%`, lalu memverifikasi TOC dengan `pg_restore -l`. Hentikan jika verifikasi
   gagal. Sebelum staging diubah, buat hasil baseline test pada klon disposable (runner saat ini
   keluar dengan kode 2 karena baseline memang memiliki failure/error; catat kode itu):

   ```powershell
   powershell.exe -NoProfile -File .\database\sql\verify\test_klon.ps1 -SourceDump $preDump -JunitPath (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_baseline.log')
   ```

2. **Konfirmasi target dan jalankan preflight read-only.**

   ```powershell
   psql -X -w -v ON_ERROR_STOP=1 -d msbd_tubes_kel7_staging -c "SELECT current_database(), current_user, version();"
   .\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\ddl\00_preflight_audit.sql
   ```

   Pastikan `current_database()` tepat `msbd_tubes_kel7_staging`. Jalankan audit duplikasi/null PK,
   UNIQUE, CHECK, dan orphan sebelum DDL.

3. **Dry-run perbaikan status dan DDL/DCL yang sudah ditinjau.**

   ```powershell
   .\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\dml\00_repair_status_ids.sql -DryRun
   .\database\sql\dry_run.ps1
   ```

   Run terkini menghasilkan 10 PASS dan 0 FAIL (exit code 0). `dcl/90_hapus_role_guru.sql`
   dilaporkan SKIP; jangan menjalankan atau men-dry-run file itu.

4. **Terapkan perbaikan data status dan constraint yang lolos hanya ke staging.**

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
   ```

   Wrapper memvalidasi `FASE1_TARGET_DB` dan `current_database()`. Jangan ubah `.env`; variabel
   tersebut hanya untuk proses PowerShell saat migration.

5. **Sinkronkan sequence setelah PK terpasang dan verifikasi.**

   ```powershell
   .\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\dml\01_sync_sequences.sql
   ```

   Jangan membungkus `setval()` dalam dry-run/ROLLBACK karena perubahan sequence tidak dibatalkan
   oleh transaksi. Jalankan query verifikasi sequence, lalu pastikan 0 mismatch.

6. **Jalankan audit pasca-eksekusi.**

   ```powershell
   .\database\sql\jalankan.ps1 -Db msbd_tubes_kel7_staging -File database\sql\verify\01_uji_privilege.sql
   .\database\sql\verify\hasilkan_objek.ps1 -BeforeDump $preDump
   .\database\sql\verify\hasilkan_orphan_report.ps1
   ```

   Simpan semua output verifier, termasuk FAIL. Verifier pada staging yang benar-benar diterapkan
   menghasilkan 150 FAIL/16 PASS; kandidat logic/role/grants diuji terpisah dalam transaksi rollback
   dan menghasilkan 167 PASS/0 FAIL. Orphan report tetap menunjukkan tiga orphan pada tiga FK
   `NOT VALID`; jangan menyatakan staging lulus berdasarkan kandidat rollback.

7. **Jalankan test hanya pada klon disposable.**

   Jalankan runner sebagai proses PowerShell anak; exit 2 saat ini berasal dari kegagalan baseline
   yang sudah tercatat. Runner tetap memverifikasi koneksi Laravel ke klon serta menghapus klon dan
   dump sementara dalam `finally`.

   ```powershell
   powershell.exe -NoProfile -File .\database\sql\verify\test_klon.ps1 -SourceDatabase msbd_tubes_kel7_staging -JunitPath (Join-Path $env:TEMP 'fase1_post.junit.xml') -LogPath (Join-Path $env:TEMP 'fase1_post.log')
   ```

   Untuk baseline dari arsip pre-stage, gunakan `-SourceDump $preDump` seperti langkah 1. Setelah
   kedua run selesai, bandingkan JUnit dan log runner:

   ```powershell
   .\database\sql\verify\bandingkan_test.ps1 -BeforeJunit (Join-Path $env:TEMP 'fase1_baseline.junit.xml') -AfterJunit (Join-Path $env:TEMP 'fase1_post.junit.xml') -BeforeLog (Join-Path $env:TEMP 'fase1_baseline.log') -AfterLog (Join-Path $env:TEMP 'fase1_post.log')
   ```

   Suite mengubah schema klon ketika teardown gagal; jangan jalankan langsung pada staging, database
   utama, atau klon yang akan dipakai ulang. `phpunit.xml` dan `.env` tidak diubah.

8. **Kriteria sebelum fase berikutnya:** selesaikan TODO-REVIEW, putuskan orphan/constraint yang
   dikecualikan, provision kredensial melalui kanal rahasia, dan terapkan kandidat staging hanya
   setelah persetujuan. Setelah itu ulangi audit katalog, verifier, orphan, sequence, runtime smoke,
   contoh DML/TCL dan test clone. Tidak ada langkah di sini yang mengotorisasi penerapan ke
   `msbd_tubes_kel7`.

