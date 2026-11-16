# Matriks privilege

| Role | Select | Insert | Update | Delete | Execute |
| --- | --- | --- | --- | --- | --- |
| app_login | `pengguna`, `role`, `role_hak_akses`, `hak_akses`; infra tables | `log_login` | - | - | - |
| role_siswa | data miliknya, rapor, nilai | `pengajuan_perubahan`, `detail_pengajuan` | `konfirmasi_data`, `password_hash` milik sendiri | - | `sp_setujui_pengajuan` if allowed by flow |
| role_tu | CRUD master dan referensi | kami | pembaruan master | - | - |
| role_kepsek | read dashboards and user management | `pengguna` / `staf` / `guru` | admin user | - | - |
| role_wali_kelas | kelas dan siswa | `nilai_siswa`, `rapor`, `detail_rapor`, `catatan_wali_kelas` | `pengajuan_perubahan`, `detail_pengajuan` | - | `sp_setujui_pengajuan` |

TODO-REVIEW: confirm final role matrix once PHP references and web routes are audited in Fase 2.
