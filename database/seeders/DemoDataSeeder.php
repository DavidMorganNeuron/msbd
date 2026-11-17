<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private int $idTahunAjaran;

    private int $idSemesterGanjil;

    private array $idKelas = [];

    public function run(): void
    {
        $this->tahunAjaran();
        $this->periodeUpdate();
        $this->kelas();
        $this->waliKelas();
    }

    private function tahunAjaran(): void
    {
        DB::table('tahun_ajaran')->update(['is_aktif' => false]);

        DB::table('tahun_ajaran')->updateOrInsert(
            ['nama_tahun_ajaran' => '2026/2027'],
            [
                'nama_tahun_ajaran' => '2026/2027',
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2027-06-30',
                'is_aktif' => true,
            ]
        );

        $this->idTahunAjaran = (int) DB::table('tahun_ajaran')
            ->where('nama_tahun_ajaran', '2026/2027')
            ->value('id_tahun_ajaran');

        DB::table('semester')->update(['is_aktif' => false]);

        DB::table('semester')->updateOrInsert(
            ['id_tahun_ajaran' => $this->idTahunAjaran, 'nama_semester' => 'Ganjil'],
            [
                'nama_semester' => 'Ganjil',
                'tanggal_mulai' => '2026-07-13',
                'tanggal_selesai' => '2026-12-18',
                'is_aktif' => true,
            ]
        );
        DB::table('semester')->updateOrInsert(
            ['id_tahun_ajaran' => $this->idTahunAjaran, 'nama_semester' => 'Genap'],
            [
                'nama_semester' => 'Genap',
                'tanggal_mulai' => '2027-01-05',
                'tanggal_selesai' => '2027-06-18',
                'is_aktif' => false,
            ]
        );

        $this->idSemesterGanjil = (int) DB::table('semester')
            ->where('id_tahun_ajaran', $this->idTahunAjaran)
            ->where('nama_semester', 'Ganjil')
            ->value('id_semester');
    }

    private function periodeUpdate(): void
    {
        DB::table('periode_update')->updateOrInsert(
            ['id_tahun_ajaran' => $this->idTahunAjaran, 'nama_periode' => 'Perubahan Data Ganjil 2026/2027'],
            [
                'nama_periode' => 'Perubahan Data Ganjil 2026/2027',
                'tanggal_mulai' => '2026-07-01',
                'tanggal_selesai' => '2027-06-30',
                'status' => 'aktif',
                'dibuat_oleh' => null,
            ]
        );
    }

    private function kelas(): void
    {
        $tingkat = DB::table('tingkat')->pluck('id_tingkat', 'nama_tingkat');
        $jurusan = DB::table('jurusan')->pluck('id_jurusan', 'kode_jurusan');

        foreach (['X', 'XI', 'XII'] as $namaTingkat) {
            foreach (['MIA', 'IIS'] as $kodeJurusan) {
                foreach (range(1, 20) as $nomorKelas) {
                    $namaKelas = "{$namaTingkat} {$kodeJurusan} {$nomorKelas}";

                    DB::table('kelas')->updateOrInsert(
                        ['nama_kelas' => $namaKelas, 'id_tahun_ajaran' => $this->idTahunAjaran],
                        [
                            'nama_kelas' => $namaKelas,
                            'id_tingkat' => $tingkat[$namaTingkat] ?? null,
                            'id_jurusan' => $jurusan[$kodeJurusan] ?? null,
                            'kapasitas' => 32,
                            'is_aktif' => true,
                        ]
                    );

                    $this->idKelas[$namaKelas] = (int) DB::table('kelas')
                        ->where('nama_kelas', $namaKelas)
                        ->where('id_tahun_ajaran', $this->idTahunAjaran)
                        ->value('id_kelas');
                }
            }
        }
    }

    private function waliKelas(): void
    {
        $guru = DB::table('guru')->orderBy('id_guru')->get();

        if ($guru->isEmpty()) {
            return;
        }

        foreach ([['X MIA 1', 0], ['X IIS 1', 1]] as [$namaKelas, $index]) {
            if (! isset($this->idKelas[$namaKelas])) {
                continue;
            }

            DB::table('penugasan_wali_kelas')->updateOrInsert(
                ['id_kelas' => $this->idKelas[$namaKelas], 'id_semester' => $this->idSemesterGanjil],
                [
                    'id_guru' => $guru[$index]->id_guru ?? $guru[0]->id_guru,
                    'ditugaskan_pada' => now(),
                ]
            );
        }
    }
}
