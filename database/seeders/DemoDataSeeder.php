<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private int $idTahunAjaran;

    private int $idSemesterGanjil;

    private array $idKelas = [];

    public function run(): void
    {
        $this->tahunAjaran();
        $this->periodeUpdate();
        $this->guru();
        $this->kelas();
        $this->siswa();
        $this->waliKelas();
        $this->ppdb();
    }

    private function tahunAjaran(): void
    {
        DB::table('tahun_ajaran')->update(['is_aktif' => false]);

        DB::table('tahun_ajaran')->updateOrInsert(
            ['nama_tahun_ajaran' => '2026/2027'],
            [
                'nama_tahun_ajaran' => '2026/2027',
                'tanggal_mulai' => '2026-07-13',
                'tanggal_selesai' => '2027-06-18',
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
                'tanggal_mulai' => '2026-11-01',
                'tanggal_selesai' => '2026-11-30',
                'status' => 'aktif',
                'dibuat_oleh' => 1,
            ]
        );
    }

    private function guru(): void
    {
        $guru = [
            ['username' => 'guru001', 'nama' => 'Rina Marpaung, S.Pd.', 'nip' => '198501012010011001', 'email' => 'rina.marpaung@methodist2.sch.id', 'telepon' => '081360000001'],
            ['username' => 'guru002', 'nama' => 'Budi Santoso, S.Pd.', 'nip' => '198703152011011002', 'email' => 'budi.santoso@methodist2.sch.id', 'telepon' => '081360000002'],
        ];

        $idRoleGuru = (int) DB::table('role')->where('nama_role', 'guru')->value('id_role');

        foreach ($guru as $data) {
            $pengguna = DB::table('pengguna')->where('username', $data['username'])->first();

            if (! $pengguna) {
                $idPengguna = DB::table('pengguna')->insertGetId([
                    'id_role' => $idRoleGuru,
                    'username' => $data['username'],
                    'password_hash' => Hash::make('password'),
                    'is_aktif' => true,
                    'dibuat_pada' => now(),
                    'diubah_pada' => now(),
                ], 'id_pengguna');
            } else {
                $idPengguna = $pengguna->id_pengguna;
            }

            DB::table('guru')->updateOrInsert(
                ['id_pengguna' => $idPengguna],
                [
                    'id_pengguna' => $idPengguna,
                    'nip' => $data['nip'],
                    'nama_guru' => $data['nama'],
                    'email' => $data['email'],
                    'no_telepon' => $data['telepon'],
                    'is_aktif' => true,
                ]
            );
        }
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

    private function siswa(): void
    {
        $idAgama = DB::table('agama')->pluck('id_agama', 'nama_agama');
        $idStatusAktif = (int) DB::table('status_siswa')->where('kode_status', 'aktif')->value('id_status_siswa');
        $idSekolah = (int) DB::table('sekolah')->where('nama_sekolah', 'SMP Swasta Methodist 2 Medan')->value('id_sekolah');

        $dataSiswa = [
            [
                'nis' => 'TEST001', 'nisn' => '0000000001', 'nama' => 'Siswa Percobaan',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2010-01-15',
                'email' => 'siswa001@example.test', 'telepon' => '081300000001', 'agama' => 'Kristen',
                'kelas' => 'X MIA 1', 'ayah' => 'Samuel Sitorus', 'ibu' => 'Ruth Sitorus',
            ],
            [
                'nis' => '2026002', 'nisn' => '0098765435', 'nama' => 'Daniel Sitorus',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2010-05-14',
                'email' => 'daniel.sitorus@example.test', 'telepon' => '081300000002', 'agama' => 'Kristen',
                'kelas' => 'X MIA 1', 'ayah' => 'Samuel Sitorus', 'ibu' => 'Ruth Sitorus',
            ],
            [
                'nis' => '2026003', 'nisn' => '0098765436', 'nama' => 'Maria Simbolon',
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2010-08-21',
                'email' => 'maria.simbolon@example.test', 'telepon' => '081300000003', 'agama' => 'Kristen',
                'kelas' => 'X IIS 1', 'ayah' => 'Hotman Simbolon', 'ibu' => 'Lestari Simbolon',
            ],
            [
                'nis' => '2026004', 'nisn' => '0098765437', 'nama' => 'Aisyah Putri Nasution',
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2010-02-09',
                'email' => 'aisyah.nasution@example.test', 'telepon' => '081300000004', 'agama' => 'Islam',
                'kelas' => 'X MIA 1', 'ayah' => 'Ahmad Nasution', 'ibu' => 'Siti Aminah',
            ],
            [
                'nis' => '2025001', 'nisn' => '0098765438', 'nama' => 'Kevin Wijaya',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2009-11-03',
                'email' => 'kevin.wijaya@example.test', 'telepon' => '081300000005', 'agama' => 'Buddha',
                'kelas' => 'XI MIA 1', 'ayah' => 'Hendra Wijaya', 'ibu' => 'Lina Wijaya',
            ],
            [
                'nis' => '2024001', 'nisn' => '0098765439', 'nama' => 'Grace Hutapea',
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Medan', 'tanggal_lahir' => '2008-07-19',
                'email' => 'grace.hutapea@example.test', 'telepon' => '081300000006', 'agama' => 'Kristen',
                'kelas' => 'XII IIS 1', 'ayah' => 'Ronald Hutapea', 'ibu' => 'Martha Hutapea',
            ],
        ];

        foreach ($dataSiswa as $data) {
            $idSiswa = DB::table('siswa')->where('nis', $data['nis'])->value('id_siswa');

            $atribut = [
                'id_status_siswa' => $idStatusAktif,
                'id_agama' => $idAgama[$data['agama']] ?? null,
                'id_sekolah_asal' => $idSekolah ?: null,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'],
                'nik' => null,
                'nama_lengkap' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'tempat_lahir' => $data['tempat_lahir'],
                'tanggal_lahir' => $data['tanggal_lahir'],
                'email' => $data['email'],
                'no_telepon' => $data['telepon'],
                'tanggal_masuk' => '2026-07-13',
                'diubah_pada' => now(),
            ];

            if ($idSiswa) {
                DB::table('siswa')->where('id_siswa', $idSiswa)->update($atribut);
            } else {
                $idSiswa = DB::table('siswa')->insertGetId($atribut + [
                    'id_pengguna' => null,
                    'dibuat_pada' => now(),
                ], 'id_siswa');
            }

            DB::table('alamat_siswa')->updateOrInsert(
                ['id_siswa' => $idSiswa],
                [
                    'id_siswa' => $idSiswa,
                    'alamat' => 'Jl. Melati No. 12',
                    'rt' => '02',
                    'rw' => '03',
                    'kelurahan' => 'Pasar Baru',
                    'kecamatan' => 'Medan Kota',
                    'kota' => 'Medan',
                    'provinsi' => 'Sumatera Utara',
                    'kode_pos' => '20212',
                ]
            );

            foreach (['ayah' => $data['ayah'], 'ibu' => $data['ibu']] as $hubungan => $nama) {
                DB::table('orang_tua_wali')->updateOrInsert(
                    ['id_siswa' => $idSiswa, 'hubungan' => $hubungan],
                    [
                        'id_siswa' => $idSiswa,
                        'hubungan' => $hubungan,
                        'nama_lengkap' => $nama,
                        'no_telepon' => $data['telepon'],
                        'email' => null,
                        'alamat' => 'Jl. Melati No. 12, Medan',
                    ]
                );
            }

            $idKelas = $this->idKelas[$data['kelas']] ?? null;

            if ($idKelas && $this->idSemesterGanjil) {
                DB::table('keanggotaan_kelas')->updateOrInsert(
                    ['id_siswa' => $idSiswa, 'id_kelas' => $idKelas, 'id_semester' => $this->idSemesterGanjil],
                    [
                        'id_siswa' => $idSiswa,
                        'id_kelas' => $idKelas,
                        'id_semester' => $this->idSemesterGanjil,
                        'tanggal_mulai' => '2026-07-13',
                        'status' => 'aktif',
                    ]
                );
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

    private function ppdb(): void
    {
        $idAgama = DB::table('agama')->pluck('id_agama', 'nama_agama');
        $idPekerjaan = DB::table('pekerjaan')->pluck('id_pekerjaan', 'nama_pekerjaan');

        $pendaftar = [
            [
                'nomor' => 'PPDB-2027-0001',
                'nama' => 'Daniel Sitorus',
                'nisn' => '0098765435',
                'jk' => 'L',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '2010-05-14',
                'asal' => 'SMP Swasta Methodist 2 Medan',
                'email' => 'daniel.sitorus@example.test',
                'telepon' => '081300000002',
                'alamat' => 'Jl. Melati No. 12, RT 02/RW 03, Kel. Pasar Baru, Kec. Medan Kota, Medan',
                'status' => 'menunggu',
                'ayah' => 'Samuel Sitorus',
                'ibu' => 'Ruth Sitorus',
            ],
            [
                'nomor' => 'PPDB-2027-0002',
                'nama' => 'Maria Simbolon',
                'nisn' => '0098765436',
                'jk' => 'P',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '2010-08-21',
                'asal' => 'SMP Negeri 1 Medan',
                'email' => 'maria.simbolon@example.test',
                'telepon' => '081300000003',
                'alamat' => 'Jl. Merdeka No. 8, RT 04/RW 05, Kel. Teladan Barat, Kec. Medan Kota, Medan',
                'status' => 'perlu_perbaikan',
                'ayah' => 'Hotman Simbolon',
                'ibu' => 'Lestari Simbolon',
            ],
            [
                'nomor' => 'PPDB-2027-0003',
                'nama' => 'Aisyah Putri Nasution',
                'nisn' => '0098765437',
                'jk' => 'P',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '2010-02-09',
                'asal' => 'SMP Negeri 2 Medan',
                'email' => 'aisyah.nasution@example.test',
                'telepon' => '081300000004',
                'alamat' => 'Jl. Sisingamangaraja No. 45, Kel. Sudirejo, Kec. Medan Kota, Medan',
                'status' => 'diverifikasi',
                'ayah' => 'Ahmad Nasution',
                'ibu' => 'Siti Aminah',
            ],
        ];

        foreach ($pendaftar as $data) {
            DB::table('pendaftaran_ppdb')->updateOrInsert(
                ['nomor_pendaftaran' => $data['nomor']],
                [
                    'nama_calon_siswa' => $data['nama'],
                    'nisn' => $data['nisn'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'sekolah_asal' => $data['asal'],
                    'alamat' => $data['alamat'],
                    'no_telepon' => $data['telepon'],
                    'status_pendaftaran' => $data['status'],
                    'jenis_kelamin' => $data['jk'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'email' => $data['email'],
                    'id_agama' => $idAgama['Kristen'] ?? null,
                    'jenjang_tujuan' => 'SMA',
                    'kelas_tujuan' => 'X MIA 1',
                    'tahun_pelajaran' => '2026/2027',
                    'persetujuan_ortu' => true,
                    'pernyataan' => true,
                    'langkah_terakhir' => 4,
                    'diajukan_pada' => now(),
                ]
            );

            $idPendaftaran = (int) DB::table('pendaftaran_ppdb')
                ->where('nomor_pendaftaran', $data['nomor'])
                ->value('id_pendaftaran');

            DB::table('ppdb_alamat')->updateOrInsert(
                ['id_pendaftaran' => $idPendaftaran],
                [
                    'jalan' => $data['alamat'],
                    'rt' => '02',
                    'rw' => '03',
                    'kelurahan' => 'Pasar Baru',
                    'kecamatan' => 'Medan Kota',
                    'kota' => 'Medan',
                    'provinsi' => 'Sumatera Utara',
                    'kode_pos' => '20212',
                    'no_hp' => $data['telepon'],
                ]
            );

            DB::table('ppdb_periodik')->updateOrInsert(
                ['id_pendaftaran' => $idPendaftaran],
                [
                    'tinggi_badan' => 158,
                    'berat_badan' => 48,
                    'gol_darah' => 'O',
                    'jarak' => 'Dekat',
                    'jarak_km' => 3.5,
                    'waktu_tempuh_jam' => 0,
                    'waktu_tempuh_menit' => 15,
                    'jumlah_saudara' => 2,
                ]
            );

            foreach (['ayah' => $data['ayah'], 'ibu' => $data['ibu']] as $hubungan => $nama) {
                DB::table('ppdb_orang_tua')->updateOrInsert(
                    ['id_pendaftaran' => $idPendaftaran, 'hubungan' => $hubungan],
                    [
                        'nama_lengkap' => $nama,
                        'pendidikan' => 'S1',
                        'id_pekerjaan' => $idPekerjaan['Karyawan Swasta'] ?? null,
                        'penghasilan' => '3-5 juta',
                        'no_telepon' => $data['telepon'],
                    ]
                );
            }
        }
    }
}
