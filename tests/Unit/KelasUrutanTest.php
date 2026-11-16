<?php

use App\Models\Kelas;
use Tests\TestCase;

uses(TestCase::class);

it('mengurutkan kelas secara natural per tingkat, jurusan, dan nomor', function () {
    $kelas = collect([
        new Kelas(['nama_kelas' => 'X MIA 10']),
        new Kelas(['nama_kelas' => 'XII IIS 1']),
        new Kelas(['nama_kelas' => 'X MIA 2']),
        new Kelas(['nama_kelas' => 'X IIS 10']),
        new Kelas(['nama_kelas' => 'XI MIA 1']),
        new Kelas(['nama_kelas' => 'X MIA 1']),
        new Kelas(['nama_kelas' => 'X IIS 2']),
    ]);

    expect(Kelas::urutAlami($kelas)->pluck('nama_kelas')->all())->toBe([
        'X IIS 2',
        'X IIS 10',
        'X MIA 1',
        'X MIA 2',
        'X MIA 10',
        'XI MIA 1',
        'XII IIS 1',
    ]);
});

it('membaca tingkat, jurusan, dan nomor dari nama kelas', function () {
    $kelas = new Kelas(['nama_kelas' => 'XI MIA 12']);

    expect($kelas->tingkatLabel())->toBe('XI')
        ->and($kelas->jurusanLabel())->toBe('MIA')
        ->and($kelas->nomorKelas())->toBe(12);
});
