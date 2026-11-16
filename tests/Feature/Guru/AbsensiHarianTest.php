<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach ([
        'detail_absensi',
        'sesi_absensi',
        'status_kehadiran',
        'keanggotaan_kelas',
        'penugasan_wali_kelas',
        'guru',
        'mata_pelajaran',
        'kelas',
        'semester',
        'siswa',
        'pengguna',
        'role',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('role', function (Blueprint $table) {
        $table->id('id_role');
        $table->string('nama_role');
        $table->string('kode_role');
    });

    Schema::create('pengguna', function (Blueprint $table) {
        $table->id('id_pengguna');
        $table->unsignedBigInteger('id_role');
        $table->string('username');
        $table->string('password_hash');
        $table->boolean('is_aktif')->default(true);
    });

    Schema::create('guru', function (Blueprint $table) {
        $table->id('id_guru');
        $table->unsignedBigInteger('id_pengguna');
        $table->string('nama_guru');
        $table->boolean('is_aktif')->default(true);
    });

    Schema::create('semester', function (Blueprint $table) {
        $table->id('id_semester');
        $table->unsignedBigInteger('id_tahun_ajaran')->nullable();
        $table->string('nama_semester');
        $table->date('tanggal_mulai')->nullable();
        $table->date('tanggal_selesai')->nullable();
        $table->boolean('is_aktif')->default(false);
    });

    Schema::create('kelas', function (Blueprint $table) {
        $table->id('id_kelas');
    });

    Schema::create('siswa', function (Blueprint $table) {
        $table->id('id_siswa');
    });

    Schema::create('keanggotaan_kelas', function (Blueprint $table) {
        $table->id('id_keanggotaan');
        $table->unsignedBigInteger('id_siswa');
        $table->unsignedBigInteger('id_kelas');
        $table->unsignedBigInteger('id_semester');
        $table->string('status');
    });

    Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
        $table->id('id_penugasan_wali_kelas');
        $table->unsignedBigInteger('id_kelas');
        $table->unsignedBigInteger('id_guru');
        $table->unsignedBigInteger('id_semester');
    });

    Schema::create('status_kehadiran', function (Blueprint $table) {
        $table->id('id_status_hadir');
        $table->string('kode');
        $table->string('nama_status');
    });

    Schema::create('sesi_absensi', function (Blueprint $table) {
        $table->id('id_sesi');
        $table->unsignedBigInteger('id_jadwal')->nullable();
        $table->unsignedBigInteger('id_kelas');
        $table->unsignedBigInteger('id_semester');
        $table->unsignedBigInteger('id_guru_pencatat');
        $table->date('tanggal');
        $table->text('topik_materi')->nullable();
        $table->time('jam_dibuka')->nullable();
        $table->string('status_sesi');
    });

    Schema::create('detail_absensi', function (Blueprint $table) {
        $table->id('id_detail_absensi');
        $table->unsignedBigInteger('id_sesi');
        $table->unsignedBigInteger('id_siswa');
        $table->unsignedBigInteger('id_status_hadir');
        $table->text('keterangan')->nullable();
        $table->timestamp('waktu_dicatat')->nullable();
    });

    Schema::create('mata_pelajaran', function (Blueprint $table) {
        $table->id('id_mata_pelajaran');
    });

    DB::table('role')->insert(['id_role' => 1, 'nama_role' => 'wali_kelas', 'kode_role' => 'wali_kelas']);
    DB::table('pengguna')->insert(['id_pengguna' => 1, 'id_role' => 1, 'username' => 'wali01', 'password_hash' => 'hash']);
    DB::table('guru')->insert(['id_guru' => 1, 'id_pengguna' => 1, 'nama_guru' => 'Wali Kelas']);
    DB::table('semester')->insert(['id_semester' => 1, 'nama_semester' => 'Ganjil', 'is_aktif' => true]);
    DB::table('kelas')->insert(['id_kelas' => 1]);
    DB::table('siswa')->insert(['id_siswa' => 11]);
    DB::table('keanggotaan_kelas')->insert([
        'id_siswa' => 11,
        'id_kelas' => 1,
        'id_semester' => 1,
        'status' => 'aktif',
    ]);
    DB::table('penugasan_wali_kelas')->insert([
        'id_kelas' => 1,
        'id_guru' => 1,
        'id_semester' => 1,
    ]);
});

it('saves daily class attendance without a lesson schedule', function () {
    $tanggal = '2026-11-16';

    $this->withSession([
        'id_pengguna' => 1,
        'username' => 'wali01',
        'role' => 'wali_kelas',
    ])->from(route('guru.absensi'))
        ->post(route('guru.absensi.simpan'), [
            'id_kelas' => 1,
            'tanggal' => $tanggal,
            'status' => [11 => 'A'],
        ])
        ->assertRedirect(route('guru.absensi', ['kelas' => 1, 'tanggal' => $tanggal]))
        ->assertSessionHas('success', 'Presensi kelas berhasil disimpan.');

    $this->assertDatabaseHas('sesi_absensi', [
        'id_jadwal' => null,
        'id_kelas' => 1,
        'id_semester' => 1,
        'tanggal' => $tanggal,
    ]);

    $this->assertDatabaseHas('detail_absensi', [
        'id_siswa' => 11,
        'id_status_hadir' => DB::table('status_kehadiran')->where('kode', 'A')->value('id_status_hadir'),
    ]);
});

it('rejects report grades below one or above one hundred', function () {
    DB::table('mata_pelajaran')->insert(['id_mata_pelajaran' => 3]);

    $this->withSession([
        'id_pengguna' => 1,
        'username' => 'wali01',
        'role' => 'wali_kelas',
    ])->from(route('guru.rapor'))
        ->post(route('guru.rapor.simpan'), [
            'id_kelas' => 1,
            'id_mapel' => 3,
            'nilai_mentah' => [
                11 => ['tugas' => 0, 'keterampilan' => 101, 'ujian' => 1],
            ],
        ])
        ->assertRedirect(route('guru.rapor'))
        ->assertSessionHasErrors([
            'nilai_mentah.11.tugas',
            'nilai_mentah.11.keterampilan',
        ]);
});
