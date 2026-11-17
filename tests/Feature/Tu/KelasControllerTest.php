<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::disableForeignKeyConstraints();
    foreach (['log_audit', 'penugasan_wali_kelas', 'kelas', 'semester', 'tahun_ajaran', 'guru', 'pengguna', 'role'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::enableForeignKeyConstraints();

    Schema::create('role', function (Blueprint $table): void {
        $table->id('id_role');
        $table->string('nama_role');
        $table->string('kode_role');
    });
    Schema::create('pengguna', function (Blueprint $table): void {
        $table->id('id_pengguna');
        $table->unsignedBigInteger('id_role');
        $table->boolean('is_aktif');
    });
    Schema::create('guru', function (Blueprint $table): void {
        $table->id('id_guru');
        $table->unsignedBigInteger('id_pengguna');
        $table->string('nama_guru');
        $table->boolean('is_aktif');
    });
    Schema::create('tahun_ajaran', function (Blueprint $table): void {
        $table->id('id_tahun_ajaran');
        $table->string('nama_tahun_ajaran');
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->boolean('is_aktif');
    });
    Schema::create('semester', function (Blueprint $table): void {
        $table->id('id_semester');
        $table->unsignedBigInteger('id_tahun_ajaran');
        $table->string('nama_semester');
        $table->boolean('is_aktif');
    });
    Schema::create('kelas', function (Blueprint $table): void {
        $table->id('id_kelas');
        $table->unsignedBigInteger('id_tahun_ajaran')->nullable();
        $table->string('nama_kelas');
        $table->boolean('is_aktif');
    });
    Schema::create('penugasan_wali_kelas', function (Blueprint $table): void {
        $table->id('id_penugasan_wali_kelas');
        $table->unsignedBigInteger('id_kelas');
        $table->unsignedBigInteger('id_guru');
        $table->unsignedBigInteger('id_semester');
        $table->timestamp('ditugaskan_pada')->nullable();
        $table->unique(['id_kelas', 'id_semester']);
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

    DB::table('role')->insert(['id_role' => 1, 'nama_role' => 'wali_kelas', 'kode_role' => 'wali_kelas']);
    DB::table('pengguna')->insert([
        ['id_pengguna' => 1, 'id_role' => 1, 'is_aktif' => true],
        ['id_pengguna' => 2, 'id_role' => 1, 'is_aktif' => true],
    ]);
    DB::table('guru')->insert([
        ['id_guru' => 1, 'id_pengguna' => 1, 'nama_guru' => 'Guru Lama', 'is_aktif' => true],
        ['id_guru' => 2, 'id_pengguna' => 2, 'nama_guru' => 'Guru Baru', 'is_aktif' => true],
    ]);
    DB::table('tahun_ajaran')->insert([
        'id_tahun_ajaran' => 1,
        'nama_tahun_ajaran' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'is_aktif' => true,
    ]);
    DB::table('semester')->insert([
        'id_semester' => 1,
        'id_tahun_ajaran' => 1,
        'nama_semester' => 'Ganjil',
        'is_aktif' => true,
    ]);
    DB::table('kelas')->insert([
        'id_kelas' => 1,
        'id_tahun_ajaran' => 1,
        'nama_kelas' => 'X IPA 1',
        'is_aktif' => true,
    ]);
});

it('allows TU to assign a homeroom teacher', function (): void {
    $response = $this->withoutMiddleware([EnsureRole::class])
        ->post(route('tu.kelas.wali'), [
            'id_kelas' => 1,
            'id_guru' => 1,
            'id_semester' => 1,
        ]);

    $response->assertRedirect(route('tu.kelas', ['semester' => 1]));
    $this->assertDatabaseHas('penugasan_wali_kelas', [
        'id_kelas' => 1,
        'id_guru' => 1,
        'id_semester' => 1,
    ]);
});

it('allows TU to replace an assigned homeroom teacher', function (): void {
    DB::table('penugasan_wali_kelas')->insert([
        'id_penugasan_wali_kelas' => 1,
        'id_kelas' => 1,
        'id_guru' => 1,
        'id_semester' => 1,
    ]);

    $response = $this->withoutMiddleware([EnsureRole::class])
        ->post(route('tu.kelas.wali'), [
            'id_kelas' => 1,
            'id_guru' => 2,
            'id_semester' => 1,
        ]);

    $response->assertRedirect(route('tu.kelas', ['semester' => 1]));
    $this->assertDatabaseHas('penugasan_wali_kelas', [
        'id_penugasan_wali_kelas' => 1,
        'id_guru' => 2,
    ]);
});

it('allows TU to remove an assigned homeroom teacher', function (): void {
    DB::table('penugasan_wali_kelas')->insert([
        'id_penugasan_wali_kelas' => 1,
        'id_kelas' => 1,
        'id_guru' => 1,
        'id_semester' => 1,
    ]);

    $response = $this->withoutMiddleware([EnsureRole::class])
        ->delete(route('tu.kelas.wali.cabut', 1));

    $response->assertRedirect(route('tu.kelas', ['semester' => 1]));
    $this->assertDatabaseMissing('penugasan_wali_kelas', ['id_penugasan_wali_kelas' => 1]);
});
