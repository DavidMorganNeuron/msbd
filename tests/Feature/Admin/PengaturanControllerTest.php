<?php

use App\Services\PengaturanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['log_audit', 'pengaturan_sistem', 'semester', 'tahun_ajaran'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('log_audit', function (Blueprint $table) {
        $table->id('id_log');
        $table->string('nama_tabel');
        $table->string('operasi');
        $table->text('id_data')->nullable();
        $table->json('nilai_lama')->nullable();
        $table->json('nilai_baru')->nullable();
        $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
        $table->text('username_pengubah')->nullable();
        $table->timestamp('waktu')->nullable();
    });

    Schema::create('pengaturan_sistem', function (Blueprint $table) {
        $table->id('id_pengaturan');
        $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
        $table->string('kunci')->unique();
        $table->text('nilai')->nullable();
        $table->text('deskripsi')->nullable();
        $table->timestamp('tgl_diubah')->nullable();
    });

    Schema::create('tahun_ajaran', function (Blueprint $table) {
        $table->id('id_tahun_ajaran');
        $table->string('nama_tahun_ajaran');
        $table->date('tanggal_mulai')->nullable();
        $table->date('tanggal_selesai')->nullable();
        $table->boolean('is_aktif')->default(false);
    });

    Schema::create('semester', function (Blueprint $table) {
        $table->id('id_semester');
        $table->unsignedBigInteger('id_tahun_ajaran');
        $table->string('nama_semester');
        $table->date('tanggal_mulai')->nullable();
        $table->date('tanggal_selesai')->nullable();
        $table->boolean('is_aktif')->default(false);
    });

    DB::table('tahun_ajaran')->insert([
        ['id_tahun_ajaran' => 1, 'nama_tahun_ajaran' => '2025/2026', 'is_aktif' => true],
        ['id_tahun_ajaran' => 2, 'nama_tahun_ajaran' => '2026/2027', 'is_aktif' => false],
    ]);
    DB::table('semester')->insert([
        'id_semester' => 1,
        'id_tahun_ajaran' => 1,
        'nama_semester' => 'Ganjil',
        'is_aktif' => true,
    ]);

    Cache::flush();
});

it('persists settings and applies the selected academic year', function () {
    $pengaturan = app(PengaturanService::class);
    $pengaturan->simpan([
        'nama_sekolah' => 'Sekolah Baru',
        'max_login_gagal' => 3,
        'durasi_kunci' => 30,
        'ukuran_berkas_mb' => 5,
        'format_berkas' => 'PDF, jpg',
        'tahun_ajaran_aktif' => '2026/2027',
    ], null);

    expect($pengaturan->nilai('max_login_gagal'))->toBe('3')
        ->and($pengaturan->formatBerkasDiizinkan())->toBe(['pdf', 'jpg']);

    $this->assertDatabaseHas('pengaturan_sistem', [
        'kunci' => 'max_login_gagal',
        'nilai' => '3',
    ]);
    $this->assertDatabaseHas('tahun_ajaran', [
        'nama_tahun_ajaran' => '2025/2026',
        'is_aktif' => false,
    ]);
    $this->assertDatabaseHas('tahun_ajaran', [
        'nama_tahun_ajaran' => '2026/2027',
        'is_aktif' => true,
    ]);
    $this->assertDatabaseHas('semester', [
        'id_semester' => 1,
        'is_aktif' => false,
    ]);
});
