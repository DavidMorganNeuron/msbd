<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('mata_pelajaran');
    Schema::dropIfExists('tahun_ajaran');

    Schema::create('tahun_ajaran', function (Blueprint $table): void {
        $table->id('id_tahun_ajaran');
        $table->string('nama_tahun_ajaran');
        $table->date('tanggal_mulai');
        $table->date('tanggal_selesai');
        $table->boolean('is_aktif')->default(false);
    });

    Schema::create('mata_pelajaran', function (Blueprint $table): void {
        $table->id('id_mata_pelajaran');
        $table->string('kode_mapel')->nullable();
        $table->string('nama_mapel');
        $table->boolean('is_aktif')->default(true);
    });
});

it('omits assessment component and predicate tabs while keeping subject configuration available', function (): void {
    $this->withoutMiddleware([EnsureRole::class])
        ->get(route('akademik.konfigurasi'))
        ->assertOk()
        ->assertSee('Tahun Ajaran')
        ->assertSee('Semester')
        ->assertSee('Kelas')
        ->assertSee('Mata Pelajaran')
        ->assertDontSee('Komponen Penilaian')
        ->assertDontSee('Skala Predikat');

    $this->withoutMiddleware([EnsureRole::class])
        ->get(route('akademik.konfigurasi', 'mapel'))
        ->assertOk()
        ->assertSee('Mata Pelajaran');
});

it('returns 404 for assessment component and predicate pages and actions', function (): void {
    foreach (['komponen', 'predikat'] as $tab) {
        $this->withoutMiddleware([EnsureRole::class])
            ->get(route('akademik.konfigurasi', $tab))
            ->assertNotFound();

        $this->withoutMiddleware([EnsureRole::class])
            ->post(route('akademik.konfigurasi.simpan', $tab))
            ->assertNotFound();

        $this->withoutMiddleware([EnsureRole::class])
            ->put(route('akademik.konfigurasi.ubah', [$tab, 1]))
            ->assertNotFound();

        $this->withoutMiddleware([EnsureRole::class])
            ->delete(route('akademik.konfigurasi.hapus', [$tab, 1]))
            ->assertNotFound();
    }
});
