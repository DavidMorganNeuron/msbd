<?php

use Database\Seeders\DemoRoleAccountsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
    Config::set('demo.role_password', str_repeat('D', 24));

    Schema::dropIfExists('siswa');
    Schema::dropIfExists('status_siswa');
    Schema::dropIfExists('pengguna');
    Schema::dropIfExists('role');

    Schema::create('role', function (Blueprint $table) {
        $table->id('id_role');
        $table->string('nama_role')->unique();
        $table->string('deskripsi')->nullable();
    });

    Schema::create('pengguna', function (Blueprint $table) {
        $table->id('id_pengguna');
        $table->unsignedBigInteger('id_role');
        $table->string('username')->unique();
        $table->string('password_hash');
        $table->boolean('is_aktif')->default(true);
        $table->timestamp('terakhir_login')->nullable();
        $table->timestamp('dibuat_pada')->nullable();
        $table->timestamp('diubah_pada')->nullable();
    });

    Schema::create('status_siswa', function (Blueprint $table) {
        $table->id('id_status_siswa');
        $table->string('kode_status')->unique();
    });

    Schema::create('siswa', function (Blueprint $table) {
        $table->id('id_siswa');
        $table->unsignedBigInteger('id_pengguna')->nullable()->unique();
        $table->unsignedBigInteger('id_status_siswa')->nullable();
        $table->string('nis')->nullable()->unique();
        $table->string('nisn')->nullable()->unique();
        $table->string('nama_lengkap');
        $table->char('jenis_kelamin', 1)->nullable();
        $table->timestamp('dibuat_pada')->nullable();
        $table->timestamp('diubah_pada')->nullable();
    });
});

it('creates idempotent demo accounts and links the student account to its student record', function () {
    DB::table('role')->insert([
        ['id_role' => 1, 'nama_role' => 'siswa'],
        ['id_role' => 2, 'nama_role' => 'tu'],
        ['id_role' => 3, 'nama_role' => 'kepsek'],
        ['id_role' => 4, 'nama_role' => 'wali_kelas'],
    ]);
    DB::table('status_siswa')->insert(['id_status_siswa' => 1, 'kode_status' => 'aktif']);

    $this->seed(DemoRoleAccountsSeeder::class);
    $this->seed(DemoRoleAccountsSeeder::class);

    $studentUser = DB::table('pengguna')->where('username', 'demo_siswa')->first();

    $this->assertDatabaseCount('pengguna', 4);
    $this->assertDatabaseCount('siswa', 1);
    $this->assertDatabaseHas('pengguna', ['username' => 'demo_tu', 'id_role' => 2, 'is_aktif' => true]);
    $this->assertDatabaseHas('pengguna', ['username' => 'demo_kepsek', 'id_role' => 3, 'is_aktif' => true]);
    $this->assertDatabaseHas('pengguna', ['username' => 'demo_wali_kelas', 'id_role' => 4, 'is_aktif' => true]);
    $this->assertDatabaseHas('siswa', [
        'id_pengguna' => $studentUser->id_pengguna,
        'id_status_siswa' => 1,
        'nis' => 'DEMO-0001',
        'nisn' => '9999999999',
    ]);

    expect(Hash::check(str_repeat('D', 24), $studentUser->password_hash))->toBeTrue();
});

it('refuses to create demo accounts outside the local environment', function () {
    app()->detectEnvironment(fn () => 'testing');

    expect(fn () => $this->seed(DemoRoleAccountsSeeder::class))
        ->toThrow(RuntimeException::class, 'Akun demo hanya boleh dibuat');

    $this->assertDatabaseCount('pengguna', 0);
    $this->assertDatabaseCount('siswa', 0);
});

it('requires a non-empty demo password before creating accounts', function () {
    Config::set('demo.role_password', '');

    expect(fn () => $this->seed(DemoRoleAccountsSeeder::class))
        ->toThrow(RuntimeException::class, 'DEMO_ROLE_PASSWORD');

    $this->assertDatabaseCount('pengguna', 0);
    $this->assertDatabaseCount('siswa', 0);
});

it('rolls back demo accounts when the demo student identity belongs to another account', function () {
    DB::table('role')->insert([
        ['id_role' => 1, 'nama_role' => 'siswa'],
        ['id_role' => 2, 'nama_role' => 'tu'],
        ['id_role' => 3, 'nama_role' => 'kepsek'],
        ['id_role' => 4, 'nama_role' => 'wali_kelas'],
    ]);
    DB::table('status_siswa')->insert(['id_status_siswa' => 1, 'kode_status' => 'aktif']);
    DB::table('pengguna')->insert([
        'id_pengguna' => 99,
        'id_role' => 1,
        'username' => 'existing_student',
        'password_hash' => 'existing-hash',
        'is_aktif' => true,
    ]);
    DB::table('siswa')->insert([
        'id_siswa' => 99,
        'id_pengguna' => 99,
        'nis' => 'OTHER-0001',
        'nisn' => '9999999999',
        'nama_lengkap' => 'Siswa Existing',
    ]);

    expect(fn () => $this->seed(DemoRoleAccountsSeeder::class))
        ->toThrow(RuntimeException::class, 'Identitas siswa demo sudah terhubung');

    $this->assertDatabaseCount('pengguna', 1);
    $this->assertDatabaseMissing('pengguna', ['username' => 'demo_siswa']);
    $this->assertDatabaseMissing('pengguna', ['username' => 'demo_tu']);
    $this->assertDatabaseMissing('pengguna', ['username' => 'demo_kepsek']);
    $this->assertDatabaseMissing('pengguna', ['username' => 'demo_wali_kelas']);
});
