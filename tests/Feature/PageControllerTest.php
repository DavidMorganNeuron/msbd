<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_teacher_class_roster_paginates_ten_students_with_absolute_numbers(): void
    {
        [$idGuru, $session] = $this->createWaliKelas();
        $idSemester = $this->createSemester();
        $idKelas = $this->createClass('X MIA 1');
        $this->assignClass($idGuru, $idKelas, $idSemester);

        for ($number = 1; $number <= 12; $number++) {
            $idSiswa = $this->createStudent(sprintf('Siswa %02d', $number));
            $this->assignStudent($idSiswa, $idKelas, $idSemester);
        }

        $halamanPertama = $this->withSession($session)
            ->get(route('guru.kelas'))
            ->assertOk()
            ->assertSee('12 siswa')
            ->assertSee('Siswa 10')
            ->assertDontSee('Siswa 11')
            ->assertSee('<td class="p-2.5 text-mute">10</td>', false);

        $halamanKedua = $this->withSession($session)
            ->get(route('guru.kelas', ['page' => 2]))
            ->assertOk()
            ->assertSee('Siswa 11')
            ->assertSee('Siswa 12')
            ->assertSee('<td class="p-2.5 text-mute">11</td>', false);

        $this->assertNotSame($halamanPertama->getContent(), $halamanKedua->getContent());
    }

    public function test_teacher_note_history_paginates_after_ten_records(): void
    {
        [$idGuru, $session] = $this->createWaliKelas();
        $this->createSemester();

        for ($number = 1; $number <= 11; $number++) {
            $idSiswa = $this->createStudent(sprintf('Siswa %02d', $number));
            DB::table('catatan_wali_kelas')->insert([
                'id_siswa' => $idSiswa,
                'id_guru' => $idGuru,
                'isi_catatan' => sprintf('Catatan %02d', $number),
                'tgl_dibuat' => sprintf('2026-10-%02d 08:00:00', 12 - $number),
            ]);
        }

        $this->withSession($session)
            ->get(route('guru.catatan'))
            ->assertOk()
            ->assertSee('Catatan 10')
            ->assertDontSee('Catatan 11')
            ->assertSee('Menampilkan 1-10 dari 11 catatan');

        $this->withSession($session)
            ->get(route('guru.catatan', ['page' => 2]))
            ->assertOk()
            ->assertSee('Catatan 11')
            ->assertSee('Menampilkan 11-11 dari 11 catatan');
    }

    public function test_attendance_and_report_pages_use_the_first_assigned_class_as_read_only_text(): void
    {
        [$idGuru, $session] = $this->createWaliKelas();
        $idSemester = $this->createSemester();
        $idKelasPertama = $this->createClass('X MIA 1');
        $idKelasKedua = $this->createClass('X MIA 2');
        $this->assignClass($idGuru, $idKelasPertama, $idSemester);
        $this->assignClass($idGuru, $idKelasKedua, $idSemester);

        $this->withSession($session)
            ->get(route('guru.absensi', ['kelas' => $idKelasKedua]))
            ->assertOk()
            ->assertSee('<input class="input" id="kelas" type="text" value="X MIA 1" readonly>', false)
            ->assertDontSee('<select class="input" id="kelas"', false);

        $this->withSession($session)
            ->get(route('guru.rapor', ['kelas' => $idKelasKedua]))
            ->assertOk()
            ->assertSee('<input class="input" id="kelas" type="text" value="X MIA 1" readonly>', false)
            ->assertDontSee('<select class="input" id="kelas"', false);
    }

    public function test_teacher_schedule_uses_a_daily_picker_with_class_details(): void
    {
        [$idGuru, $session] = $this->createWaliKelas();
        $idSemester = $this->createSemester();
        $idKelas = $this->createClass('X MIA 1');
        $idMapel = (int) DB::table('mata_pelajaran')->insertGetId([
            'nama_mapel' => 'Matematika Wajib',
            'is_aktif' => true,
        ], 'id_mata_pelajaran');

        DB::table('jadwal_pelajaran')->insert([
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
            'id_mata_pelajaran' => $idMapel,
            'id_guru' => $idGuru,
            'hari' => 2,
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '09:00:00',
            'ruang' => 'Ruang X MIA 1',
        ]);
        DB::table('jadwal_pelajaran')->insert([
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
            'id_mata_pelajaran' => $idMapel,
            'id_guru' => $idGuru,
            'hari' => 7,
            'jam_mulai' => '10:00:00',
            'jam_selesai' => '10:40:00',
            'ruang' => 'Ruang X MIA 1',
        ]);

        $hariDefault = (int) now('Asia/Jakarta')->dayOfWeekIso;
        if ($hariDefault > 6) {
            $hariDefault = 1;
        }

        $this->withSession($session)
            ->get(route('guru.jadwal'))
            ->assertOk()
            ->assertSee('data-jadwal-picker', false)
            ->assertSee('data-default-day="'.$hariDefault.'"', false)
            ->assertSee('data-hari-tab="2"', false)
            ->assertSee('Matematika Wajib')
            ->assertSee('Kelas')
            ->assertSee('X MIA 1')
            ->assertSee('Ruang')
            ->assertDontSee('data-jadwal-mode-switch', false)
            ->assertSee('data-hari-tab="7"', false);
    }

    public function test_student_schedule_defaults_to_daily_mode_and_keeps_the_full_roster(): void
    {
        $idRole = $this->createRole('siswa');
        $idPengguna = $this->createAccount('siswa-jadwal', $idRole);
        $idSemester = $this->createSemester();
        $idKelas = $this->createClass('XI IIS 1');
        $idSiswa = $this->createStudent('Siswa Jadwal', $idPengguna);
        $this->assignStudent($idSiswa, $idKelas, $idSemester);
        [$idGuru] = $this->createWaliKelas();
        $idMapel = (int) DB::table('mata_pelajaran')->insertGetId([
            'nama_mapel' => 'Biologi',
            'is_aktif' => true,
        ], 'id_mata_pelajaran');

        DB::table('jadwal_pelajaran')->insert([
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
            'id_mata_pelajaran' => $idMapel,
            'id_guru' => $idGuru,
            'hari' => 3,
            'jam_mulai' => '09:20:00',
            'jam_selesai' => '10:00:00',
            'ruang' => 'XI IIS 1',
        ]);

        $session = $this->sessionFor($idPengguna, 'siswa-jadwal', 'siswa');

        $this->withSession($session)
            ->get(route('siswa.jadwal'))
            ->assertOk()
            ->assertSee('data-jadwal-mode-switch', false)
            ->assertSee('data-jadwal-mode-button="harian"', false)
            ->assertSee('data-jadwal-mode-button="roster"', false)
            ->assertSee('data-jadwal-mode-panel="harian"', false)
            ->assertSee('data-jadwal-mode-panel="roster" hidden', false)
            ->assertSee('Roster lengkap')
            ->assertSee('Biologi')
            ->assertSee('Guru Pengajar')
            ->assertSee('Wali Test')
            ->assertSee('<table', false)
            ->assertDontSee('data-hari-tab="7"', false);
    }

    public function test_students_only_see_reports_after_they_are_finalized(): void
    {
        $idRole = $this->createRole('siswa');
        $idPengguna = $this->createAccount('siswa-test', $idRole);
        $idSemester = $this->createSemester();
        $idKelas = $this->createClass('X MIA 1');
        $idSiswa = $this->createStudent('Siswa Test', $idPengguna);
        $idAnggota = $this->assignStudent($idSiswa, $idKelas, $idSemester);
        $idMapel = (int) DB::table('mata_pelajaran')->insertGetId([
            'nama_mapel' => 'Biologi',
            'is_aktif' => true,
        ], 'id_mata_pelajaran');
        $idRapor = (int) DB::table('rapor')->insertGetId([
            'id_anggota' => $idAnggota,
            'id_semester' => $idSemester,
            'status_rapor' => 'draft',
            'rata_rata' => 92,
            'jumlah_sakit' => 0,
            'jumlah_izin' => 0,
            'jumlah_alpa' => 0,
            'catatan_wali' => 'Catatan pribadi',
        ], 'id_rapor');
        DB::table('detail_rapor')->insert([
            'id_rapor' => $idRapor,
            'id_mapel' => $idMapel,
            'nilai_akhir' => 92,
        ]);
        $session = $this->sessionFor($idPengguna, 'siswa-test', 'siswa');

        $this->withSession($session)
            ->get(route('siswa.rapor'))
            ->assertOk()
            ->assertSee('Rapor belum tersedia')
            ->assertDontSee('Catatan pribadi')
            ->assertDontSee('Biologi');

        DB::table('rapor')->where('id_rapor', $idRapor)->update(['status_rapor' => 'final']);

        $this->withSession($session)
            ->get(route('siswa.rapor'))
            ->assertOk()
            ->assertSee('Biologi')
            ->assertSee('Final')
            ->assertSee('Catatan pribadi');
    }

    public function test_student_profile_displays_parent_data_labels_instead_of_codes(): void
    {
        $student = array_fill_keys([
            'nama_lengkap', 'nis', 'nisn', 'nik', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'agama', 'no_hp', 'email', 'sekolah_asal', 'kelas', 'status', 'alamat', 'alamat_rt',
            'alamat_rw', 'alamat_kelurahan', 'alamat_kecamatan', 'alamat_kota', 'alamat_provinsi',
            'alamat_kode_pos', 'ayah', 'ayah_no_telepon', 'ayah_email', 'ayah_alamat', 'ibu',
            'ibu_no_telepon', 'ibu_email', 'ibu_alamat', 'wali', 'wali_no_telepon', 'wali_email',
            'wali_alamat',
        ], '-');
        $ppdb = [
            'ortu' => [
                [
                    'nama' => 'Ayah',
                    'pendidikan' => '09',
                    'pekerjaan' => '05',
                    'penghasilan' => '3',
                    'kebutuhan' => '02',
                ],
            ],
        ];
        view()->share('errors', new ViewErrorBag);

        $html = view('siswa.profil', [
            's' => $student,
            'ppdb' => $ppdb,
            'dokumen' => collect(),
        ])->render();

        $this->assertStringContainsString('D4/S1', $html);
        $this->assertStringContainsString('PNS/TNI/Polri', $html);
        $this->assertStringContainsString('1 juta - 1.999.999', $html);
        $this->assertStringContainsString('Tunarungu', $html);
        $this->assertStringNotContainsString('>09</dd>', $html);
        $this->assertStringNotContainsString('>05</dd>', $html);
    }

    private function createSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ([
                'detail_rapor',
                'rapor',
                'detail_absensi',
                'sesi_absensi',
                'jadwal_pelajaran',
                'catatan_wali_kelas',
                'keanggotaan_kelas',
                'penugasan_wali_kelas',
                'skala_predikat',
                'komponen_penilaian',
                'mata_pelajaran',
                'kelas',
                'semester',
                'siswa',
                'guru',
                'pengguna',
                'role',
            ] as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Schema::create('role', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role');
            $table->string('kode_role')->nullable();
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role');
            $table->string('username');
            $table->string('password_hash');
            $table->boolean('is_aktif')->default(true);
            $table->timestamp('terakhir_login')->nullable();
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->unsignedBigInteger('id_pengguna');
            $table->string('nama_guru')->nullable();
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->id('id_siswa');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('nama_lengkap');
            $table->string('nisn')->nullable();
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->id('id_semester');
            $table->string('nama_semester');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_aktif')->default(false);
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->string('nama_kelas');
        });

        Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
            $table->id('id_penugasan_wali_kelas');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_semester');
        });

        Schema::create('keanggotaan_kelas', function (Blueprint $table) {
            $table->id('id_keanggotaan');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_semester');
            $table->string('status');
        });

        Schema::create('catatan_wali_kelas', function (Blueprint $table) {
            $table->id('id_catatan');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_guru');
            $table->text('isi_catatan');
            $table->timestamp('tgl_dibuat');
        });

        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id('id_mata_pelajaran');
            $table->string('nama_mapel');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id('id_jadwal');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_semester');
            $table->unsignedBigInteger('id_mata_pelajaran');
            $table->unsignedBigInteger('id_guru')->nullable();
            $table->unsignedSmallInteger('hari')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('ruang')->nullable();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id('id_komponen');
            $table->string('nama_komponen');
            $table->decimal('bobot_persen', 5, 2)->default(0);
        });

        Schema::create('rapor', function (Blueprint $table) {
            $table->id('id_rapor');
            $table->unsignedBigInteger('id_anggota');
            $table->unsignedBigInteger('id_semester');
            $table->string('status_rapor')->nullable();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->integer('jumlah_sakit')->default(0);
            $table->integer('jumlah_izin')->default(0);
            $table->integer('jumlah_alpa')->default(0);
            $table->text('catatan_wali')->nullable();
        });

        Schema::create('detail_rapor', function (Blueprint $table) {
            $table->id('id_detail_rapor');
            $table->unsignedBigInteger('id_rapor');
            $table->unsignedBigInteger('id_mapel');
            $table->unsignedBigInteger('id_predikat')->nullable();
            $table->decimal('nilai_akhir', 5, 2);
        });

        Schema::create('skala_predikat', function (Blueprint $table) {
            $table->id('id_predikat');
            $table->string('huruf');
        });

        Schema::create('sesi_absensi', function (Blueprint $table) {
            $table->id('id_sesi');
            $table->unsignedBigInteger('id_jadwal');
            $table->date('tanggal');
        });

        Schema::create('detail_absensi', function (Blueprint $table) {
            $table->id('id_detail_absensi');
            $table->unsignedBigInteger('id_sesi');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_status_hadir');
        });

        Schema::create('status_kehadiran', function (Blueprint $table) {
            $table->id('id_status_hadir');
            $table->string('kode');
            $table->string('nama_status');
        });
    }

    private function createRole(string $name): int
    {
        return (int) DB::table('role')->insertGetId([
            'nama_role' => $name,
            'kode_role' => $name,
        ], 'id_role');
    }

    private function createAccount(string $username, int $idRole): int
    {
        return (int) DB::table('pengguna')->insertGetId([
            'id_role' => $idRole,
            'username' => $username,
            'password_hash' => 'hash',
            'is_aktif' => true,
        ], 'id_pengguna');
    }

    private function createWaliKelas(): array
    {
        $idRole = $this->createRole('wali_kelas');
        $idPengguna = $this->createAccount('wali-test', $idRole);
        $idGuru = (int) DB::table('guru')->insertGetId([
            'id_pengguna' => $idPengguna,
            'nama_guru' => 'Wali Test',
        ], 'id_guru');

        return [
            $idGuru,
            $this->sessionFor($idPengguna, 'wali-test', 'wali_kelas'),
        ];
    }

    private function createSemester(): int
    {
        return (int) DB::table('semester')->insertGetId([
            'nama_semester' => 'Ganjil 2026',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
            'is_aktif' => true,
        ], 'id_semester');
    }

    private function createClass(string $name): int
    {
        return (int) DB::table('kelas')->insertGetId(['nama_kelas' => $name], 'id_kelas');
    }

    private function assignClass(int $idGuru, int $idKelas, int $idSemester): void
    {
        DB::table('penugasan_wali_kelas')->insert([
            'id_guru' => $idGuru,
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
        ]);
    }

    private function createStudent(string $name, ?int $idPengguna = null): int
    {
        return (int) DB::table('siswa')->insertGetId([
            'id_pengguna' => $idPengguna,
            'nama_lengkap' => $name,
            'nisn' => str_pad((string) DB::table('siswa')->count() + 1, 10, '0', STR_PAD_LEFT),
        ], 'id_siswa');
    }

    private function assignStudent(int $idSiswa, int $idKelas, int $idSemester): int
    {
        return (int) DB::table('keanggotaan_kelas')->insertGetId([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
            'status' => 'aktif',
        ], 'id_keanggotaan');
    }

    private function sessionFor(int $idPengguna, string $username, string $role): array
    {
        return [
            'id_pengguna' => $idPengguna,
            'username' => $username,
            'role' => $role,
            'nama' => $username,
        ];
    }
}
