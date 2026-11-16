<?php

namespace Tests\Feature;

use App\Http\Controllers\AksiFormController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Tu\KelasController;
use App\Http\Middleware\EnsureRole;
use App\Services\KelasMassalService;
use App\Services\KenaikanKelasService;
use App\Services\PengajuanPerubahanService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelasKenaikanKelasFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalAcademicSchema();
        $this->seedAcademicReferenceData();
    }

    protected function createMinimalAcademicSchema(): void
    {
        Schema::dropIfExists('guru');
        Schema::dropIfExists('penugasan_wali_kelas');
        Schema::dropIfExists('log_audit');
        Schema::dropIfExists('keanggotaan_kelas');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('semester');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('status_siswa');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('tingkat');
        Schema::dropIfExists('jurusan');

        Schema::create('jurusan', function (Blueprint $table) {
            $table->id('id_jurusan');
            $table->string('kode_jurusan', 20)->unique();
            $table->string('nama_jurusan', 120);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id('id_tahun_ajaran');
            $table->string('nama_tahun_ajaran', 120);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->id('id_semester');
            $table->unsignedBigInteger('id_tahun_ajaran');
            $table->string('nama_semester', 80);
            $table->date('tanggal_mulai');
            $table->boolean('is_aktif')->default(false);
            $table->foreign('id_tahun_ajaran')->references('id_tahun_ajaran')->on('tahun_ajaran');
        });

        Schema::create('tingkat', function (Blueprint $table) {
            $table->id('id_tingkat');
            $table->string('nama_tingkat', 30);
            $table->unsignedInteger('urutan');
        });

        Schema::create('status_siswa', function (Blueprint $table) {
            $table->id('id_status_siswa');
            $table->string('kode_status', 40)->unique();
            $table->string('nama_status', 80);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->id('id_siswa');
            $table->unsignedBigInteger('id_status_siswa')->nullable();
            $table->string('nama_lengkap', 160);
            $table->string('nisn', 30)->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->foreign('id_status_siswa')->references('id_status_siswa')->on('status_siswa')->nullOnDelete();
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->string('nama_kelas', 120);
            $table->unsignedBigInteger('id_tahun_ajaran')->nullable();
            $table->unsignedBigInteger('id_tingkat')->nullable();
            $table->unsignedBigInteger('id_jurusan')->nullable();
            $table->integer('kapasitas')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->foreign('id_tahun_ajaran')->references('id_tahun_ajaran')->on('tahun_ajaran')->nullOnDelete();
            $table->foreign('id_tingkat')->references('id_tingkat')->on('tingkat')->nullOnDelete();
            $table->foreign('id_jurusan')->references('id_jurusan')->on('jurusan')->nullOnDelete();
        });

        Schema::create('keanggotaan_kelas', function (Blueprint $table) {
            $table->id('id_keanggotaan');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_semester');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 30)->default('aktif');
            $table->foreign('id_siswa')->references('id_siswa')->on('siswa')->restrictOnDelete();
            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->restrictOnDelete();
            $table->foreign('id_semester')->references('id_semester')->on('semester')->restrictOnDelete();
        });

        Schema::create('log_audit', function (Blueprint $table) {
            $table->id('id_log');
            $table->string('nama_tabel', 80);
            $table->string('operasi', 20);
            $table->string('id_data', 50)->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
            $table->string('username_pengubah', 120)->nullable();
            $table->timestamp('waktu')->useCurrent();
        });
    }

    protected function seedAcademicReferenceData(): void
    {
        DB::table('jurusan')->insert([
            ['id_jurusan' => 1, 'kode_jurusan' => 'MIA', 'nama_jurusan' => 'MIA', 'is_aktif' => true],
            ['id_jurusan' => 2, 'kode_jurusan' => 'IIS', 'nama_jurusan' => 'IIS', 'is_aktif' => true],
        ]);

        DB::table('tingkat')->insert([
            ['id_tingkat' => 1, 'nama_tingkat' => 'X', 'urutan' => 1],
            ['id_tingkat' => 2, 'nama_tingkat' => 'XI', 'urutan' => 2],
            ['id_tingkat' => 3, 'nama_tingkat' => 'XII', 'urutan' => 3],
        ]);

        DB::table('status_siswa')->insert([
            ['id_status_siswa' => 1, 'kode_status' => 'aktif', 'nama_status' => 'Aktif', 'is_aktif' => true],
            ['id_status_siswa' => 2, 'kode_status' => 'pindah', 'nama_status' => 'Pindah', 'is_aktif' => true],
            ['id_status_siswa' => 3, 'kode_status' => 'keluar', 'nama_status' => 'Keluar', 'is_aktif' => true],
            ['id_status_siswa' => 4, 'kode_status' => 'lulus', 'nama_status' => 'Lulus', 'is_aktif' => true],
        ]);
    }

    protected function seedYear(int $idTahun, string $nama, string $mulai, string $selesai): void
    {
        DB::table('tahun_ajaran')->insert([
            'id_tahun_ajaran' => $idTahun,
            'nama_tahun_ajaran' => $nama,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'is_aktif' => true,
        ]);

        DB::table('semester')->insert([
            ['id_semester' => ($idTahun * 10) + 1, 'id_tahun_ajaran' => $idTahun, 'nama_semester' => 'Ganjil', 'tanggal_mulai' => $mulai, 'is_aktif' => true],
            ['id_semester' => ($idTahun * 10) + 2, 'id_tahun_ajaran' => $idTahun, 'nama_semester' => 'Genap', 'tanggal_mulai' => $selesai, 'is_aktif' => false],
        ]);
    }

    protected function attachStudents(array $names): array
    {
        $ids = [];
        $idBerikutnya = max(1000, (int) DB::table('siswa')->max('id_siswa')) + 1;

        foreach ($names as $index => $nama) {
            $id = $idBerikutnya + $index;
            DB::table('siswa')->insert([
                'id_siswa' => $id,
                'nama_lengkap' => $nama,
                'nisn' => 'NISN-'.$id,
            ]);
            $ids[] = $id;
        }

        return $ids;
    }

    public function test_kelas_yang_sudah_ada_dapat_diubah_duplikat_ditolak_dan_identitas_berisi_siswa_dikunci(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
            $table->id('id_penugasan_wali_kelas');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_semester');
            $table->timestamp('ditugaskan_pada')->nullable();
        });

        DB::table('kelas')->insert([
            ['id_kelas' => 1, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 30, 'is_aktif' => true],
            ['id_kelas' => 2, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 30, 'is_aktif' => true],
        ]);

        $payload = [
            'aksi' => 'kelas',
            'id_kelas' => 1,
            'id_tahun_ajaran' => 2025,
            'id_semester' => 20251,
            'id_tingkat' => 1,
            'id_jurusan' => 1,
            'nama_kelas' => 'X MIA 1',
            'kapasitas' => 30,
        ];

        $response = $this->withoutMiddleware([EnsureRole::class])
            ->post(route('tu.kelas.wali'), $payload);

        $response->assertRedirect(route('tu.kelas', ['semester' => 20251]));
        $this->assertDatabaseCount('kelas', 2);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025]);

        $idKelas = DB::table('kelas')->value('id_kelas');
        $this->post(route('tu.kelas.wali'), array_merge($payload, [
            'id_kelas' => $idKelas,
            'nama_kelas' => 'X MIA 1 Edit',
            'kapasitas' => 32,
        ]))
            ->assertRedirect(route('tu.kelas', ['semester' => 20251]));
        $this->assertDatabaseHas('kelas', ['id_kelas' => $idKelas, 'nama_kelas' => 'X MIA 1 Edit', 'kapasitas' => 32]);

        $duplicate = array_merge($payload, ['nama_kelas' => 'XI MIA 1']);

        try {
            (new KelasController)->simpan(Request::create('/kelas', 'POST', $duplicate));
            $this->fail('Nama kelas duplikat seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nama_kelas', $exception->errors());
        }

        $idSiswa = $this->attachStudents(['Siswa kelas X'])[0];
        DB::table('keanggotaan_kelas')->insert([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'id_semester' => 20251,
            'tanggal_mulai' => '2025-07-01',
            'status' => 'aktif',
        ]);

        $ubahIdentitas = Request::create('/kelas', 'POST', [
            'id_kelas' => $idKelas,
            'id_tahun_ajaran' => 2025,
            'id_semester' => 20251,
            'id_tingkat' => 2,
            'id_jurusan' => 1,
            'nama_kelas' => 'XI MIA 1',
            'kapasitas' => 30,
        ]);

        try {
            (new KelasController)->simpan($ubahIdentitas);
            $this->fail('Identitas kelas berisi siswa seharusnya dikunci.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nama_kelas', $exception->errors());
        }

        $this->assertDatabaseHas('kelas', ['id_kelas' => $idKelas, 'nama_kelas' => 'X MIA 1 Edit']);
    }

    public function test_kelas_baru_tidak_dapat_dibuat_melalui_form_manajemen_kelas(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        $this->withoutMiddleware([EnsureRole::class])
            ->post(route('tu.kelas.wali'), [
                'aksi' => 'kelas',
                'id_tahun_ajaran' => 2025,
                'id_semester' => 20251,
                'id_tingkat' => 1,
                'id_jurusan' => 1,
                'nama_kelas' => 'X MIA 1',
                'kapasitas' => 30,
            ])
            ->assertSessionHasErrors('id_kelas');

        $this->assertDatabaseCount('kelas', 0);
    }

    public function test_daftar_kelas_dapat_difilter_berdasarkan_tingkat_dan_jurusan(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        Schema::create('role', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role');
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->unsignedBigInteger('id_pengguna');
            $table->string('nama_guru');
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
            $table->id('id_penugasan_wali_kelas');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_semester');
            $table->timestamp('ditugaskan_pada')->nullable();
        });

        DB::table('kelas')->insert([
            ['id_kelas' => 1, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 2, 'nama_kelas' => 'X MIA 2', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 3, 'nama_kelas' => 'X MIA 10', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 4, 'nama_kelas' => 'X IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 5, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 6, 'nama_kelas' => 'XI IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $controller = new KelasController;
        $kelasUntukFilter = fn (array $filters) => $controller->index(Request::create(
            '/tu/kelas',
            'GET',
            ['semester' => 20251] + $filters
        ))->getData()['kelas']->pluck('nama_kelas')->all();

        $this->assertSame(
            ['X MIA 1', 'X MIA 2', 'X MIA 10', 'X IIS 1'],
            $kelasUntukFilter(['tingkat' => 'X'])
        );
        $this->assertSame(
            ['X IIS 1', 'XI IIS 1'],
            $kelasUntukFilter(['jurusan' => 'IIS'])
        );
        $this->assertSame(
            ['X MIA 1', 'X MIA 2', 'X MIA 10', 'XI MIA 1'],
            $kelasUntukFilter(['jurusan' => 'MIA'])
        );
        $this->assertSame(
            ['X MIA 1', 'X MIA 2', 'X MIA 10'],
            $kelasUntukFilter(['tingkat' => 'X', 'jurusan' => 'MIA'])
        );
    }

    public function test_seluruh_kelas_standar_dibuat_dan_proses_ulang_tidak_menggandakan(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        $hasil = (new KelasMassalService)->buat(2025);

        $this->assertSame(120, $hasil['dibuat']);
        $this->assertSame(0, $hasil['dilewati']);
        $this->assertSame(120, $hasil['total']);
        $this->assertDatabaseCount('kelas', 120);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'X MIA 1', 'id_tingkat' => 1, 'id_jurusan' => 1]);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'X MIA 20']);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'XI IIS 1', 'id_tingkat' => 2, 'id_jurusan' => 2]);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'XII IIS 20', 'id_tingkat' => 3, 'id_jurusan' => 2]);

        $ulang = (new KelasMassalService)->buat(2025);

        $this->assertSame(0, $ulang['dibuat']);
        $this->assertSame(120, $ulang['dilewati']);
        $this->assertDatabaseCount('kelas', 120);
    }

    public function test_aksi_buat_otomatis_dari_halaman_kelas_menghasilkan_120_kelas(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        $this->withoutMiddleware([EnsureRole::class])
            ->post(route('tu.kelas.wali'), [
                'aksi' => 'buat',
                'id_tahun_ajaran' => 2025,
                'id_semester' => 20251,
            ])
            ->assertRedirect(route('tu.kelas', ['semester' => 20251]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('kelas', 120);
    }

    public function test_struktur_kelas_tahun_sebelumnya_dapat_diduplikasi_tanpa_menyalin_siswa(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 10, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 11, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $siswa = $this->attachStudents(['Siswa Lama 1', 'Siswa Lama 2']);
        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 1, 'id_siswa' => $siswa[0], 'id_kelas' => 10, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 2, 'id_siswa' => $siswa[1], 'id_kelas' => 10, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $request = Request::create('/kelas', 'POST', [
            'id_tahun_ajaran' => 2025,
            'id_semester' => 20251,
        ]);

        $response = (new KelasController)->duplikat($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025]);
        $this->assertDatabaseHas('kelas', ['nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025]);
        $this->assertSame(2, DB::table('keanggotaan_kelas')->count());
    }

    public function test_kenaikan_kelas_massal_menaikkan_seluruh_kelas_asal_dan_mengecualikan_xii(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 20, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 21, 'nama_kelas' => 'X IIS 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 22, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 23, 'nama_kelas' => 'XI IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 24, 'nama_kelas' => 'XII MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 3, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $siswa = $this->attachStudents(['MIA A', 'MIA B', 'IIS A', 'IIS B', 'XII A']);

        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 10, 'id_siswa' => $siswa[0], 'id_kelas' => 20, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 11, 'id_siswa' => $siswa[1], 'id_kelas' => 20, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 12, 'id_siswa' => $siswa[2], 'id_kelas' => 21, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 13, 'id_siswa' => $siswa[3], 'id_kelas' => 21, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 14, 'id_siswa' => $siswa[4], 'id_kelas' => 24, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $hasil = (new KenaikanKelasService)->proses(2024, [$siswa[0], $siswa[1], $siswa[2], $siswa[3]]);

        $this->assertSame(4, $hasil['naik']);
        $this->assertSame(0, $hasil['tidak_naik']);
        $this->assertSame(0, $hasil['lulus']);
        $this->assertSame(0, $hasil['tanpa_tujuan']);

        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[0], 'status' => 'aktif', 'id_kelas' => 22]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[1], 'status' => 'aktif', 'id_kelas' => 22]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[2], 'status' => 'aktif', 'id_kelas' => 23]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[3], 'status' => 'aktif', 'id_kelas' => 23]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[4], 'status' => 'aktif', 'id_kelas' => 24]);
    }

    public function test_siswa_kelas_xii_benar_benar_tidak_dinaikkan_ke_tingkat_berikutnya(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 30, 'nama_kelas' => 'XII MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 3, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 31, 'nama_kelas' => 'XII IIS 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 3, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $idSiswa = $this->attachStudents(['Siswa XII'])[0];
        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 30, 'id_siswa' => $idSiswa, 'id_kelas' => 30, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $hasil = (new KenaikanKelasService)->proses(2024, [$idSiswa]);

        $this->assertSame(0, $hasil['naik']);
        $this->assertSame(0, $hasil['tidak_naik']);
        $this->assertSame(1, $hasil['lulus']);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $idSiswa, 'status' => 'lulus', 'id_kelas' => 30]);
    }

    public function test_pemetaan_otomatis_menentukan_kelas_tujuan_mia_dan_iis_dengan_baris_sumber_masing_masing(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 40, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 41, 'nama_kelas' => 'X IIS 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 42, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 43, 'nama_kelas' => 'XI IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $rencana = (new KenaikanKelasService)->pratinjau(2024);
        $this->assertCount(2, $rencana['baris']);
        $this->assertSame(0, count($rencana['peringatan']));

        $barisByAsal = collect($rencana['baris'])->keyBy('kelas_asal.nama');
        $this->assertSame('XI MIA 1', $barisByAsal['X MIA 1']['kelas_tujuan']['nama']);
        $this->assertSame('XI IIS 1', $barisByAsal['X IIS 1']['kelas_tujuan']['nama']);
        $this->assertSame(40, $barisByAsal['X MIA 1']['kelas_asal']['id']);
        $this->assertSame(41, $barisByAsal['X IIS 1']['kelas_asal']['id']);
    }

    public function test_kelas_tujuan_yang_tidak_tersedia_menolak_seluruh_proses(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 50, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 51, 'nama_kelas' => 'XI IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 2, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $idSiswa = $this->attachStudents(['Tidak Punya Tujuan'])[0];
        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 50, 'id_siswa' => $idSiswa, 'id_kelas' => 50, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $this->expectException(ValidationException::class);
        (new KenaikanKelasService)->proses(2024, [$idSiswa]);

        $this->assertDatabaseCount('keanggotaan_kelas', 1);
    }

    public function test_siswa_yang_tidak_dipilih_tidak_naik_dan_riwayat_lama_tidak_tertimpa(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 60, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 61, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 62, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $siswa = $this->attachStudents(['Tetap Di Kelas', 'Nanti Naik']);
        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 60, 'id_siswa' => $siswa[0], 'id_kelas' => 60, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
            ['id_keanggotaan' => 61, 'id_siswa' => $siswa[1], 'id_kelas' => 60, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $hasil = (new KenaikanKelasService)->proses(2024, [$siswa[1]]);

        $this->assertSame(1, $hasil['naik']);
        $this->assertSame(1, $hasil['tidak_naik']);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[0], 'status' => 'aktif', 'id_kelas' => 61]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[1], 'status' => 'aktif', 'id_kelas' => 62]);
        $this->assertDatabaseHas('keanggotaan_kelas', ['id_siswa' => $siswa[0], 'status' => 'tidak_naik_kelas']);
    }

    public function test_riwayat_keanggotaan_lama_tidak_dihapus_dan_status_baru_tercatat(): void
    {
        $this->seedYear(2024, '2024/2025', '2024-07-01', '2025-06-30');
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 70, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2024, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
            ['id_kelas' => 71, 'nama_kelas' => 'XI MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 2, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
        ]);

        $idSiswa = $this->attachStudents(['Riwayat Siswa'])[0];
        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 70, 'id_siswa' => $idSiswa, 'id_kelas' => 70, 'id_semester' => 20241, 'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        (new KenaikanKelasService)->proses(2024, [$idSiswa]);

        $rows = DB::table('keanggotaan_kelas')->where('id_siswa', $idSiswa)->orderBy('id_keanggotaan')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('naik_kelas', $rows[0]->status);
        $this->assertSame('aktif', $rows[1]->status);
        $this->assertSame(71, (int) $rows[1]->id_kelas);
        $this->assertNotNull($rows[0]->tanggal_selesai);
    }

    public function test_tu_penempatan_page_route_is_removed(): void
    {
        $this->get('/tu/penempatan')->assertNotFound();
    }

    public function test_tu_penempatan_submission_route_is_removed(): void
    {
        $this->post('/tu/penempatan')->assertNotFound();
    }

    public function test_tu_penempatan_does_not_expose_a_submission_handler(): void
    {
        $this->assertFalse(method_exists(AksiFormController::class, 'penempatanSimpan'));
    }

    public function test_tu_penempatan_listing_action_is_removed(): void
    {
        $this->assertFalse(method_exists(PageController::class, 'penempatan'));
    }

    public function test_indeks_membatasi_keanggotaan_aktif_dan_mengizinkan_riwayat_nonaktif(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');

        DB::table('kelas')->insert([
            ['id_kelas' => 83, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'kapasitas' => 32, 'is_aktif' => true],
        ]);
        $idSiswa = $this->attachStudents(['Riwayat Indeks'])[0];
        $migration = require database_path('migrations/2026_11_13_152848_add_unique_active_keanggotaan_index.php');
        $migration->up();

        DB::table('keanggotaan_kelas')->insert([
            ['id_keanggotaan' => 100, 'id_siswa' => $idSiswa, 'id_kelas' => 83, 'id_semester' => 20251, 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-03-01', 'status' => 'pindah'],
            ['id_keanggotaan' => 101, 'id_siswa' => $idSiswa, 'id_kelas' => 83, 'id_semester' => 20251, 'tanggal_mulai' => '2025-03-02', 'tanggal_selesai' => '2025-06-01', 'status' => 'keluar'],
            ['id_keanggotaan' => 102, 'id_siswa' => $idSiswa, 'id_kelas' => 83, 'id_semester' => 20251, 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => null, 'status' => 'aktif'],
        ]);

        $this->expectException(QueryException::class);
        DB::table('keanggotaan_kelas')->insert([
            'id_keanggotaan' => 103,
            'id_siswa' => $idSiswa,
            'id_kelas' => 83,
            'id_semester' => 20251,
            'tanggal_mulai' => '2025-07-15',
            'tanggal_selesai' => null,
            'status' => 'aktif',
        ]);
    }

    public function test_tu_penempatan_endpoint_is_unavailable_for_new_students(): void
    {
        $this->post('/tu/penempatan', ['id_siswa' => [1]])->assertNotFound();
    }

    public function test_tu_penempatan_endpoint_is_unavailable_for_previously_assigned_students(): void
    {
        $this->post('/tu/penempatan', ['id_siswa' => [1], 'id_kelas' => 50])->assertNotFound();
    }

    public function test_wali_kelas_hanya_menangani_siswa_di_kelas_yang_diampu(): void
    {
        $this->seedYear(2025, '2025/2026', '2025-07-01', '2026-06-30');
        DB::table('kelas')->insert([
            ['id_kelas' => 50, 'nama_kelas' => 'X MIA 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 1, 'is_aktif' => true],
            ['id_kelas' => 51, 'nama_kelas' => 'X IIS 1', 'id_tahun_ajaran' => 2025, 'id_tingkat' => 1, 'id_jurusan' => 2, 'is_aktif' => true],
        ]);
        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->unsignedBigInteger('id_pengguna');
            $table->boolean('is_aktif')->default(true);
        });
        Schema::create('penugasan_wali_kelas', function (Blueprint $table) {
            $table->id('id_penugasan_wali_kelas');
            $table->unsignedBigInteger('id_kelas');
            $table->unsignedBigInteger('id_guru');
            $table->unsignedBigInteger('id_semester');
        });

        $siswa = $this->attachStudents(['Siswa Diampu', 'Siswa Kelas Lain']);
        DB::table('guru')->insert([
            ['id_guru' => 8, 'id_pengguna' => 77, 'is_aktif' => true],
            ['id_guru' => 9, 'id_pengguna' => 78, 'is_aktif' => false],
        ]);
        DB::table('penugasan_wali_kelas')->insert([
            'id_kelas' => 50,
            'id_guru' => 8,
            'id_semester' => 20251,
        ]);
        DB::table('keanggotaan_kelas')->insert([
            ['id_siswa' => $siswa[0], 'id_kelas' => 50, 'id_semester' => 20251, 'tanggal_mulai' => '2025-07-01', 'status' => 'aktif'],
            ['id_siswa' => $siswa[1], 'id_kelas' => 51, 'id_semester' => 20251, 'tanggal_mulai' => '2025-07-01', 'status' => 'aktif'],
        ]);

        $service = new PengajuanPerubahanService;

        $this->assertTrue($service->waliKelasMenanganiSiswa(77, $siswa[0]));
        $this->assertFalse($service->waliKelasMenanganiSiswa(77, $siswa[1]));
        $this->assertFalse($service->waliKelasMenanganiSiswa(78, $siswa[0]));
    }
}
