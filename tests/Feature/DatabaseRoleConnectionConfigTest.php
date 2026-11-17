<?php

it('defines all role-specific PostgreSQL connections with the expected pgsql driver and env usernames', function () {
    $roles = [
        'pgsql' => 'DB_USERNAME',
        'pgsql_owner' => 'DB_USERNAME_OWNER',
        'pgsql_siswa' => 'DB_USERNAME_SISWA',
        'pgsql_tu' => 'DB_USERNAME_TU',
        'pgsql_kepsek' => 'DB_USERNAME_KEPSEK',
        'pgsql_wali_kelas' => 'DB_USERNAME_WALI_KELAS',
    ];

    foreach ($roles as $connectionName => $envKey) {
        expect(config("database.connections.{$connectionName}.driver"))->toBe('pgsql');
        expect(config("database.connections.{$connectionName}.username"))->toBe(env($envKey, ''));
    }
});

it('grants the student role access to PPDB profile lookup columns', function () {
    $privileges = file_get_contents(base_path('database/sql/dcl/02_privileges.sql'));

    expect($privileges)
        ->toBeString()
        ->toContain('GRANT SELECT (id_agama, nama_agama, is_aktif) ON TABLE public.agama TO role_siswa;')
        ->toContain('GRANT SELECT (id_pekerjaan, nama_pekerjaan) ON TABLE public.pekerjaan TO role_siswa;');
});

it('grants the database roles the minimum privileges required by change requests and public PPDB locations', function () {
    $privileges = file_get_contents(base_path('database/sql/dcl/02_privileges.sql'));

    expect($privileges)
        ->toBeString()
        ->toContain('GRANT SELECT ON TABLE public.periode_update TO role_siswa;')
        ->toContain('GRANT SELECT (id_guru, id_pengguna, is_aktif, nama_guru) ON TABLE public.guru TO role_wali_kelas;')
        ->toContain('GRANT SELECT (boleh_diubah_siswa, label_field, nama_kolom) ON TABLE public.ref_field_data TO role_wali_kelas;')
        ->toContain('GRANT SELECT ON TABLE public.kota TO app_login;')
        ->toContain('GRANT SELECT ON TABLE public.provinsi TO app_login;');
});

it('grants only the required writes for password changes, student periods, and homeroom notes', function () {
    $privileges = file_get_contents(base_path('database/sql/dcl/02_privileges.sql'));

    expect($privileges)
        ->toBeString()
        ->toContain('GRANT UPDATE (password_hash, diubah_pada) ON TABLE public.pengguna TO role_siswa;')
        ->toContain('GRANT UPDATE (password_hash, diubah_pada) ON TABLE public.pengguna TO role_kepsek;')
        ->toContain('GRANT UPDATE (password_hash, diubah_pada) ON TABLE public.pengguna TO role_wali_kelas;')
        ->toContain('GRANT INSERT (id_tahun_ajaran, nama_periode, tanggal_mulai, tanggal_selesai, status, dibuat_oleh) ON TABLE public.periode_update TO role_tu;')
        ->toContain('GRANT UPDATE (status) ON TABLE public.periode_update TO role_tu;')
        ->toContain('GRANT INSERT (id_pengguna, judul, pesan, dibaca, dibuat_pada) ON TABLE public.notifikasi TO role_wali_kelas;');
});

it('routes student change-request inserts through the owned security-definer function', function () {
    $privileges = file_get_contents(base_path('database/sql/dcl/02_privileges.sql'));

    expect($privileges)
        ->toBeString()
        ->toContain('GRANT USAGE ON SCHEMA app_owner TO role_siswa;')
        ->toContain('GRANT EXECUTE ON FUNCTION app_owner.fn_buat_pengajuan_perubahan(bigint,bigint,text) TO role_siswa;')
        ->toContain('ALTER FUNCTION app_owner.fn_buat_pengajuan_perubahan(bigint,bigint,text) OWNER TO app_owner;')
        ->toContain('GRANT INSERT, SELECT, UPDATE ON TABLE public.pengajuan_perubahan TO app_owner;')
        ->not->toContain('GRANT INSERT, SELECT, UPDATE ON TABLE public.pengajuan_perubahan TO role_siswa;');
});

it('grants public PPDB writes and column-limited principal account creation', function () {
    $privileges = file_get_contents(base_path('database/sql/dcl/02_privileges.sql'));

    expect($privileges)
        ->toBeString()
        ->toContain('GRANT INSERT, SELECT, UPDATE ON TABLE public.pendaftaran_ppdb TO app_login;')
        ->toContain('GRANT DELETE, INSERT, SELECT, UPDATE ON TABLE public.ppdb_orang_tua TO app_login;')
        ->toContain('GRANT USAGE ON SEQUENCE public.pendaftaran_ppdb_id_pendaftaran_seq TO app_login;')
        ->toContain('GRANT SELECT (id_siswa, id_pengguna, id_status_siswa, nama_lengkap, nisn, nik) ON TABLE public.siswa TO app_login;')
        ->toContain('GRANT SELECT (id_sekolah, nama_sekolah) ON TABLE public.sekolah TO app_login;')
        ->toContain('GRANT INSERT (id_role, username, password_hash, is_aktif, dibuat_pada, diubah_pada) ON TABLE public.pengguna TO role_kepsek;')
        ->toContain('GRANT INSERT (id_pengguna, nama_guru, nip, no_telepon, is_aktif) ON TABLE public.guru TO role_kepsek;')
        ->toContain('GRANT INSERT (id_pengguna, nama_staf, jabatan, no_telepon, is_aktif) ON TABLE public.staf TO role_kepsek;')
        ->not->toContain('GRANT INSERT ON TABLE public.pengguna TO role_kepsek;')
        ->not->toContain('GRANT INSERT ON TABLE public.guru TO role_kepsek;')
        ->not->toContain('GRANT INSERT ON TABLE public.staf TO role_kepsek;')
        ->not->toContain('GRANT SELECT (id_pengguna, id_role, username, password_hash) ON TABLE public.pengguna TO role_kepsek;');
});
