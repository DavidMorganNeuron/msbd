<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Http\Middleware\EnsureRole;
use App\Models\Pengguna;
use App\Services\PpdbService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PpdbFormulirFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->createPpdbTestSchema();
        $this->seedPpdbReferenceData();
        Storage::fake('public');
    }

    protected function createPpdbTestSchema(): void
    {
        Schema::dropIfExists('dokumen_siswa');
        Schema::dropIfExists('ppdb_keluar');
        Schema::dropIfExists('ppdb_beasiswa');
        Schema::dropIfExists('ppdb_prestasi');
        Schema::dropIfExists('ppdb_orang_tua');
        Schema::dropIfExists('ppdb_periodik');
        Schema::dropIfExists('ppdb_alamat');
        Schema::dropIfExists('pendaftaran_ppdb');
        Schema::dropIfExists('ref_checklist_item');
        Schema::dropIfExists('ref_alasan_pengembalian');
        Schema::dropIfExists('jenis_dokumen');
        Schema::dropIfExists('log_login');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('agama');
        Schema::dropIfExists('role_hak_akses');
        Schema::dropIfExists('hak_akses');
        Schema::dropIfExists('role');
        Schema::dropIfExists('pengguna');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('tingkat');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('pengaturan_sistem');
        Schema::dropIfExists('sekolah');
        Schema::dropIfExists('kota');
        Schema::dropIfExists('provinsi');

        Schema::create('pengaturan_sistem', function (Blueprint $table) {
            $table->id('id_pengaturan');
            $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
            $table->string('kunci', 120)->unique();
            $table->text('nilai')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamp('tgl_diubah')->nullable();
        });

        Schema::create('sekolah', function (Blueprint $table) {
            $table->id('id_sekolah');
            $table->string('nama_sekolah', 180)->unique();
            $table->string('npsn', 20)->nullable();
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('provinsi', function (Blueprint $table) {
            $table->id('id_provinsi');
            $table->string('nama_provinsi', 120)->unique();
            $table->string('kode_provinsi', 5)->nullable();
        });

        Schema::create('kota', function (Blueprint $table) {
            $table->id('id_kota');
            $table->unsignedBigInteger('id_provinsi');
            $table->string('nama_kota', 120);
            $table->unique(['id_provinsi', 'nama_kota']);
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id('id_tahun_ajaran');
            $table->string('nama_tahun_ajaran', 40);
            $table->boolean('is_aktif')->default(false);
        });

        Schema::create('tingkat', function (Blueprint $table) {
            $table->id('id_tingkat');
            $table->string('nama_tingkat', 20);
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->unsignedBigInteger('id_tahun_ajaran');
            $table->unsignedBigInteger('id_tingkat');
            $table->string('nama_kelas', 80);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role')->nullable();
            $table->string('username', 100)->nullable()->unique();
            $table->string('password_hash', 255)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->timestamp('terakhir_login')->nullable();
        });

        Schema::create('log_login', function (Blueprint $table) {
            $table->id('id_log_login');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('username_dicoba', 100);
            $table->boolean('berhasil');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('waktu')->nullable();
        });

        Schema::create('role', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role', 80);
            $table->string('kode_role', 40)->nullable();
        });

        Schema::create('hak_akses', function (Blueprint $table) {
            $table->id('id_hak_akses');
            $table->string('kode_hak_akses', 80);
            $table->string('nama_hak_akses', 160);
        });

        Schema::create('role_hak_akses', function (Blueprint $table) {
            $table->unsignedBigInteger('id_role');
            $table->unsignedBigInteger('id_hak_akses');
            $table->primary(['id_role', 'id_hak_akses']);
        });

        Schema::create('pekerjaan', function (Blueprint $table) {
            $table->id('id_pekerjaan');
            $table->string('nama_pekerjaan', 120);
            $table->string('kode_pekerjaan', 10)->nullable();
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->id('id_siswa');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('nama_lengkap', 160);
            $table->string('nisn', 30)->nullable();
            $table->string('nik', 30)->nullable();
            $table->string('jenis_kelamin', 2)->nullable();
            $table->string('tempat_lahir', 120)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('email', 160)->nullable();
            $table->string('no_telepon', 40)->nullable();
        });

        Schema::create('agama', function (Blueprint $table) {
            $table->id('id_agama');
            $table->string('nama_agama', 80);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('jenis_dokumen', function (Blueprint $table) {
            $table->id('id_jenis_dokumen');
            $table->string('nama_dokumen', 120);
        });

        Schema::create('ref_checklist_item', function (Blueprint $table) {
            $table->id('id_item');
            $table->unsignedBigInteger('id_jenis_dokumen');
            $table->string('nama_item', 180);
            $table->boolean('wajib')->default(true);
        });

        Schema::create('ref_alasan_pengembalian', function (Blueprint $table) {
            $table->id('id_alasan');
            $table->string('nama_alasan', 150);
        });

        Schema::create('pendaftaran_ppdb', function (Blueprint $table) {
            $table->id('id_pendaftaran');
            $table->string('nomor_pendaftaran', 40)->nullable()->unique();
            $table->string('token_draf', 64)->nullable()->unique();
            $table->string('status_pendaftaran', 30)->default('draf');
            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->unsignedBigInteger('id_pengguna_pemeriksa')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->smallInteger('langkah_terakhir')->default(0);
            $table->string('jenis_kelamin', 1)->nullable();
            $table->string('nik', 16)->nullable();
            $table->string('no_kk', 16)->nullable();
            $table->string('tempat_lahir', 80)->nullable();
            $table->string('email', 120)->nullable();
            $table->unsignedBigInteger('id_agama')->nullable();
            $table->foreign('id_agama')->references('id_agama')->on('agama');
            $table->string('jenjang_tujuan', 10)->nullable();
            $table->string('kelas_tujuan', 80)->nullable();
            $table->string('tahun_pelajaran', 20)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('nama_ortu_wali', 120)->nullable();
            $table->boolean('persetujuan_ortu')->default(false);
            $table->boolean('pernyataan')->default(false);
            $table->unsignedBigInteger('id_kelas_diterima')->nullable();
            $table->string('pendaftaran_untuk', 30)->nullable();
            $table->string('no_seri_skhun', 50)->nullable();
            $table->string('tahun_skhun', 4)->nullable();
            $table->string('no_seri_ijazah', 50)->nullable();
            $table->string('tahun_ijazah', 4)->nullable();
            $table->string('no_peserta_ujian', 50)->nullable();
            $table->string('hobi', 10)->nullable();
            $table->string('cita_cita', 10)->nullable();
            $table->smallInteger('anak_ke')->nullable();
            $table->string('status_keluarga', 50)->nullable();
            $table->string('berkebutuhan_khusus', 10)->nullable();
            $table->string('tempat_tinggal', 10)->nullable();
            $table->string('moda_transportasi', 10)->nullable();
            $table->string('penerima_kps', 10)->nullable();
            $table->string('no_kps', 50)->nullable();
            $table->string('kewarganegaraan', 5)->nullable();
            $table->string('negara', 80)->nullable();
            $table->string('layak_pip', 10)->nullable();
            $table->string('alasan_pip', 10)->nullable();
            $table->string('nama_calon_siswa', 150)->nullable();
            $table->string('nisn', 20)->nullable();
            $table->string('sekolah_asal', 200)->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telepon', 30)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('no_telepon_alamat', 30)->nullable();
        });

        Schema::create('ppdb_alamat', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pendaftaran')->primary();
            $table->text('jalan')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('kelurahan', 80)->nullable();
            $table->string('kecamatan', 80)->nullable();
            $table->string('kota', 80)->nullable();
            $table->string('provinsi', 80)->nullable();
            $table->string('dusun', 80)->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('telp', 30)->nullable();
            $table->string('no_hp', 30)->nullable();
        });

        Schema::create('ppdb_periodik', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pendaftaran')->primary();
            $table->smallInteger('tinggi_badan')->nullable();
            $table->smallInteger('berat_badan')->nullable();
            $table->string('gol_darah', 3)->nullable();
            $table->string('jarak', 5)->nullable();
            $table->decimal('jarak_km', 5, 2)->nullable();
            $table->smallInteger('waktu_tempuh_jam')->nullable();
            $table->smallInteger('waktu_tempuh_menit')->nullable();
            $table->smallInteger('jumlah_saudara')->nullable();
        });

        Schema::create('ppdb_orang_tua', function (Blueprint $table) {
            $table->id('id_orang_tua');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('hubungan', 10);
            $table->string('nama_lengkap', 120)->nullable();
            $table->string('nik', 16)->nullable();
            $table->string('tempat_lahir', 80)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('pendidikan', 10)->nullable();
            $table->unsignedBigInteger('id_pekerjaan')->nullable();
            $table->string('penghasilan', 10)->nullable();
            $table->string('berkebutuhan_khusus', 10)->nullable();
            $table->string('no_telepon', 30)->nullable();
            $table->text('alamat')->nullable();
            $table->unique(['id_pendaftaran', 'hubungan']);
        });

        Schema::create('ppdb_prestasi', function (Blueprint $table) {
            $table->id('id_prestasi');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis', 10)->nullable();
            $table->string('tingkat', 10)->nullable();
            $table->string('nama_prestasi', 150)->nullable();
            $table->string('tahun', 4)->nullable();
            $table->string('penyelenggara', 150)->nullable();
        });

        Schema::create('ppdb_beasiswa', function (Blueprint $table) {
            $table->id('id_beasiswa');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis', 10)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('tahun_mulai', 4)->nullable();
            $table->string('tahun_selesai', 4)->nullable();
        });

        Schema::create('ppdb_keluar', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pendaftaran')->primary();
            $table->string('keluar_karena', 10)->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->text('alasan')->nullable();
        });

        Schema::create('dokumen_siswa', function (Blueprint $table) {
            $table->id('id_dokumen');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis_dokumen', 120);
            $table->string('nama_file_asli', 200)->nullable();
            $table->string('path_file', 500)->nullable();
            $table->string('status_verifikasi', 30)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('diunggah_pada')->nullable();
        });
    }

    protected function seedPpdbReferenceData(): void
    {
        DB::table('agama')->insert([
            ['id_agama' => 1, 'nama_agama' => 'Kristen', 'is_aktif' => true],
            ['id_agama' => 7, 'nama_agama' => 'Katolik', 'is_aktif' => true],
            ['id_agama' => 8, 'nama_agama' => 'Islam', 'is_aktif' => true],
            ['id_agama' => 9, 'nama_agama' => 'Hindu', 'is_aktif' => true],
            ['id_agama' => 10, 'nama_agama' => 'Buddha', 'is_aktif' => true],
            ['id_agama' => 11, 'nama_agama' => 'Konghucu', 'is_aktif' => true],
        ]);

        DB::table('tahun_ajaran')->insert([
            ['id_tahun_ajaran' => 1, 'nama_tahun_ajaran' => '2026/2027', 'is_aktif' => true],
        ]);

        DB::table('tingkat')->insert([
            ['id_tingkat' => 1, 'nama_tingkat' => 'X'],
            ['id_tingkat' => 2, 'nama_tingkat' => 'XI'],
            ['id_tingkat' => 3, 'nama_tingkat' => 'XII'],
        ]);
        DB::table('kelas')->insert([
            ['id_kelas' => 1, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X IIS 1', 'is_aktif' => true],
            ['id_kelas' => 2, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X MIA 1', 'is_aktif' => true],
            ['id_kelas' => 3, 'id_tahun_ajaran' => 1, 'id_tingkat' => 2, 'nama_kelas' => 'XI IIS 1', 'is_aktif' => true],
            ['id_kelas' => 4, 'id_tahun_ajaran' => 1, 'id_tingkat' => 2, 'nama_kelas' => 'XI MIA 1', 'is_aktif' => true],
            ['id_kelas' => 5, 'id_tahun_ajaran' => 1, 'id_tingkat' => 3, 'nama_kelas' => 'XII IIS 1', 'is_aktif' => true],
            ['id_kelas' => 6, 'id_tahun_ajaran' => 1, 'id_tingkat' => 3, 'nama_kelas' => 'XII MIA 2', 'is_aktif' => true],
        ]);

        DB::table('role')->insert([
            ['id_role' => 1, 'nama_role' => 'Tata Usaha', 'kode_role' => 'tu'],
            ['id_role' => 2, 'nama_role' => 'Siswa', 'kode_role' => 'siswa'],
        ]);

        DB::table('hak_akses')->insert([
            ['id_hak_akses' => 1, 'kode_hak_akses' => 'ppdb.verify', 'nama_hak_akses' => 'Verifikasi PPDB'],
        ]);

        DB::table('pekerjaan')->insert([
            ['id_pekerjaan' => 1, 'nama_pekerjaan' => 'Tidak Bekerja', 'kode_pekerjaan' => '01'],
            ['id_pekerjaan' => 2, 'nama_pekerjaan' => 'Nelayan', 'kode_pekerjaan' => '02'],
            ['id_pekerjaan' => 3, 'nama_pekerjaan' => 'Petani', 'kode_pekerjaan' => '03'],
            ['id_pekerjaan' => 4, 'nama_pekerjaan' => 'Peternak', 'kode_pekerjaan' => '04'],
            ['id_pekerjaan' => 5, 'nama_pekerjaan' => 'PNS/ASN', 'kode_pekerjaan' => '05'],
            ['id_pekerjaan' => 6, 'nama_pekerjaan' => 'Karyawan Swasta', 'kode_pekerjaan' => '06'],
            ['id_pekerjaan' => 7, 'nama_pekerjaan' => 'Pedagang', 'kode_pekerjaan' => '07'],
            ['id_pekerjaan' => 8, 'nama_pekerjaan' => 'Wiraswasta', 'kode_pekerjaan' => '09'],
            ['id_pekerjaan' => 9, 'nama_pekerjaan' => 'Buruh', 'kode_pekerjaan' => '11'],
            ['id_pekerjaan' => 10, 'nama_pekerjaan' => 'Pensiunan', 'kode_pekerjaan' => '12'],
            ['id_pekerjaan' => 11, 'nama_pekerjaan' => 'Lainnya', 'kode_pekerjaan' => '13'],
        ]);

        DB::table('role_hak_akses')->insert([
            ['id_role' => 1, 'id_hak_akses' => 1],
        ]);

        DB::table('pengguna')->insert([
            ['id_pengguna' => 100, 'id_role' => 1, 'username' => 'tu01', 'password_hash' => 'secret', 'is_aktif' => true],
        ]);
    }

    protected function createVerifikasiLogSchema(): void
    {
        Schema::dropIfExists('verifikasi_pendaftaran');
        Schema::create('verifikasi_pendaftaran', function (Blueprint $table) {
            $table->id('id_verifikasi');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->unsignedBigInteger('id_petugas');
            $table->unsignedBigInteger('id_alasan')->nullable();
            $table->string('hasil', 30);
            $table->text('catatan')->nullable();
            $table->timestamp('tgl_verifikasi')->nullable();
        });
    }

    protected function validPpdbPayload(array $overrides = []): array
    {
        $payload = [
            'nomor_pendaftaran' => 'PPDB-20261113-ABC123',
            'tanggal' => '2026-11-13',
            'tahun_pelajaran' => '2026/2027',
            'nis' => '2026',
            'pendaftaran_untuk' => 'SMA',
            'jenjang_tujuan' => 'SMA',
            'kelas_tujuan' => 'X MIA 1',
            'sekolah_asal' => 'SMPN 1 Bandung',
            'nama_lengkap' => 'Rahma Putri',
            'password' => 'SandiAman123',
            'password_confirmation' => 'SandiAman123',
            'jenis_kelamin' => 'P',
            'nisn' => '1234567890',
            'nik' => '3201010101010001',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2013-05-11',
            'agama' => '01',
            'no_kk' => '3201010101010002',
            'no_hp' => '081234567890',
            'email' => 'rahma@example.com',
            'prov' => 'Jawa Barat',
            'kota' => 'Bandung',
            'kec' => 'Coblong',
            'kel' => 'Dago',
            'jalan' => 'Jl. Merdeka No. 1',
            'rt' => '01',
            'rw' => '02',
            'nama_ortu_wali' => 'Ari Putra',
            'ortu_nama_0' => 'Ari Putra',
            'ortu_nik_0' => '3201010101010003',
            'ortu_tanggal_lahir_0' => '1980-01-15',
            'ortu_tempat_lahir_0' => 'Bandung',
            'ortu_pendidikan_0' => '04',
            'ortu_kerja_0' => '05',
            'ortu_penghasilan_0' => '4',
            'ortu_kebutuhan_0' => '00',
            'ortu_hp_0' => '081234567891',
            'ortu_alamat_0' => 'Jl. Merdeka No. 1',
            'ortu_nama_1' => 'Siti Aisyah',
            'ortu_nik_1' => '3201010101010004',
            'ortu_tanggal_lahir_1' => '1983-05-30',
            'ortu_tempat_lahir_1' => 'Bandung',
            'ortu_pendidikan_1' => '04',
            'ortu_kerja_1' => '06',
            'ortu_penghasilan_1' => '4',
            'ortu_kebutuhan_1' => '00',
            'ortu_hp_1' => '081234567892',
            'ortu_alamat_1' => 'Jl. Merdeka No. 1',
            'tinggi_badan' => '160',
            'berat_badan' => '48',
            'gol_darah' => 'O',
            'jarak' => '1',
            'waktu_jam' => '20',
            'waktu_menit' => '30',
            'jumlah_saudara' => '2',
            'prestasi_jenis' => ['1'],
            'prestasi_tingkat' => ['2'],
            'prestasi_nama' => ['Lomba sains'],
            'prestasi_tahun' => ['2024'],
            'prestasi_penyelenggara' => ['Dinas Pendidikan'],
            'beasiswa_jenis' => ['01'],
            'beasiswa_keterangan' => ['Beasiswa prestasi'],
            'beasiswa_tahun_mulai' => ['2025'],
            'beasiswa_tahun_selesai' => ['2026'],
            'persetujuan_ortu' => 'on',
            'pernyataan' => 'on',
            'langkah_terakhir' => 3,
            'berkas_0' => $this->validPdf('kk.pdf'),
            'berkas_1' => $this->validPdf('akta.pdf'),
            'berkas_2' => $this->validPdf('ijazah.pdf'),
            'berkas_3' => $this->validPdf('pasfoto.pdf'),
        ];

        return array_replace_recursive($payload, $overrides);
    }

    protected function validPdf(string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $filename,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
            'application/pdf'
        );
    }

    public function test_halaman_formulir_ppdb_dapat_diakses(): void
    {
        $this->get('/ppdb')
            ->assertOk()
            ->assertSee('Kabupaten/Kota')
            ->assertSee('Pilih Provinsi')
            ->assertSee('id="w-kec" name="kec" type="text"', false)
            ->assertSee('id="w-kel" name="kel" type="text"', false)
            ->assertSee('value="05">lain-lain', false)
            ->assertDontSee('A) PNS')
            ->assertDontSee('1) kurang dari 1 km')
            ->assertDontSee('09) D4/S1')
            ->assertSee('value="SMA" selected', false)
            ->assertSee('value="X MIA 1"', false)
            ->assertSee('value="XII MIA 2"', false)
            ->assertSee('value="XI IIS 1"', false)
            ->assertDontSee('value="SMP"', false)
            ->assertDontSee('value="SD"', false)
            ->assertDontSee('No. Pendaftaran')
            ->assertDontSee('peringatan ganda')
            ->assertSee('Nomor KK dapat digunakan bersama oleh anggota keluarga dalam satu KK.')
            ->assertSee('NIK orang tua/wali dapat sama pada pengajuan saudara dalam satu keluarga.')
            ->assertSee('value="00"', false)
            ->assertSee('Tidak')
            ->assertSee('Hapus')
            ->assertSee('Tunanetra')
            ->assertSee('Tunarungu')
            ->assertSee('Tunawicara')
            ->assertSee('Tunagrahita')
            ->assertSee('Tunadaksa')
            ->assertSee('Tunalaras')
            ->assertSee('Tunaganda')
            ->assertSee('Autisme')
            ->assertDontSee('<select class="input disabled:bg-paper disabled:text-mute/70 disabled:cursor-not-allowed" id="w-kec"', false)
            ->assertDontSee('Pendaftaran Keluar');

        $konten = $this->get('/ppdb')->getContent();
        $this->assertSame(1, substr_count($konten, '<option value="X MIA 1"'));
        $this->assertSame(1, substr_count($konten, '<option value="X IIS 1"'));
    }

    public function test_halaman_status_ppdb_dapat_diakses_dengan_jenis_pencarian(): void
    {
        $this->get('/ppdb/status')->assertOk();
        $this->get('/ppdb/status?cek_nisn=1234567890')->assertOk();
        $this->get('/ppdb/status?cek_kode=PPDB-20261113-ABC123')->assertOk();
    }

    public function test_berkas_dari_pendaftaran_ditolak_dapat_diperbaiki_dari_halaman_status(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-REJECTED-0001',
            'token_draf' => 'token-ling-perbaikan',
            'status_pendaftaran' => 'ditolak',
            'nama_calon_siswa' => 'Ling',
            'nisn' => '1234567890',
            'diajukan_pada' => now(),
            'catatan_verifikasi' => 'Berkas belum jelas.',
        ]);
        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta_lama.pdf',
            'path_file' => 'ppdb/'.$idPendaftaran.'/akta_lama.pdf',
            'status_verifikasi' => 'ditolak',
            'catatan' => 'Berkas belum jelas.',
            'diunggah_pada' => now(),
        ]);

        $this->get('/ppdb/status?cek_nisn=1234567890&cek_kode=PPDB-REJECTED-0001')
            ->assertOk()
            ->assertSee('Perlu perbaikan')
            ->assertSee('Pengajuan perlu diperbaiki')
            ->assertSee(route('ppdb', ['draf' => 'token-ling-perbaikan']), false)
            ->assertSee('Kirim ulang berkas')
            ->assertDontSee('berkas_ulang');
    }

    public function test_tautan_perbaikan_tidak_ditampilkan_tanpa_kedua_identitas(): void
    {
        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-REPAIR-ONLY-01',
            'token_draf' => 'token-private-repair',
            'status_pendaftaran' => 'perlu_perbaikan',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567890',
            'diajukan_pada' => now(),
        ]);

        $this->get('/ppdb/status?cek_nisn=1234567890')
            ->assertOk()
            ->assertDontSee('token-private-repair')
            ->assertDontSee('Kirim ulang berkas');
    }

    public function test_halaman_status_menyediakan_navigasi_kembali_ke_beranda(): void
    {
        $this->get('/ppdb/status')
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee('Beranda');
    }

    public function test_halaman_verifikasi_tu_tidak_mengirimkan_data_checklist_lama(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-CHECKLIST-01',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567890',
            'diajukan_pada' => now(),
        ]);

        $view = (new PageController)->verifikasi($idPendaftaran, app(PpdbService::class));
        $this->assertArrayNotHasKey('checklist', $view->getData());
        $this->assertArrayHasKey('berkas', $view->getData());
        $this->assertArrayHasKey('alasan', $view->getData());
        $this->assertArrayHasKey('detail', $view->getData());
    }

    public function test_halaman_verifikasi_tu_menggunakan_pilihan_dokumen_tanpa_checklist_atau_tombol_tolak(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-01',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567890',
            'diajukan_pada' => now(),
        ]);
        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta.pdf',
            'path_file' => 'ppdb/1/akta.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware(EnsureRole::class)
            ->get(route('tu.verifikasi', $idPendaftaran))
            ->assertOk()
            ->assertSee('Seluruh data pendaftar')
            ->assertSee('hanya dapat dilihat Tata Usaha')
            ->assertSee('Dokumen yang perlu diperbaiki')
            ->assertSee('name="id_dokumen_perbaikan"', false)
            ->assertSee('Dokumen yang perlu diperbaiki (opsional)')
            ->assertDontSee('name="lolos[]"', false)
            ->assertDontSee('name="hasil" value="tolak"', false);
    }

    public function test_tu_dapat_mengembalikan_hanya_dokumen_terpilih_dengan_alasan_dan_catatan(): void
    {
        $this->createVerifikasiLogSchema();
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-02',
            'status_pendaftaran' => 'ditolak',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567891',
            'diajukan_pada' => now(),
        ]);
        $idDokumen = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta.pdf',
            'path_file' => 'ppdb/1/akta.pdf',
            'status_verifikasi' => 'ditolak',
            'diunggah_pada' => now(),
        ]);
        $idDokumenLain = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Kartu Keluarga',
            'nama_file_asli' => 'kk.pdf',
            'path_file' => 'ppdb/1/kk.pdf',
            'status_verifikasi' => 'ditolak',
            'diunggah_pada' => now(),
        ]);
        DB::table('ref_alasan_pengembalian')->insert(['nama_alasan' => 'Berkas buram']);

        $this->withoutMiddleware([EnsureRole::class, ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 100])
            ->from(route('tu.verifikasi', $idPendaftaran))
            ->post(route('tu.verifikasi.simpan'), [
                'id_pendaftaran' => $idPendaftaran,
                'hasil' => 'kembalikan',
                'id_alasan' => 'Berkas buram',
                'id_dokumen_perbaikan' => $idDokumen,
                'catatan' => 'Foto kurang jelas.',
            ])
            ->assertRedirect(route('tu.verifikasi', $idPendaftaran))
            ->assertSessionHas('success', 'Berkas dikembalikan untuk diperbaiki.');

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'perlu_perbaikan',
            'catatan_verifikasi' => 'Alasan: Berkas buram Catatan: Foto kurang jelas.',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumen,
            'status_verifikasi' => 'perlu_perbaikan',
            'catatan' => 'Berkas buram — Foto kurang jelas.',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumenLain,
            'status_verifikasi' => 'menunggu',
        ]);
        $this->assertDatabaseHas('verifikasi_pendaftaran', [
            'id_pendaftaran' => $idPendaftaran,
            'id_alasan' => 1,
            'hasil' => 'perlu_perbaikan',
            'catatan' => 'Foto kurang jelas.',
        ]);

        $this->get('/ppdb/status?cek_nisn=1234567891&cek_kode=PPDB-VERIFIKASI-02')
            ->assertOk()
            ->assertSee('Akta Kelahiran')
            ->assertSee('Alasan: Berkas buram Catatan: Foto kurang jelas.');
    }

    public function test_tu_tidak_dapat_memilih_dokumen_dari_pendaftaran_siswa_lain(): void
    {
        $this->createVerifikasiLogSchema();
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-03',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon A',
            'nisn' => '1234567892',
            'diajukan_pada' => now(),
        ]);
        $idPendaftaranLain = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-04',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon B',
            'nisn' => '1234567893',
            'diajukan_pada' => now(),
        ]);
        $idDokumenLain = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaranLain,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta-lain.pdf',
            'path_file' => 'ppdb/2/akta-lain.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);
        DB::table('ref_alasan_pengembalian')->insert(['nama_alasan' => 'Berkas buram']);

        $this->withoutMiddleware([EnsureRole::class, ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 100])
            ->from(route('tu.verifikasi', $idPendaftaran))
            ->post(route('tu.verifikasi.simpan'), [
                'id_pendaftaran' => $idPendaftaran,
                'hasil' => 'kembalikan',
                'id_alasan' => 'Berkas buram',
                'id_dokumen_perbaikan' => $idDokumenLain,
            ])
            ->assertSessionHasErrors(['id_dokumen_perbaikan']);

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'menunggu',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumenLain,
            'status_verifikasi' => 'menunggu',
        ]);
        $this->assertDatabaseCount('verifikasi_pendaftaran', 0);
    }

    public function test_tu_wajib_memilih_alasan_tetapi_dokumen_perbaikan_bersifat_opsional(): void
    {
        $this->createVerifikasiLogSchema();
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-06',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567895',
            'diajukan_pada' => now(),
        ]);

        $this->withoutMiddleware([EnsureRole::class, ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 100])
            ->from(route('tu.verifikasi', $idPendaftaran))
            ->post(route('tu.verifikasi.simpan'), [
                'id_pendaftaran' => $idPendaftaran,
                'hasil' => 'kembalikan',
            ])
            ->assertSessionHasErrors(['id_alasan'])
            ->assertSessionDoesntHaveErrors('id_dokumen_perbaikan');

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'menunggu',
        ]);
        $this->assertDatabaseCount('verifikasi_pendaftaran', 0);
    }

    public function test_tu_tidak_dapat_menolak_pendaftaran_ppdb(): void
    {
        $this->createVerifikasiLogSchema();
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-05',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567894',
            'diajukan_pada' => now(),
        ]);

        $this->withoutMiddleware([EnsureRole::class, ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 100])
            ->from(route('tu.verifikasi', $idPendaftaran))
            ->post(route('tu.verifikasi.simpan'), [
                'id_pendaftaran' => $idPendaftaran,
                'hasil' => 'tolak',
            ])
            ->assertSessionHasErrors(['hasil']);

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'menunggu',
        ]);
        $this->assertDatabaseCount('verifikasi_pendaftaran', 0);
    }

    public function test_draf_berhasil_dan_tidak_dianggap_final(): void
    {
        $payload = $this->validPpdbPayload([
            'nomor_pendaftaran' => 'PPDB-20261113-DRAFT',
            'nisn' => '1234567891',
            'nik' => '3201010101010005',
            'no_kk' => '3201010101010006',
        ]);

        $payload['persetujuan_ortu'] = 'on';
        $payload['pernyataan'] = 'on';

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb/draf', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('pendaftaran_ppdb', ['status_pendaftaran' => 'draf', 'nisn' => '1234567891']);
        $this->assertDatabaseMissing('pendaftaran_ppdb', ['status_pendaftaran' => 'menunggu', 'nisn' => '1234567891']);
        $this->assertDatabaseMissing('pengguna', ['username' => '1234567891']);
    }

    public function test_draf_parsial_dapat_disimpan_dan_dilanjutkan_dengan_wilayah_manual(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb/draf', [
                'nama_lengkap' => 'Calon Siswa Draf',
                'prov' => 'Jawa Barat',
                'kota' => 'Bandung',
                'kec' => 'Coblong',
                'kel' => 'Dago',
                'langkah_terakhir' => 2,
            ])
            ->assertRedirect();

        $draf = DB::table('pendaftaran_ppdb')->where('nama_calon_siswa', 'Calon Siswa Draf')->first();

        $this->assertNotNull($draf);
        $this->assertSame('draf', $draf->status_pendaftaran);
        $this->assertSame(2, (int) $draf->langkah_terakhir);
        $this->assertDatabaseHas('ppdb_alamat', [
            'id_pendaftaran' => $draf->id_pendaftaran,
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);

        $this->get(route('ppdb', ['draf' => $draf->token_draf]))
            ->assertOk()
            ->assertSee('value="Coblong"', false)
            ->assertSee('value="Dago"', false)
            ->assertSee('id="langkah_terakhir" value="2"', false);
    }

    public function test_kode_jenis_beasiswa_lama_5_disimpan_dengan_format_05(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb/draf', [
                'beasiswa_jenis' => ['5'],
                'beasiswa_keterangan' => ['Beasiswa lain-lain'],
            ])
            ->assertRedirect();

        $idPendaftaran = DB::table('pendaftaran_ppdb')->value('id_pendaftaran');

        $this->assertDatabaseHas('ppdb_beasiswa', [
            'id_pendaftaran' => $idPendaftaran,
            'jenis' => '05',
            'keterangan' => 'Beasiswa lain-lain',
        ]);
    }

    public function test_baris_prestasi_dan_beasiswa_dapat_dihapus_dari_draf(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb/draf', [
                'prestasi_jenis' => ['1'],
                'prestasi_tingkat' => ['2'],
                'prestasi_nama' => ['Juara lomba'],
                'beasiswa_jenis' => ['01'],
                'beasiswa_keterangan' => ['Beasiswa lama'],
            ])
            ->assertRedirect();

        $draf = DB::table('pendaftaran_ppdb')->first();

        $this->assertDatabaseHas('ppdb_prestasi', [
            'id_pendaftaran' => $draf->id_pendaftaran,
            'nama_prestasi' => 'Juara lomba',
        ]);
        $this->assertDatabaseHas('ppdb_beasiswa', [
            'id_pendaftaran' => $draf->id_pendaftaran,
            'keterangan' => 'Beasiswa lama',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb/draf', [
                'token_draf' => $draf->token_draf,
                'prestasi_dikelola' => '1',
                'beasiswa_dikelola' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('ppdb_prestasi', ['id_pendaftaran' => $draf->id_pendaftaran]);
        $this->assertDatabaseMissing('ppdb_beasiswa', ['id_pendaftaran' => $draf->id_pendaftaran]);
    }

    public function test_pendaftaran_menerima_sma_dan_hanya_kelas_jurusan_yang_tersedia(): void
    {
        $salahJenjang = $this->validPpdbPayload([
            'pendaftaran_untuk' => 'TK/SD/SMP/SMA',
            'jenjang_tujuan' => 'SMP',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $salahJenjang)
            ->assertSessionHasErrors(['pendaftaran_untuk', 'jenjang_tujuan']);

        $kelasTidakValid = $this->validPpdbPayload([
            'kelas_tujuan' => 'XIII MIA 1',
            'nisn' => '1234567892',
            'nik' => '3201010101010007',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $kelasTidakValid)
            ->assertSessionHasErrors('kelas_tujuan');

        $this->assertDatabaseCount('pendaftaran_ppdb', 0);
    }

    public function test_pengiriman_final_berhasil_apabila_seluruh_persyaratan_terpenuhi(): void
    {
        $payload = $this->validPpdbPayload([
            'berkebutuhan_khusus' => '08',
            'kelas_tujuan' => 'XII MIA 2',
        ]);

        $response = $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pendaftaran_ppdb', ['status_pendaftaran' => 'menunggu', 'nisn' => '1234567890']);
        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '1234567890', 'berkebutuhan_khusus' => '08']);
        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '1234567890', 'id_agama' => 8]);
        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '1234567890', 'kelas_tujuan' => 'XII MIA 2']);
        $akun = DB::table('pengguna')->where('username', '1234567890')->first();
        $this->assertNotNull($akun);
        $this->assertFalse((bool) $akun->is_aktif);
        $this->assertTrue(Hash::check('SandiAman123', $akun->password_hash));
        $this->assertDatabaseHas('ppdb_alamat', ['id_pendaftaran' => DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran')]);
        $this->assertDatabaseHas('ppdb_orang_tua', ['id_pendaftaran' => DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran'), 'hubungan' => 'ayah']);
        $this->assertDatabaseHas('ppdb_prestasi', ['id_pendaftaran' => DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran')]);
        $this->assertDatabaseHas('ppdb_beasiswa', ['id_pendaftaran' => DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran')]);
        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran')]);
        $this->assertDatabaseCount('ppdb_keluar', 0);
    }

    public function test_pengajuan_perlu_perbaikan_dapat_dikirim_ulang_dengan_data_lama_dan_tanpa_password(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $this->validPpdbPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pendaftaran = DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->first();
        $akunAwal = DB::table('pengguna')->where('username', '1234567890')->first();
        $idAkta = DB::table('dokumen_siswa')
            ->where('id_pendaftaran', $pendaftaran->id_pendaftaran)
            ->where('jenis_dokumen', 'Akta Kelahiran')
            ->value('id_dokumen');

        DB::table('pendaftaran_ppdb')
            ->where('id_pendaftaran', $pendaftaran->id_pendaftaran)
            ->update([
                'status_pendaftaran' => 'perlu_perbaikan',
                'catatan_verifikasi' => 'Perbaiki data dan akta.',
            ]);
        DB::table('dokumen_siswa')
            ->where('id_dokumen', $idAkta)
            ->update(['status_verifikasi' => 'perlu_perbaikan']);

        $response = $this->get(route('ppdb', ['draf' => $pendaftaran->token_draf]))
            ->assertOk()
            ->assertSee('value="Rahma Putri"', false)
            ->assertSee('name="kelas_tujuan" value="X MIA 1"', false)
            ->assertSee('name="pernyataan"', false)
            ->assertSee('Kirim ulang berkas')
            ->assertSee('name="berkas_1"', false)
            ->assertDontSee('name="password"', false);
        $this->assertSame(1, preg_match('/<input[^>]*name="berkas_1"[^>]*data-wajib/', $response->getContent()));
        $this->assertSame(0, preg_match('/<input[^>]*name="berkas_0"[^>]*data-wajib/', $response->getContent()));
        $this->assertSame(1, preg_match('/<input[^>]*name="pernyataan"[^>]*checked/', $response->getContent()));
        $this->assertSame(1, preg_match('/<input[^>]*name="persetujuan_ortu"[^>]*checked/', $response->getContent()));

        $payload = $this->validPpdbPayload([
            'token_draf' => $pendaftaran->token_draf,
            'nama_lengkap' => 'Rahma Putri Diperbaiki',
            'kelas_tujuan' => 'X IIS 1',
            'berkas_1' => $this->validPdf('akta-baru.pdf'),
        ]);
        unset($payload['password'], $payload['password_confirmation'], $payload['berkas_0'], $payload['berkas_2'], $payload['berkas_3']);

        $this->post('/ppdb', $payload)
            ->assertRedirect(route('ppdb.status', ['cek_kode' => $pendaftaran->nomor_pendaftaran]))
            ->assertSessionHas('success', 'Perbaikan berhasil dikirim. Pendaftaran menunggu pemeriksaan kembali.');

        $this->assertDatabaseCount('pendaftaran_ppdb', 1);
        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $pendaftaran->id_pendaftaran,
            'nama_calon_siswa' => 'Rahma Putri Diperbaiki',
            'kelas_tujuan' => 'X MIA 1',
            'status_pendaftaran' => 'menunggu',
            'catatan_verifikasi' => null,
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_pendaftaran' => $pendaftaran->id_pendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta-baru.pdf',
            'status_verifikasi' => 'menunggu',
        ]);
        $this->assertDatabaseCount('dokumen_siswa', 4);
        $akunAkhir = DB::table('pengguna')->where('username', '1234567890')->first();
        $this->assertSame($akunAwal->id_pengguna, $akunAkhir->id_pengguna);
        $this->assertFalse((bool) $akunAkhir->is_aktif);
    }

    public function test_tu_dapat_melihat_seluruh_data_formulir_tanpa_kontrol_edit_data_siswa(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $this->validPpdbPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $idPendaftaran = DB::table('pendaftaran_ppdb')
            ->where('nisn', '1234567890')
            ->value('id_pendaftaran');

        $this->withoutMiddleware(EnsureRole::class)
            ->get(route('tu.verifikasi', $idPendaftaran))
            ->assertOk()
            ->assertSee('Seluruh data pendaftar')
            ->assertSee('Rahma Putri')
            ->assertSee('3201010101010001')
            ->assertSee('Coblong')
            ->assertSee('Ari Putra')
            ->assertSee('Lomba sains')
            ->assertSee('Beasiswa prestasi')
            ->assertDontSee('name="nama_lengkap"', false)
            ->assertDontSee('name="nik"', false);
    }

    public function test_akun_ppdb_tidak_dapat_login_saat_menunggu_perbaikan_atau_ditolak(): void
    {
        $payload = $this->validPpdbPayload([
            'nisn' => '1234567893',
            'nik' => '3201010101010008',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $payload)
            ->assertRedirect();

        $idPendaftaran = DB::table('pendaftaran_ppdb')->where('nisn', '1234567893')->value('id_pendaftaran');
        $kredensial = ['username' => '1234567893', 'password' => 'SandiAman123', 'role' => 'siswa'];

        $this->post('/masuk', $kredensial)
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $idDokumen = (int) DB::table('dokumen_siswa')
            ->where('id_pendaftaran', $idPendaftaran)
            ->value('id_dokumen');
        app(PpdbService::class)->verifikasi((int) $idPendaftaran, 100, 'perlu_perbaikan', null, $idDokumen);
        $this->post('/masuk', $kredensial)
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        DB::table('pendaftaran_ppdb')
            ->where('id_pendaftaran', $idPendaftaran)
            ->update(['status_pendaftaran' => 'ditolak']);
        DB::table('dokumen_siswa')
            ->where('id_pendaftaran', $idPendaftaran)
            ->update(['status_verifikasi' => 'ditolak']);
        app(PpdbService::class)->verifikasi((int) $idPendaftaran, 100, 'perlu_perbaikan', null, $idDokumen);
        $this->assertSame(
            3,
            DB::table('dokumen_siswa')
                ->where('id_pendaftaran', $idPendaftaran)
                ->where('status_verifikasi', 'menunggu')
                ->count()
        );

        $this->post('/masuk', $kredensial)
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_kata_sandi_baru_harus_dikonfirmasi(): void
    {
        $payload = $this->validPpdbPayload([
            'password_confirmation' => 'TidakSama123',
            'nisn' => '1234567894',
            'nik' => '3201010101010009',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('pendaftaran_ppdb', 0);
        $this->assertDatabaseMissing('pengguna', ['username' => '1234567894']);
    }

    public function test_kelas_tujuan_diurutkan_dengan_nomor_rombel_natural(): void
    {
        DB::table('kelas')->insert([
            ['id_kelas' => 7, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X IIS 10', 'is_aktif' => true],
            ['id_kelas' => 8, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X IIS 2', 'is_aktif' => true],
            ['id_kelas' => 9, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X MIA 10', 'is_aktif' => true],
            ['id_kelas' => 10, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X MIA 2', 'is_aktif' => true],
        ]);

        $kelasTujuan = app(PpdbService::class)->kelasTujuan();

        $this->assertSame([
            'X IIS 1',
            'X IIS 2',
            'X IIS 10',
            'X MIA 1',
            'X MIA 2',
            'X MIA 10',
            'XI IIS 1',
            'XI MIA 1',
            'XII IIS 1',
            'XII MIA 2',
        ], $kelasTujuan);
    }

    public function test_siswa_dapat_mengganti_kata_sandi_dari_menu_akun(): void
    {
        $idPengguna = DB::table('pengguna')->insertGetId([
            'id_role' => 2,
            'username' => 'siswa01',
            'password_hash' => Hash::make('SandiLama123'),
            'is_aktif' => true,
        ]);
        $sesiSiswa = [
            'id_pengguna' => $idPengguna,
            'username' => 'siswa01',
            'role' => 'siswa',
        ];

        $this->withSession($sesiSiswa)
            ->get(route('akun.sandi'))
            ->assertOk()
            ->assertSee('Ganti Kata Sandi');

        Schema::create('log_audit', function (Blueprint $table) {
            $table->id('id_log');
            $table->string('nama_tabel');
            $table->string('operasi');
            $table->string('id_data')->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
            $table->text('username_pengubah')->nullable();
            $table->timestamp('waktu')->nullable();
        });

        $this->withSession($sesiSiswa)
            ->post(route('akun.sandi.update'), [
                'sandi_lama' => 'SandiLama123',
                'sandi_baru' => 'SandiBaru123',
                'sandi_ulang' => 'SandiBaru123',
            ])
            ->assertRedirect(route('akun.sandi'))
            ->assertSessionHas('success', 'Kata sandi berhasil diubah.');

        $this->assertTrue(Hash::check(
            'SandiBaru123',
            DB::table('pengguna')->where('id_pengguna', $idPengguna)->value('password_hash')
        ));
    }

    public function test_pengiriman_final_tidak_menghapus_riwayat_pendaftaran_keluar_yang_sudah_ada(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-OLD-DRAFT',
            'token_draf' => 'existing-draft-token',
            'status_pendaftaran' => 'draf',
            'diajukan_pada' => now(),
        ]);
        DB::table('ppdb_keluar')->insert([
            'id_pendaftaran' => $idPendaftaran,
            'keluar_karena' => '2',
            'tanggal_keluar' => '2025-06-30',
            'alasan' => 'Catatan lama',
        ]);

        $payload = $this->validPpdbPayload([
            'nomor_pendaftaran' => 'PPDB-20261113-KEEP01',
            'token_draf' => 'existing-draft-token',
            'nisn' => '1234567891',
            'nik' => '3201010101010005',
            'no_kk' => '3201010101010006',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('ppdb_keluar', [
            'id_pendaftaran' => $idPendaftaran,
            'keluar_karena' => '2',
            'tanggal_keluar' => '2025-06-30',
            'alasan' => 'Catatan lama',
        ]);
    }

    public function test_input_wajib_yang_tidak_valid_ditolak_oleh_server(): void
    {
        $payload = $this->validPpdbPayload(['nama_lengkap' => '', 'nisn' => '']);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['nama_lengkap', 'nisn']);

        $this->assertDatabaseCount('pendaftaran_ppdb', 0);
    }

    public function test_nisn_atau_nik_duplikat_ditolak_sesuai_aturan_aplikasi(): void
    {
        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-OLD-000001',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Lama',
            'nisn' => '1234567890',
            'nik' => '3201010101010009',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);

        $pesan = PpdbService::PESAN_IDENTITAS_TIDAK_DAPAT_DIPROSES;
        $payloadNisnDuplikat = $this->validPpdbPayload([
            'nik' => '3201010101010013',
        ]);

        $this->get('/ppdb')->assertDontSee('Calon Lama');

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payloadNisnDuplikat)
            ->assertSessionHasErrors(['nisn' => $pesan])
            ->assertDontSee('Calon Lama');

        $payloadNikDuplikat = $this->validPpdbPayload([
            'nisn' => '1234567892',
            'nik' => '3201010101010009',
        ]);

        $this->from('/ppdb')
            ->post('/ppdb', $payloadNikDuplikat)
            ->assertSessionHasErrors(['nisn' => $pesan])
            ->assertDontSee('Calon Lama');

        $this->get('/ppdb')->assertSee(route('ppdb.status'), false);

        $this->assertDatabaseCount('pendaftaran_ppdb', 1);
    }

    public function test_nomor_identitas_yang_sama_diperbolehkan_pada_draf_belum_dikirim(): void
    {
        $this->pasangIndeksIdentitasPpdbTerkirim();

        DB::table('pendaftaran_ppdb')->insert([
            [
                'nomor_pendaftaran' => 'PPDB-DRAF-IDENTITAS-01',
                'status_pendaftaran' => 'draf',
                'nisn' => '1234567898',
                'nik' => '3201010101010018',
            ],
            [
                'nomor_pendaftaran' => 'PPDB-DRAF-IDENTITAS-02',
                'status_pendaftaran' => 'draf',
                'nisn' => '1234567898',
                'nik' => '3201010101010018',
            ],
        ]);

        $this->assertDatabaseCount('pendaftaran_ppdb', 2);
    }

    public function test_indeks_database_mencegah_nisn_ganda_pada_pengajuan_terkirim(): void
    {
        $this->pasangIndeksIdentitasPpdbTerkirim();
        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-NISN-IDENTITAS-01',
            'status_pendaftaran' => 'menunggu',
            'nisn' => '1234567898',
            'nik' => '3201010101010018',
        ]);

        $this->expectException(QueryException::class);

        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-NISN-IDENTITAS-02',
            'status_pendaftaran' => 'menunggu',
            'nisn' => '1234567898',
            'nik' => '3201010101010019',
        ]);
    }

    public function test_indeks_database_mencegah_nik_ganda_pada_pengajuan_terkirim(): void
    {
        $this->pasangIndeksIdentitasPpdbTerkirim();
        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-NIK-IDENTITAS-01',
            'status_pendaftaran' => 'menunggu',
            'nisn' => '1234567898',
            'nik' => '3201010101010018',
        ]);

        $this->expectException(QueryException::class);

        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-NIK-IDENTITAS-02',
            'status_pendaftaran' => 'menunggu',
            'nisn' => '1234567899',
            'nik' => '3201010101010018',
        ]);
    }

    public function test_siswa_dapat_melanjutkan_pengajuan_draft_dengan_nisn_dan_nik_yang_sama(): void
    {
        $payload = $this->validPpdbPayload([
            'nisn' => '1234567895',
            'nik' => '3201010101010015',
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb/draf', $payload)
            ->assertRedirect();

        $draf = DB::table('pendaftaran_ppdb')->where('nisn', '1234567895')->first();
        $payload['token_draf'] = $draf->token_draf;

        $this->post('/ppdb', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pendaftaran_ppdb', 1);
        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $draf->id_pendaftaran,
            'nisn' => '1234567895',
            'nik' => '3201010101010015',
            'status_pendaftaran' => 'menunggu',
        ]);
    }

    public function test_saudara_dapat_menggunakan_nomor_kk_dan_nik_orang_tua_yang_sama(): void
    {
        $this->pasangIndeksIdentitasPpdbTerkirim();

        $nomorKk = '3201010101010020';
        $nikAyah = '3201010101010021';
        $nikIbu = '3201010101010022';
        $payloadPertama = $this->validPpdbPayload([
            'nomor_pendaftaran' => 'PPDB-SAUDARA-0001',
            'nisn' => '1234567896',
            'nik' => '3201010101010016',
            'no_kk' => $nomorKk,
            'ortu_nik_0' => $nikAyah,
            'ortu_nik_1' => $nikIbu,
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->post('/ppdb', $payloadPertama)
            ->assertRedirect();

        $payloadKedua = $this->validPpdbPayload([
            'nomor_pendaftaran' => 'PPDB-SAUDARA-0002',
            'nama_lengkap' => 'Adik Rahma Putri',
            'nisn' => '1234567897',
            'nik' => '3201010101010017',
            'no_kk' => $nomorKk,
            'ortu_nik_0' => $nikAyah,
            'ortu_nik_1' => $nikIbu,
        ]);

        $this->post('/ppdb', $payloadKedua)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pendaftaran_ppdb', 2);
        $this->assertSame(2, DB::table('ppdb_orang_tua')->where('nik', $nikAyah)->count());
        $this->assertSame(2, DB::table('ppdb_orang_tua')->where('nik', $nikIbu)->count());
        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '1234567896', 'no_kk' => $nomorKk]);
        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '1234567897', 'no_kk' => $nomorKk]);
    }

    private function pasangIndeksIdentitasPpdbTerkirim(): void
    {
        $migration = require database_path('migrations/2026_11_14_053216_add_unique_submitted_identity_indexes_to_pendaftaran_ppdb.php');
        $migration->up();
    }

    public function test_api_ppdb_menolak_format_nomor_identitas_yang_tidak_valid(): void
    {
        $this->postJson('/api/ppdb', [
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '12345X7890',
            'nik' => '320101010101000',
            'no_kk' => '3201010101010002x',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nisn', 'nik', 'no_kk']);

        $this->assertDatabaseCount('pendaftaran_ppdb', 0);
    }

    public function test_berkas_wajib_harus_tersedia_saat_pengiriman_final(): void
    {
        $payload = $this->validPpdbPayload([
            'berkas_0' => null,
            'berkas_1' => null,
            'berkas_2' => null,
            'berkas_3' => null,
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['berkas_0']);
    }

    public function test_data_utama_dan_tabel_pendukung_tersimpan_secara_konsisten(): void
    {
        $payload = $this->validPpdbPayload();
        $this->withoutMiddleware([ValidateCsrfToken::class])->post('/ppdb', $payload);

        $id = DB::table('pendaftaran_ppdb')->where('nisn', '1234567890')->value('id_pendaftaran');
        $this->assertNotNull($id);

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $id,
            'nama_calon_siswa' => 'Rahma Putri',
            'status_pendaftaran' => 'menunggu',
        ]);

        $this->assertDatabaseHas('ppdb_alamat', ['id_pendaftaran' => $id, 'kota' => 'Bandung']);
        $this->assertDatabaseHas('ppdb_periodik', ['id_pendaftaran' => $id, 'tinggi_badan' => 160]);
        $this->assertDatabaseHas('ppdb_orang_tua', ['id_pendaftaran' => $id, 'hubungan' => 'ayah', 'nama_lengkap' => 'Ari Putra']);
        $this->assertDatabaseHas('ppdb_prestasi', ['id_pendaftaran' => $id, 'nama_prestasi' => 'Lomba sains']);
        $this->assertDatabaseHas('ppdb_beasiswa', ['id_pendaftaran' => $id, 'keterangan' => 'Beasiswa prestasi']);
        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => $id, 'jenis_dokumen' => 'Kartu Keluarga']);
        $this->assertDatabaseHas('sekolah', ['nama_sekolah' => 'SMPN 1 Bandung']);
        $this->assertDatabaseHas('provinsi', ['nama_provinsi' => 'Jawa Barat']);
        $this->assertDatabaseHas('kota', [
            'id_provinsi' => DB::table('provinsi')->where('nama_provinsi', 'Jawa Barat')->value('id_provinsi'),
            'nama_kota' => 'Bandung',
        ]);
    }

    public function test_kegagalan_penyimpanan_tidak_meninggalkan_data_pendaftaran_sebagian(): void
    {
        $payload = $this->validPpdbPayload([
            'nisn' => '9999999999',
            'berkas_0' => $this->validPdf('kk.pdf'),
            'berkas_1' => $this->validPdf('akta.pdf'),
            'berkas_2' => $this->validPdf('ijazah.pdf'),
            'berkas_3' => null,
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['berkas_3']);

        $this->assertDatabaseCount('pendaftaran_ppdb', 0);
        $this->assertDatabaseCount('dokumen_siswa', 0);
    }

    public function test_alur_perubahan_status_pendaftaran_mengikuti_aturan_yang_diterapkan(): void
    {
        $id = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-STATUS-0001',
            'status_pendaftaran' => 'diverifikasi',
            'nama_calon_siswa' => 'Status Test',
            'nisn' => '2222222222',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);

        foreach (['Kartu Keluarga', 'Akta Kelahiran', 'Ijazah / SKL', 'Pas Foto 3x4'] as $jenis) {
            DB::table('dokumen_siswa')->insert([
                'id_pendaftaran' => $id,
                'jenis_dokumen' => $jenis,
                'nama_file_asli' => strtolower(str_replace([' ', '/'], ['_', '_'], $jenis)).'.pdf',
                'path_file' => 'ppdb/'.$id.'/'.strtolower(str_replace([' ', '/'], ['_', '_'], $jenis)).'.pdf',
                'status_verifikasi' => 'valid',
                'diunggah_pada' => now(),
            ]);
        }

        $user = Pengguna::find(100);
        $this->actingAs($user, 'web');

        $this->postJson('/api/ppdb/'.$id.'/verifikasi', [
            'status_pendaftaran' => 'diterima',
        ])->assertOk();

        $this->assertDatabaseHas('pendaftaran_ppdb', ['nisn' => '2222222222', 'status_pendaftaran' => 'diterima']);
    }

    public function test_api_pengembalian_memerlukan_dan_hanya_memperbarui_dokumen_terpilih(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-API-RETURN-01',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon API',
            'nisn' => '2222222225',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);
        $idDokumen = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta.pdf',
            'path_file' => 'ppdb/1/akta.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);
        $idDokumenLain = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Kartu Keluarga',
            'nama_file_asli' => 'kk.pdf',
            'path_file' => 'ppdb/1/kk.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $user = Pengguna::find(100);
        $this->actingAs($user, 'web');

        $this->postJson('/api/ppdb/'.$idPendaftaran.'/verifikasi', [
            'status_pendaftaran' => 'perlu_perbaikan',
            'id_dokumen_perbaikan' => $idDokumen,
            'catatan_verifikasi' => 'Foto kurang jelas.',
        ])->assertOk();

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'perlu_perbaikan',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumen,
            'status_verifikasi' => 'perlu_perbaikan',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumenLain,
            'status_verifikasi' => 'menunggu',
        ]);
    }

    public function test_tu_dapat_mengembalikan_perbaikan_data_tanpa_mewajibkan_unggah_ulang_dokumen(): void
    {
        $this->createVerifikasiLogSchema();
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-VERIFIKASI-NODOK-01',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon Uji',
            'nisn' => '1234567897',
            'diajukan_pada' => now(),
        ]);
        $idDokumen = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Kartu Keluarga',
            'nama_file_asli' => 'kk.pdf',
            'path_file' => 'ppdb/1/kk.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);
        DB::table('ref_alasan_pengembalian')->insert(['nama_alasan' => 'Lainnya']);

        $this->withoutMiddleware([EnsureRole::class, ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 100])
            ->post(route('tu.verifikasi.simpan'), [
                'id_pendaftaran' => $idPendaftaran,
                'hasil' => 'kembalikan',
                'id_alasan' => 'Lainnya',
                'catatan' => 'Perbaiki alamat tempat tinggal.',
            ])
            ->assertRedirect(route('tu.verifikasi', $idPendaftaran))
            ->assertSessionHas('success', 'Berkas dikembalikan untuk diperbaiki.');

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'perlu_perbaikan',
            'catatan_verifikasi' => 'Alasan: Lainnya Catatan: Perbaiki alamat tempat tinggal.',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumen,
            'status_verifikasi' => 'menunggu',
        ]);
    }

    public function test_api_menerima_pengembalian_perbaikan_data_tanpa_dokumen_khusus(): void
    {
        $idPendaftaran = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-API-NODOK-01',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Calon API',
            'nisn' => '2222222226',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);
        $idDokumen = DB::table('dokumen_siswa')->insertGetId([
            'id_pendaftaran' => $idPendaftaran,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta.pdf',
            'path_file' => 'ppdb/1/akta.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $this->actingAs(Pengguna::find(100), 'web')
            ->postJson('/api/ppdb/'.$idPendaftaran.'/verifikasi', [
                'status_pendaftaran' => 'perlu_perbaikan',
                'catatan_verifikasi' => 'Perbaiki data alamat.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idPendaftaran,
            'status_pendaftaran' => 'perlu_perbaikan',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_dokumen' => $idDokumen,
            'status_verifikasi' => 'menunggu',
        ]);
    }

    public function test_api_menolak_status_ppdb_yang_tidak_diizinkan(): void
    {
        DB::table('pendaftaran_ppdb')->insert([
            'nomor_pendaftaran' => 'PPDB-STATUS-0002',
            'status_pendaftaran' => 'diterima',
            'nama_calon_siswa' => 'Status Salah',
            'nisn' => '2222222223',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);
        $idMenunggu = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-STATUS-0003',
            'status_pendaftaran' => 'menunggu',
            'nama_calon_siswa' => 'Status Menunggu',
            'nisn' => '2222222224',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);

        $user = Pengguna::find(100);
        $this->actingAs($user, 'web');

        $this->postJson('/api/ppdb/'.DB::table('pendaftaran_ppdb')->where('nisn', '2222222223')->value('id_pendaftaran').'/verifikasi', [
            'status_pendaftaran' => 'menunggu',
        ])->assertStatus(422);

        $this->postJson('/api/ppdb/'.$idMenunggu.'/verifikasi', [
            'status_pendaftaran' => 'ditolak',
        ])->assertStatus(422);

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $idMenunggu,
            'status_pendaftaran' => 'menunggu',
        ]);
    }

    public function test_endpoint_unggah_ulang_lama_mengarahkan_ke_formulir_perbaikan_lengkap(): void
    {
        $id = DB::table('pendaftaran_ppdb')->insertGetId([
            'nomor_pendaftaran' => 'PPDB-REUPLOAD-0001',
            'token_draf' => 'token-reupload-legacy',
            'status_pendaftaran' => 'perlu_perbaikan',
            'nama_calon_siswa' => 'Reupload Test',
            'nisn' => '3333333333',
            'diajukan_pada' => now(),
            'langkah_terakhir' => 3,
        ]);

        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => $id,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'lama.pdf',
            'path_file' => 'ppdb/'.$id.'/lama.pdf',
            'status_verifikasi' => 'perlu_perbaikan',
            'catatan' => 'Konten buram',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-REUPLOAD-0001',
                'nisn' => '3333333333',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validPdf('akta_baru.pdf'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-reupload-legacy']))
            ->assertSessionHas('success', 'Silakan perbaiki data atau berkas melalui formulir pendaftaran.');

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => $id,
            'status_pendaftaran' => 'perlu_perbaikan',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_pendaftaran' => $id,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'lama.pdf',
            'status_verifikasi' => 'perlu_perbaikan',
        ]);
    }

    public function test_endpoint_api_public_dan_endpoint_verifikasi_memiliki_hak_akses_yang_sesuai(): void
    {
        $public = $this->postJson('/api/ppdb', array_merge($this->validPpdbPayload([
            'nisn' => '4444444444',
            'nik' => '3201010101010011',
            'no_kk' => '3201010101010012',
            'nomor_pendaftaran' => 'PPDB-API-0001',
        ]), [
            'nama_calon_siswa' => 'Rahma Putri',
            'persetujuan_ortu' => true,
            'pernyataan' => true,
        ]));

        $public->assertStatus(201);

        $this->postJson('/api/ppdb/999999/verifikasi', ['status_pendaftaran' => 'diterima'])
            ->assertUnauthorized();

        $user = Pengguna::find(100);
        $this->actingAs($user, 'sanctum');
        $id = DB::table('pendaftaran_ppdb')->where('nisn', '4444444444')->value('id_pendaftaran');

        $this->postJson('/api/ppdb/'.$id.'/verifikasi', ['status_pendaftaran' => 'diverifikasi'])
            ->assertOk();
    }

    public function test_api_kabupaten_kota_mengembalikan_wilayah_pada_provinsi_terpilih(): void
    {
        Schema::dropIfExists('kota');
        Schema::dropIfExists('provinsi');

        Schema::create('provinsi', function (Blueprint $table) {
            $table->id('id_provinsi');
            $table->string('nama_provinsi', 120);
            $table->string('kode_provinsi', 5)->nullable();
        });

        Schema::create('kota', function (Blueprint $table) {
            $table->id('id_kota');
            $table->unsignedBigInteger('id_provinsi');
            $table->string('nama_kota', 120);
        });

        DB::table('provinsi')->insert([
            ['id_provinsi' => 10, 'nama_provinsi' => 'Provinsi A', 'kode_provinsi' => '10'],
            ['id_provinsi' => 20, 'nama_provinsi' => 'Provinsi B', 'kode_provinsi' => '20'],
        ]);

        DB::table('kota')->insert([
            ['id_kota' => 101, 'id_provinsi' => 10, 'nama_kota' => 'Kabupaten Beta'],
            ['id_kota' => 102, 'id_provinsi' => 10, 'nama_kota' => 'Kabupaten Alpha'],
            ['id_kota' => 103, 'id_provinsi' => 10, 'nama_kota' => 'Kota Gamma'],
            ['id_kota' => 104, 'id_provinsi' => 20, 'nama_kota' => 'Kabupaten Delta'],
        ]);

        $this->get('/ppdb')
            ->assertOk()
            ->assertSee('Provinsi A')
            ->assertSee('Provinsi B')
            ->assertSee('Kabupaten/Kota');

        $this->getJson('/api/kabupaten/10')
            ->assertOk()
            ->assertExactJson([
                ['id' => 102, 'nama' => 'Kabupaten Alpha'],
                ['id' => 101, 'nama' => 'Kabupaten Beta'],
                ['id' => 103, 'nama' => 'Kota Gamma'],
            ]);

        $this->getJson('/api/kabupaten/20')
            ->assertOk()
            ->assertExactJson([
                ['id' => 104, 'nama' => 'Kabupaten Delta'],
            ]);

        $this->getJson('/api/kabupaten/30')
            ->assertOk()
            ->assertExactJson([]);
    }
}
