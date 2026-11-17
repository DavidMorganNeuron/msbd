<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('seeds the requested 2026/2027 academic-year and annual update dates', function () {
    Schema::create('tahun_ajaran', function (Blueprint $table) {
        $table->id('id_tahun_ajaran');
        $table->string('nama_tahun_ajaran')->unique();
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->boolean('is_aktif')->default(false);
    });

    Schema::create('semester', function (Blueprint $table) {
        $table->id('id_semester');
        $table->unsignedBigInteger('id_tahun_ajaran');
        $table->string('nama_semester');
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->boolean('is_aktif')->default(false);
    });

    Schema::create('periode_update', function (Blueprint $table) {
        $table->id('id_periode_update');
        $table->unsignedBigInteger('id_tahun_ajaran');
        $table->string('nama_periode');
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->string('status');
        $table->unsignedBigInteger('dibuat_oleh')->nullable();
    });

    Schema::create('tingkat', function (Blueprint $table) {
        $table->id('id_tingkat');
        $table->string('nama_tingkat');
    });

    Schema::create('jurusan', function (Blueprint $table) {
        $table->id('id_jurusan');
        $table->string('kode_jurusan');
    });

    Schema::create('kelas', function (Blueprint $table) {
        $table->id('id_kelas');
        $table->string('nama_kelas');
        $table->unsignedBigInteger('id_tahun_ajaran');
        $table->unsignedBigInteger('id_tingkat')->nullable();
        $table->unsignedBigInteger('id_jurusan')->nullable();
        $table->unsignedInteger('kapasitas');
        $table->boolean('is_aktif');
    });

    Schema::create('guru', function (Blueprint $table) {
        $table->id('id_guru');
    });

    $this->seed(DemoDataSeeder::class);

    $yearId = DB::table('tahun_ajaran')
        ->where('nama_tahun_ajaran', '2026/2027')
        ->value('id_tahun_ajaran');

    $this->assertDatabaseHas('tahun_ajaran', [
        'id_tahun_ajaran' => $yearId,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'is_aktif' => true,
    ]);

    $this->assertDatabaseHas('semester', [
        'id_tahun_ajaran' => $yearId,
        'nama_semester' => 'Ganjil',
        'tanggal_mulai' => '2026-07-13',
        'tanggal_selesai' => '2026-12-18',
        'is_aktif' => true,
    ]);

    $this->assertDatabaseHas('periode_update', [
        'id_tahun_ajaran' => $yearId,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status' => 'aktif',
    ]);
});
