<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

it('redirects an active account to its role dashboard and records the successful login', function (
    string $role,
    string $username,
    string $dashboardRoute,
    string $profileTable,
    string $profileNameColumn,
    string $displayName,
): void {
    Schema::create('role', function (Blueprint $table): void {
        $table->id('id_role');
        $table->string('nama_role');
        $table->string('kode_role');
    });

    Schema::create('pengguna', function (Blueprint $table): void {
        $table->id('id_pengguna');
        $table->unsignedBigInteger('id_role');
        $table->string('username')->unique();
        $table->string('password_hash');
        $table->boolean('is_aktif')->default(true);
        $table->timestamp('terakhir_login')->nullable();
        $table->timestamp('diubah_pada')->nullable();
    });

    Schema::create('pengaturan_sistem', function (Blueprint $table): void {
        $table->id('id_pengaturan_sistem');
        $table->string('kunci');
        $table->text('nilai')->nullable();
    });

    Schema::create('log_login', function (Blueprint $table): void {
        $table->id('id_log_login');
        $table->unsignedBigInteger('id_pengguna')->nullable();
        $table->string('username_dicoba');
        $table->boolean('berhasil');
        $table->string('ip_address')->nullable();
        $table->string('user_agent')->nullable();
        $table->timestamp('waktu');
    });

    Schema::create('siswa', function (Blueprint $table): void {
        $table->id('id_siswa');
        $table->unsignedBigInteger('id_pengguna')->nullable();
        $table->string('nama_lengkap');
    });

    Schema::create('staf', function (Blueprint $table): void {
        $table->id('id_staf');
        $table->unsignedBigInteger('id_pengguna')->nullable();
        $table->string('nama_staf');
    });

    Schema::create('guru', function (Blueprint $table): void {
        $table->id('id_guru');
        $table->unsignedBigInteger('id_pengguna')->nullable();
        $table->string('nama_guru');
    });

    $roleId = DB::table('role')->insertGetId([
        'nama_role' => $role,
        'kode_role' => $role,
    ]);

    $password = 'Demo-Password-123';
    $userId = DB::table('pengguna')->insertGetId([
        'id_role' => $roleId,
        'username' => $username,
        'password_hash' => Hash::make($password),
        'is_aktif' => true,
    ]);

    DB::table($profileTable)->insert([
        'id_pengguna' => $userId,
        $profileNameColumn => $displayName,
    ]);

    $response = $this->post(route('login.proses'), [
        'username' => $username,
        'password' => $password,
    ]);

    $response->assertRedirect(route($dashboardRoute));
    $response->assertSessionHasAll([
        'id_pengguna' => $userId,
        'username' => $username,
        'role' => $role,
        'nama' => $displayName,
    ]);

    $this->assertDatabaseHas('log_login', [
        'id_pengguna' => $userId,
        'username_dicoba' => $username,
        'berhasil' => true,
    ]);
})->with([
    'student' => ['siswa', 'demo_siswa', 'siswa.dashboard', 'siswa', 'nama_lengkap', 'Siswa Demo'],
    'guardian teacher' => ['wali_kelas', 'demo_wali_kelas', 'guru.kelas', 'guru', 'nama_guru', 'Wali Kelas Demo'],
    'administrative staff' => ['tu', 'demo_tu', 'tu.dashboard', 'staf', 'nama_staf', 'Staf TU Demo'],
    'principal' => ['kepsek', 'demo_kepsek', 'kepsek.dashboard', 'staf', 'nama_staf', 'Kepala Sekolah Demo'],
]);

it('allows every self-service role to change its own password', function (string $role) {
    Schema::create('role', function (Blueprint $table): void {
        $table->id('id_role');
        $table->string('nama_role');
        $table->string('kode_role');
    });

    Schema::create('pengguna', function (Blueprint $table): void {
        $table->id('id_pengguna');
        $table->unsignedBigInteger('id_role');
        $table->string('username')->unique();
        $table->string('password_hash');
        $table->boolean('is_aktif')->default(true);
        $table->timestamp('terakhir_login')->nullable();
        $table->timestamp('diubah_pada')->nullable();
    });

    Schema::create('log_audit', function (Blueprint $table): void {
        $table->id('id_log');
        $table->string('nama_tabel');
        $table->string('operasi');
        $table->string('id_data')->nullable();
        $table->json('nilai_lama')->nullable();
        $table->json('nilai_baru')->nullable();
        $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
        $table->string('username_pengubah')->nullable();
        $table->timestamp('waktu');
    });

    $roleId = DB::table('role')->insertGetId([
        'nama_role' => $role,
        'kode_role' => $role,
    ]);
    $username = 'demo_'.$role;
    $userId = DB::table('pengguna')->insertGetId([
        'id_role' => $roleId,
        'username' => $username,
        'password_hash' => Hash::make('Demo-Password-123'),
        'is_aktif' => true,
    ]);

    $response = $this->withSession([
        'id_pengguna' => $userId,
        'username' => $username,
        'role' => $role,
    ])->post(route('akun.sandi.update'), [
        'sandi_lama' => 'Demo-Password-123',
        'sandi_baru' => 'Updated-Password-456',
        'sandi_ulang' => 'Updated-Password-456',
    ]);

    $response->assertRedirect(route('akun.sandi'));
    expect(Hash::check('Updated-Password-456', DB::table('pengguna')->where('id_pengguna', $userId)->value('password_hash')))
        ->toBeTrue();
    $this->assertNotNull(DB::table('pengguna')->where('id_pengguna', $userId)->value('diubah_pada'));
})->with(['siswa', 'wali_kelas', 'tu', 'kepsek']);
