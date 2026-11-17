<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('log_audit');
    Schema::dropIfExists('siswa');
    Schema::enableForeignKeyConstraints();

    Schema::create('siswa', function (Blueprint $table): void {
        $table->id('id_siswa');
        $table->string('nama_lengkap');
        $table->string('nis')->unique();
        $table->string('nisn')->unique();
        $table->string('nik')->unique();
        $table->string('jenis_kelamin');
        $table->string('tempat_lahir');
        $table->date('tanggal_lahir');
        $table->string('email')->nullable();
        $table->string('no_telepon')->nullable();
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

    DB::table('siswa')->insert([
        'id_siswa' => 8,
        'nama_lengkap' => 'Siswa Uji',
        'nis' => '5748',
        'nisn' => '0075724035',
        'nik' => '6472062801070004',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2007-01-28',
        'email' => 'lama@example.test',
        'no_telepon' => '08123456789',
    ]);
});

it('allows TU to edit a student profile while preserving unique identifiers', function (): void {
    $response = $this->withoutMiddleware([EnsureRole::class])
        ->put(route('tu.siswa.update', 8), [
            'nama_lengkap' => 'Siswa Diperbarui',
            'nis' => '5748',
            'nisn' => '0075724035',
            'nik' => '6472062801070004',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '2007-01-28',
            'email' => 'baru@example.test',
            'no_telepon' => '08123456789',
        ]);

    $response->assertRedirect(route('tu.siswa'));
    $this->assertDatabaseHas('siswa', [
        'id_siswa' => 8,
        'nama_lengkap' => 'Siswa Diperbarui',
        'nisn' => '0075724035',
        'nik' => '6472062801070004',
        'tempat_lahir' => 'Jakarta',
        'email' => 'baru@example.test',
    ]);
});
