-- Minimal privileges aligned to the school workflow.

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO app_login, app_owner, role_siswa, role_tu, role_kepsek, role_wali_kelas;

REVOKE SELECT ON TABLE public.pengguna FROM role_siswa, role_tu, role_kepsek, role_wali_kelas;
GRANT SELECT ON TABLE public.siswa, public.role, public.role_hak_akses, public.hak_akses
    TO role_siswa, role_tu, role_kepsek, role_wali_kelas;
GRANT SELECT (id_pengguna, id_role, username, is_aktif, terakhir_login, dibuat_pada, diubah_pada)
    ON TABLE public.pengguna TO role_siswa, role_tu, role_kepsek, role_wali_kelas;
GRANT SELECT ON TABLE public.pengguna TO app_login;

REVOKE INSERT, UPDATE, DELETE ON TABLE
    public.nilai_siswa, public.sesi_absensi, public.detail_absensi, public.rapor,
    public.detail_rapor, public.catatan_wali_kelas, public.izin_siswa
    FROM role_siswa, role_tu, role_kepsek, role_wali_kelas, app_login;
GRANT INSERT, UPDATE ON TABLE
    public.nilai_siswa, public.sesi_absensi, public.detail_absensi, public.rapor,
    public.detail_rapor, public.catatan_wali_kelas, public.izin_siswa
    TO role_wali_kelas;
GRANT DELETE ON TABLE public.nilai_siswa TO role_wali_kelas;
GRANT SELECT ON TABLE public.nilai_siswa TO role_siswa, role_tu, role_kepsek, role_wali_kelas;

REVOKE ALL ON TABLE public.log_audit, public.log_login
    FROM role_siswa, role_tu, role_kepsek, role_wali_kelas, app_login, PUBLIC;
GRANT INSERT ON TABLE public.log_audit TO app_owner;
GRANT SELECT ON TABLE public.log_audit TO role_kepsek;
GRANT INSERT ON TABLE public.log_login TO app_login;

GRANT SELECT ON TABLE public.konfirmasi_data TO role_siswa, role_tu, role_kepsek, role_wali_kelas;
GRANT INSERT, UPDATE ON TABLE public.pengajuan_perubahan TO role_siswa;
GRANT UPDATE ON TABLE public.pengajuan_perubahan TO role_wali_kelas;

GRANT SELECT ON TABLE
    public.pengajuan_perubahan, public.detail_pengajuan, public.ref_field_data,
    public.pengguna, public.siswa, public.alamat_siswa, public.orang_tua_wali
    TO app_owner;
GRANT UPDATE ON TABLE
    public.pengajuan_perubahan, public.siswa, public.alamat_siswa, public.orang_tua_wali
    TO app_owner;
GRANT INSERT ON TABLE public.alamat_siswa, public.orang_tua_wali TO app_owner;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_owner;

ALTER FUNCTION public.fn_audit_trail() OWNER TO app_owner;
ALTER PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT) OWNER TO app_owner;
REVOKE ALL ON PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT)
    FROM PUBLIC, role_siswa, role_tu, role_kepsek, app_login;
GRANT EXECUTE ON PROCEDURE public.sp_setujui_pengajuan(BIGINT, BIGINT, TEXT)
    TO role_wali_kelas;

REVOKE ALL ON FUNCTION public.fn_predikat_nilai(NUMERIC) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.fn_predikat_nilai(NUMERIC)
    TO role_wali_kelas, role_tu, role_kepsek, role_siswa;
