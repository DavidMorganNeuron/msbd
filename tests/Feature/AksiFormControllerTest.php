<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AksiFormControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createJadwalSchema();
        $this->seedJadwalData();
    }

    public function test_mata_pelajaran_bawaan_tersedia_dan_migrasi_aman_dijalankan_ulang(): void
    {
        $migration = require database_path('migrations/2026_11_15_112800_seed_default_mata_pelajaran.php');
        $migration->up();
        $migration->up();

        $daftarMapel = [
            'UPACARA',
            'Biologi',
            'Writing',
            'Mate (P)',
            'Mate (W)',
            'B.Inggris',
            'PPKn',
            'Kebaktian',
            'B. Indonesia',
            'Fisika',
            'Tinkom',
            'Agama',
            'B. Mandarin',
            'Sejarah',
            'Kimia',
            'S. Budaya',
        ];
        $mapelTersimpan = DB::table('mata_pelajaran')->pluck('nama_mapel')->all();

        $this->assertDatabaseCount('mata_pelajaran', 16);
        foreach ($daftarMapel as $namaMapel) {
            $this->assertContains($namaMapel, $mapelTersimpan);
        }
        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'MAT-P',
            'nama_mapel' => 'Mate (P)',
            'is_aktif' => true,
        ]);
    }

    public function test_tu_dapat_melihat_mata_pelajaran_di_dropdown_jadwal(): void
    {
        $this->withSession($this->sesiTu())
            ->get(route('akademik.jadwal'))
            ->assertOk()
            ->assertSee('Biologi')
            ->assertSee('Mate (P)')
            ->assertSee('B. Mandarin');
    }

    public function test_tu_dapat_menyimpan_satu_baris_jadwal(): void
    {
        $idMapel = DB::table('mata_pelajaran')->where('nama_mapel', 'Biologi')->value('id_mata_pelajaran');

        $this->withSession($this->sesiTu())
            ->post(route('akademik.jadwal.simpan'), [
                'id_semester' => 1,
                'id_kelas' => 1,
                'jadwal' => [
                    [
                        'hari' => 1,
                        'jam_mulai' => '07:00',
                        'jam_selesai' => '07:40',
                        'id_mata_pelajaran' => $idMapel,
                        'nama_guru_mapel' => 'Budi Santoso',
                    ],
                ],
            ])
            ->assertRedirect(route('akademik.jadwal', ['semester' => 1, 'kelas' => 1]))
            ->assertSessionHas('success', 'Jadwal pelajaran berhasil disimpan.');

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => 1,
            'id_semester' => 1,
            'id_mata_pelajaran' => $idMapel,
            'id_guru' => 1,
            'hari' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:40',
        ]);
    }

    public function test_tu_ditolak_menyimpan_baris_jadwal_yang_tidak_lengkap(): void
    {
        $this->withSession($this->sesiTu())
            ->from(route('akademik.jadwal'))
            ->post(route('akademik.jadwal.simpan'), [
                'id_semester' => 1,
                'id_kelas' => 1,
                'jadwal' => [
                    [
                        'hari' => 1,
                        'jam_mulai' => '07:00',
                        'jam_selesai' => '07:40',
                        'id_mata_pelajaran' => '',
                        'nama_guru_mapel' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('akademik.jadwal'))
            ->assertSessionHasErrors([
                'jadwal.0.id_mata_pelajaran' => 'Mata pelajaran wajib dipilih pada setiap baris jadwal.',
            ]);

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_tu_dapat_menyimpan_jadwal_lewat_matriks(): void
    {
        $idBio = DB::table('mata_pelajaran')->where('nama_mapel', 'Biologi')->value('id_mata_pelajaran');
        $idMate = DB::table('mata_pelajaran')->where('nama_mapel', 'Mate (P)')->value('id_mata_pelajaran');

        $this->withSession($this->sesiTu())
            ->post(route('akademik.jadwal.simpan'), [
                'id_semester' => 1,
                'id_kelas' => 1,
                'matriks' => [
                    1 => [
                        1 => [
                            'id_mata_pelajaran' => $idBio,
                            'nama_guru_mapel' => 'Budi Santoso',
                        ],
                        2 => [
                            'id_mata_pelajaran' => $idMate,
                            'nama_guru_mapel' => 'Guru Matematika',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('akademik.jadwal', ['semester' => 1, 'kelas' => 1]))
            ->assertSessionHas('success', 'Jadwal pelajaran berhasil disimpan.');

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => 1,
            'id_semester' => 1,
            'id_mata_pelajaran' => $idBio,
            'hari' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:40',
        ]);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => 1,
            'id_semester' => 1,
            'id_mata_pelajaran' => $idMate,
            'hari' => 1,
            'jam_mulai' => '07:40',
            'jam_selesai' => '08:20',
        ]);
    }

    public function test_matriks_jadwal_otomatis_fallback_ke_wali_kelas_jika_guru_kosong(): void
    {
        $idUpacara = DB::table('mata_pelajaran')->where('nama_mapel', 'UPACARA')->value('id_mata_pelajaran');

        DB::table('penugasan_wali_kelas')->insert([
            'id_penugasan_wali_kelas' => 1,
            'id_kelas' => 1,
            'id_guru' => 1,
            'id_semester' => 1,
        ]);

        $this->withSession($this->sesiTu())
            ->post(route('akademik.jadwal.simpan'), [
                'id_semester' => 1,
                'id_kelas' => 1,
                'matriks' => [
                    1 => [
                        1 => [
                            'id_mata_pelajaran' => $idUpacara,
                            'nama_guru_mapel' => '',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('akademik.jadwal', ['semester' => 1, 'kelas' => 1]))
            ->assertSessionHas('success', 'Jadwal pelajaran berhasil disimpan.');

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => 1,
            'id_semester' => 1,
            'id_mata_pelajaran' => $idUpacara,
            'id_guru' => 1,
            'hari' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:40',
        ]);
    }

    public function test_list_kelas_diurutkan_secara_natural_1_sampai_20(): void
    {
        DB::table('kelas')->insert([
            ['id_kelas' => 2, 'id_tingkat' => 1, 'id_tahun_ajaran' => 1, 'nama_kelas' => 'Kelas 10', 'kapasitas' => 30, 'is_aktif' => true],
            ['id_kelas' => 3, 'id_tingkat' => 1, 'id_tahun_ajaran' => 1, 'nama_kelas' => 'Kelas 2', 'kapasitas' => 30, 'is_aktif' => true],
            ['id_kelas' => 4, 'id_tingkat' => 1, 'id_tahun_ajaran' => 1, 'nama_kelas' => 'Kelas 1', 'kapasitas' => 30, 'is_aktif' => true],
            ['id_kelas' => 5, 'id_tingkat' => 1, 'id_tahun_ajaran' => 1, 'nama_kelas' => 'Kelas 20', 'kapasitas' => 30, 'is_aktif' => true],
        ]);

        $response = $this->withSession($this->sesiTu())
            ->get(route('akademik.jadwal'));

        $response->assertOk();
        $kelasView = $response->viewData('kelas');
        $namaKelasUrut = collect($kelasView)->pluck('nama')->all();

        $posKelas1 = array_search('Kelas 1', $namaKelasUrut);
        $posKelas2 = array_search('Kelas 2', $namaKelasUrut);
        $posKelas10 = array_search('Kelas 10', $namaKelasUrut);
        $posKelas20 = array_search('Kelas 20', $namaKelasUrut);

        $this->assertTrue($posKelas1 < $posKelas2);
        $this->assertTrue($posKelas2 < $posKelas10);
        $this->assertTrue($posKelas10 < $posKelas20);
    }

    private function createJadwalSchema(): void
    {
        Schema::create('role', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role');
            $table->string('kode_role');
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role');
            $table->string('username')->unique();
            $table->string('password_hash')->nullable();
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id('id_tahun_ajaran');
            $table->string('nama_tahun_ajaran');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->id('id_semester');
            $table->unsignedBigInteger('id_tahun_ajaran');
            $table->string('nama_semester');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('tingkat', function (Blueprint $table) {
            $table->id('id_tingkat');
            $table->string('nama_tingkat');
            $table->unsignedSmallInteger('urutan');
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->unsignedBigInteger('id_tingkat')->nullable();
            $table->unsignedBigInteger('id_tahun_ajaran')->nullable();
            $table->string('nama_kelas');
            $table->unsignedInteger('kapasitas')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->unsignedBigInteger('id_jurusan')->nullable();
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('nama_guru');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
            $table->id('id_penugasan_wali_kelas');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_semester');
        });

        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id('id_mata_pelajaran');
            $table->string('kode_mapel', 30)->nullable()->unique();
            $table->string('nama_mapel', 150)->unique();
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id('id_jadwal');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_semester');
            $table->unsignedBigInteger('id_mata_pelajaran');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedSmallInteger('hari');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruang', 50)->nullable();
        });

        Schema::create('sesi_absensi', function (Blueprint $table) {
            $table->id('id_sesi');
            $table->unsignedBigInteger('id_jadwal');
        });
    }

    private function seedJadwalData(): void
    {
        DB::table('role')->insert([
            'id_role' => 1,
            'nama_role' => 'tu',
            'kode_role' => 'tu',
        ]);
        DB::table('pengguna')->insert([
            'id_pengguna' => 1,
            'id_role' => 1,
            'username' => 'tu01',
            'is_aktif' => true,
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
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'is_aktif' => true,
        ]);
        DB::table('tingkat')->insert([
            'id_tingkat' => 1,
            'nama_tingkat' => 'X',
            'urutan' => 10,
        ]);
        DB::table('kelas')->insert([
            'id_kelas' => 1,
            'id_tingkat' => 1,
            'id_tahun_ajaran' => 1,
            'nama_kelas' => 'X MIA 1',
            'kapasitas' => 30,
            'is_aktif' => true,
        ]);
        DB::table('guru')->insert([
            'id_guru' => 1,
            'id_pengguna' => null,
            'nama_guru' => 'Budi Santoso',
            'is_aktif' => true,
        ]);
        DB::table('mata_pelajaran')->insert([
            'id_mata_pelajaran' => 1,
            'kode_mapel' => 'BIO',
            'nama_mapel' => 'Biologi',
            'is_aktif' => true,
        ]);

        $migration = require database_path('migrations/2026_11_15_112800_seed_default_mata_pelajaran.php');
        $migration->up();
    }

    private function sesiTu(): array
    {
        return [
            'id_pengguna' => 1,
            'username' => 'tu01',
            'role' => 'tu',
        ];
    }
}
