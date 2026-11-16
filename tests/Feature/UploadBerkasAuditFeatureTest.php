<?php

namespace Tests\Feature;

use App\Http\Controllers\AksiFormController;
use App\Services\PengaturanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadBerkasAuditFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('filesystems.default', 'public');

        DB::purge();
        $this->createUploadBerkasSchema();
        $this->seedUploadBerkasReference();
        Storage::fake('public');
        Storage::fake('private');
    }

    protected function createUploadBerkasSchema(): void
    {
        Schema::dropIfExists('dokumen_siswa');
        Schema::dropIfExists('ppdb_keluar');
        Schema::dropIfExists('ppdb_beasiswa');
        Schema::dropIfExists('ppdb_prestasi');
        Schema::dropIfExists('ppdb_orang_tua');
        Schema::dropIfExists('ppdb_periodik');
        Schema::dropIfExists('ppdb_alamat');
        Schema::dropIfExists('pendaftaran_ppdb');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('role_hak_akses');
        Schema::dropIfExists('hak_akses');
        Schema::dropIfExists('role');
        Schema::dropIfExists('pengguna');
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('tingkat');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('jenis_dokumen');
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
            $table->string('nama_tingkat', 40);
        });

        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->unsignedBigInteger('id_tahun_ajaran');
            $table->unsignedBigInteger('id_tingkat');
            $table->string('nama_kelas', 80);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::create('jenis_dokumen', function (Blueprint $table) {
            $table->id('id_jenis_dokumen');
            $table->string('nama_dokumen', 120)->unique();
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

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role')->nullable();
            $table->string('username', 100)->nullable()->unique();
            $table->string('nama_pengguna', 120)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->timestamp('terakhir_login')->nullable();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->unsignedBigInteger('id_pengguna');
            $table->string('judul', 200)->nullable();
            $table->text('pesan')->nullable();
            $table->boolean('dibaca')->default(false);
            $table->timestamp('dibuat_pada')->nullable();
        });

        Schema::create('agama', function (Blueprint $table) {
            $table->id('id_agama');
            $table->string('nama_agama', 80);
            $table->boolean('is_aktif')->default(true);
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

        Schema::create('alamat_siswa', function (Blueprint $table) {
            $table->id('id_alamat');
            $table->unsignedBigInteger('id_siswa');
            $table->text('alamat')->nullable();
        });

        Schema::create('orang_tua_wali', function (Blueprint $table) {
            $table->id('id_orang_tua_wali');
            $table->unsignedBigInteger('id_siswa');
            $table->string('hubungan', 30)->nullable();
            $table->string('nama_lengkap', 120)->nullable();
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
            $table->string('jenjang_tujuan', 10)->nullable();
            $table->string('kelas_tujuan', 80)->nullable();
            $table->string('tahun_pelajaran', 20)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('nama_ortu_wali', 120)->nullable();
            $table->boolean('persetujuan_ortu')->default(false);
            $table->boolean('pernyataan')->default(false);
            $table->unsignedBigInteger('id_kelas_diterima')->nullable();
            $table->string('pendaftaran_untuk', 30)->nullable();
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
            $table->unsignedBigInteger('id_siswa')->nullable();
            $table->unsignedBigInteger('id_pendaftaran')->nullable();
            $table->string('jenis_dokumen', 120);
            $table->string('nama_file_asli', 200)->nullable();
            $table->string('path_file', 500)->nullable();
            $table->string('status_verifikasi', 30)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('diunggah_pada')->nullable();
        });
    }

    protected function seedUploadBerkasReference(): void
    {
        DB::table('tahun_ajaran')->insert([
            ['id_tahun_ajaran' => 1, 'nama_tahun_ajaran' => '2026/2027', 'is_aktif' => true],
        ]);

        DB::table('tingkat')->insert([
            ['id_tingkat' => 1, 'nama_tingkat' => 'X'],
        ]);

        DB::table('kelas')->insert([
            ['id_kelas' => 1, 'id_tahun_ajaran' => 1, 'id_tingkat' => 1, 'nama_kelas' => 'X MIA 1', 'is_aktif' => true],
        ]);

        DB::table('role')->insert([
            ['id_role' => 1, 'nama_role' => 'tu', 'kode_role' => 'tu'],
            ['id_role' => 2, 'nama_role' => 'siswa', 'kode_role' => 'siswa'],
        ]);

        DB::table('hak_akses')->insert([
            ['id_hak_akses' => 1, 'kode_hak_akses' => 'ppdb.verify', 'nama_hak_akses' => 'Verifikasi PPDB'],
        ]);

        DB::table('role_hak_akses')->insert([
            ['id_role' => 1, 'id_hak_akses' => 1],
        ]);

        DB::table('pengguna')->insert([
            ['id_pengguna' => 10, 'id_role' => 1, 'nama_pengguna' => 'tu01', 'password_hash' => 'secret', 'is_aktif' => true],
            ['id_pengguna' => 20, 'id_role' => 2, 'nama_pengguna' => 'siswa01', 'password_hash' => 'secret', 'is_aktif' => true],
        ]);

        DB::table('agama')->insert([
            ['id_agama' => 1, 'nama_agama' => 'Kristen', 'is_aktif' => true],
            ['id_agama' => 7, 'nama_agama' => 'Katolik', 'is_aktif' => true],
            ['id_agama' => 8, 'nama_agama' => 'Islam', 'is_aktif' => true],
            ['id_agama' => 9, 'nama_agama' => 'Hindu', 'is_aktif' => true],
            ['id_agama' => 10, 'nama_agama' => 'Buddha', 'is_aktif' => true],
            ['id_agama' => 11, 'nama_agama' => 'Konghucu', 'is_aktif' => true],
        ]);

        DB::table('pekerjaan')->insert([
            ['id_pekerjaan' => 1, 'nama_pekerjaan' => 'Tidak Bekerja', 'kode_pekerjaan' => '01'],
            ['id_pekerjaan' => 2, 'nama_pekerjaan' => 'Nelayan', 'kode_pekerjaan' => '02'],
            ['id_pekerjaan' => 3, 'nama_pekerjaan' => 'Petani', 'kode_pekerjaan' => '03'],
            ['id_pekerjaan' => 4, 'nama_pekerjaan' => 'Peternak', 'kode_pekerjaan' => '04'],
            ['id_pekerjaan' => 5, 'nama_pekerjaan' => 'PNS/ASN', 'kode_pekerjaan' => '05'],
            ['id_pekerjaan' => 6, 'nama_pekerjaan' => 'Karyawan Swasta', 'kode_pekerjaan' => '06'],
            ['id_pekerjaan' => 7, 'nama_pekerjaan' => 'Pedagang', 'kode_pekerjaan' => '07'],
            ['id_pekerjaan' => 8, 'nama_pekerjaan' => 'Wiraswasta', 'kode_pekerjaan' => '08'],
            ['id_pekerjaan' => 9, 'nama_pekerjaan' => 'Buruh', 'kode_pekerjaan' => '11'],
            ['id_pekerjaan' => 10, 'nama_pekerjaan' => 'Pensiunan', 'kode_pekerjaan' => '12'],
            ['id_pekerjaan' => 11, 'nama_pekerjaan' => 'Lainnya', 'kode_pekerjaan' => '13'],
        ]);

        DB::table('siswa')->insert([
            ['id_siswa' => 1, 'id_pengguna' => 20, 'nama_lengkap' => 'Siswa Uji', 'nisn' => '1234567890', 'nik' => '3201010101010001'],
        ]);

        DB::table('pendaftaran_ppdb')->insert([
            ['id_pendaftaran' => 1, 'nomor_pendaftaran' => 'PPDB-TEST-0001', 'token_draf' => 'token-test-0001', 'status_pendaftaran' => 'perlu_perbaikan', 'nama_calon_siswa' => 'Calon Uji', 'nisn' => '2222222222', 'diajukan_pada' => now(), 'langkah_terakhir' => 3],
            ['id_pendaftaran' => 2, 'nomor_pendaftaran' => 'PPDB-TEST-0002', 'token_draf' => 'token-test-0002', 'status_pendaftaran' => 'menunggu', 'nama_calon_siswa' => 'Calon Uji 2', 'nisn' => '2222222223', 'diajukan_pada' => now(), 'langkah_terakhir' => 3],
        ]);

        DB::table('jenis_dokumen')->insert([
            ['id_jenis_dokumen' => 1, 'nama_dokumen' => 'Kartu Keluarga'],
            ['id_jenis_dokumen' => 2, 'nama_dokumen' => 'Akta Kelahiran'],
            ['id_jenis_dokumen' => 3, 'nama_dokumen' => 'Ijazah / SKL'],
            ['id_jenis_dokumen' => 4, 'nama_dokumen' => 'Pas Foto 3x4'],
        ]);
    }

    protected function validUploadFixture(string $filename, string $ext): UploadedFile
    {
        $content = match ($ext) {
            'pdf' => "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
            'jpg', 'jpeg' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00",
            'png' => "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xDE\x00\x00\x00\x0CIDAT\x88\xc5\x9d\x7f\x00\x00\x00\x02\x00\x01\xE5\x27\xD8\xD8\x00\x00\x00\x00IEND\xAEB`\x82",
            default => 'invalid',
        };

        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        return UploadedFile::fake()->createWithContent($filename, $content, $mime);
    }

    protected function validPpdbPayload(array $overrides = []): array
    {
        $payload = [
            'nomor_pendaftaran' => 'PPDB-UPLOAD-0001',
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
            'nisn' => '1234567899',
            'nik' => '3201010101010099',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2013-05-11',
            'agama' => '01',
            'no_kk' => '3201010101010098',
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
            'ortu_nik_0' => '3201010101010097',
            'ortu_tanggal_lahir_0' => '1980-01-15',
            'ortu_tempat_lahir_0' => 'Bandung',
            'ortu_pendidikan_0' => '04',
            'ortu_kerja_0' => '05',
            'ortu_penghasilan_0' => '4',
            'ortu_kebutuhan_0' => '01',
            'ortu_hp_0' => '081234567891',
            'ortu_alamat_0' => 'Jl. Merdeka No. 1',
            'ortu_nama_1' => 'Siti Aisyah',
            'ortu_nik_1' => '3201010101010096',
            'ortu_tanggal_lahir_1' => '1983-05-30',
            'ortu_tempat_lahir_1' => 'Bandung',
            'ortu_pendidikan_1' => '04',
            'ortu_kerja_1' => '06',
            'ortu_penghasilan_1' => '4',
            'ortu_kebutuhan_1' => '01',
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
            'berkas_0' => $this->validUploadFixture('kk.pdf', 'pdf'),
            'berkas_1' => $this->validUploadFixture('akta.pdf', 'pdf'),
            'berkas_2' => $this->validUploadFixture('ijazah.pdf', 'pdf'),
            'berkas_3' => $this->validUploadFixture('pasfoto.jpg', 'jpg'),
        ];

        return array_replace_recursive($payload, $overrides);
    }

    public function test_pdf_jpg_jpeg_dan_png_dapat_diterima_oleh_validasi_berkas_ppdb(): void
    {
        foreach (['pdf', 'jpg', 'jpeg', 'png'] as $ext) {
            $filename = 'dokumen-'.$ext.'.'.$ext;
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            };

            $payload = $this->validPpdbPayload([
                'nisn' => '100000000'.substr($ext, 0, 1),
                'nik' => '32010101010100'.str_pad((string) strlen($ext), 2, '0', STR_PAD_LEFT),
                'nomor_pendaftaran' => 'PPDB-UPLOAD-'.strtoupper(substr($ext, 0, 2)).'-0001',
                'berkas_0' => $this->validUploadFixture($filename, $ext),
                'berkas_1' => $this->validUploadFixture('akta.'.$ext, $ext),
                'berkas_2' => $this->validUploadFixture('ijazah.'.$ext, $ext),
                'berkas_3' => $this->validUploadFixture('pasfoto.'.$ext, $ext),
            ]);

            $this->withoutMiddleware([ValidateCsrfToken::class])
                ->from('/ppdb')
                ->post('/ppdb', $payload)
                ->assertRedirect();
        }
    }

    public function test_file_tidak_diizinkan_ditolak_oleh_validasi_upload_ppdb(): void
    {
        $payload = $this->validPpdbPayload([
            'berkas_0' => UploadedFile::fake()->create('virus.exe', 256, 'application/x-msdownload'),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['berkas_0']);
    }

    public function test_file_yang_melebihi_batas_ukuran_ditolak(): void
    {
        $payload = $this->validPpdbPayload([
            'berkas_0' => UploadedFile::fake()->create('besar.pdf', 3072, 'application/pdf'),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['berkas_0']);
    }

    public function test_file_palsu_dengan_isi_teks_biasa_ditolak_untuk_pdf_jpg_dan_png(): void
    {
        $kasus = [
            ['dokumen.pdf', 'ini adalah teks biasa bukan pdf', '2000000101', '3201010101010101'],
            ['foto.jpg', 'ini adalah teks biasa bukan jpg', '2000000102', '3201010101010102'],
            ['foto.png', 'ini adalah teks biasa bukan png', '2000000103', '3201010101010103'],
        ];

        foreach ($kasus as [$namaFile, $konten, $nisn, $nik]) {
            $payload = $this->validPpdbPayload([
                'nisn' => $nisn,
                'nik' => $nik,
                'nomor_pendaftaran' => 'PPDB-FAKE-'.strtoupper(substr($namaFile, 0, 3)).'-0001',
                'berkas_0' => UploadedFile::fake()->createWithContent($namaFile, $konten),
                'berkas_1' => UploadedFile::fake()->create('akta.pdf', 1024, 'application/pdf'),
                'berkas_2' => UploadedFile::fake()->create('ijazah.pdf', 1024, 'application/pdf'),
                'berkas_3' => UploadedFile::fake()->create('pasfoto.jpg', 1024, 'image/jpeg'),
            ]);

            $this->withoutMiddleware([ValidateCsrfToken::class])
                ->from('/ppdb')
                ->post('/ppdb', $payload)
                ->assertSessionHasErrors(['berkas_0']);
        }
    }

    public function test_file_dengan_ekstensi_ganda_ditolak_oleh_validasi_ppdb(): void
    {
        $payload = $this->validPpdbPayload([
            'nisn' => '2000000104',
            'nik' => '3201010101010104',
            'nomor_pendaftaran' => 'PPDB-DOUBLE-0001',
            'berkas_0' => UploadedFile::fake()->createWithContent('dokumen.pdf.exe', "MZ\x00\x00\x00\x00\x00\x00\x00\x00exec-code-plain-text"),
            'berkas_1' => UploadedFile::fake()->create('akta.pdf', 1024, 'application/pdf'),
            'berkas_2' => UploadedFile::fake()->create('ijazah.pdf', 1024, 'application/pdf'),
            'berkas_3' => UploadedFile::fake()->create('pasfoto.jpg', 1024, 'image/jpeg'),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertSessionHasErrors(['berkas_0']);
    }

    public function test_file_pdf_jpg_dan_png_valid_dengan_isi_terkait_diterima(): void
    {
        $kasus = [
            ['dokumen-valid.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF", 'application/pdf'],
            ['foto-valid.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00", 'image/jpeg'],
            ['foto-valid.png', "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xDE\x00\x00\x00\x0CIDAT\x88\xc5\x9d\x7f\x00\x00\x00\x02\x00\x01\xE5\x27\xD8\xD8\x00\x00\x00\x00IEND\xAEB`\x82", 'image/png'],
        ];

        foreach ($kasus as [$namaFile, $konten, $mime]) {
            $payload = $this->validPpdbPayload([
                'nisn' => '2000000110'.(string) array_search($namaFile, array_column($kasus, 0), true),
                'nik' => '32010101010101'.str_pad((string) (array_search($namaFile, array_column($kasus, 0), true) + 1), 2, '0', STR_PAD_LEFT),
                'nomor_pendaftaran' => 'PPDB-VALID-'.strtoupper(substr($namaFile, 0, 3)).'-0001',
                'berkas_0' => UploadedFile::fake()->createWithContent($namaFile, $konten, $mime),
                'berkas_1' => UploadedFile::fake()->create('akta.pdf', 1024, 'application/pdf'),
                'berkas_2' => UploadedFile::fake()->create('ijazah.pdf', 1024, 'application/pdf'),
                'berkas_3' => UploadedFile::fake()->create('pasfoto.jpg', 1024, 'image/jpeg'),
            ]);

            $this->withoutMiddleware([ValidateCsrfToken::class])
                ->from('/ppdb')
                ->post('/ppdb', $payload)
                ->assertRedirect();
        }
    }

    public function test_file_terupload_disimpan_ke_storage_dan_metadata_dibuat_di_tabel_dokumen(): void
    {
        $payload = $this->validPpdbPayload();

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb')
            ->post('/ppdb', $payload)
            ->assertRedirect();

        $id = DB::table('pendaftaran_ppdb')->where('nisn', '1234567899')->value('id_pendaftaran');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => $id, 'jenis_dokumen' => 'Kartu Keluarga']);
        $path = DB::table('dokumen_siswa')->where('id_pendaftaran', $id)->value('path_file');
        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('private')->exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_bukti_pengajuan_baru_disimpan_di_private_storage_dan_bukti_path_tercatat(): void
    {
        Schema::dropIfExists('detail_pengajuan');
        Schema::dropIfExists('pengajuan_perubahan');
        Schema::dropIfExists('ref_field_data');
        Schema::dropIfExists('periode_update');

        Schema::create('periode_update', function (Blueprint $table) {
            $table->id('id_periode_update');
            $table->string('status', 20)->default('aktif');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
        });

        Schema::create('ref_field_data', function (Blueprint $table) {
            $table->id('id_field');
            $table->string('nama_tabel', 60);
            $table->string('nama_kolom', 80);
            $table->string('label_field', 120);
            $table->boolean('boleh_diubah_siswa')->default(false);
            $table->boolean('perlu_bukti')->default(false);
        });

        Schema::create('pengajuan_perubahan', function (Blueprint $table) {
            $table->id('id_pengajuan');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_periode_update');
            $table->timestamp('tgl_pengajuan')->nullable();
            $table->string('status_pengajuan', 20)->default('menunggu');
            $table->text('alasan')->nullable();
        });

        Schema::create('detail_pengajuan', function (Blueprint $table) {
            $table->id('id_detail_pengajuan');
            $table->unsignedBigInteger('id_pengajuan');
            $table->string('nama_field', 80);
            $table->longText('nilai_lama')->nullable();
            $table->longText('nilai_baru')->nullable();
            $table->string('bukti_path', 500)->nullable();
        });

        DB::table('periode_update')->insert([
            ['id_periode_update' => 1, 'status' => 'aktif', 'tanggal_mulai' => now()->subDay()->toDateString(), 'tanggal_selesai' => now()->addDay()->toDateString()],
        ]);

        DB::table('ref_field_data')->insert([
            ['id_field' => 1, 'nama_tabel' => 'siswa', 'nama_kolom' => 'nama_lengkap', 'label_field' => 'Nama lengkap', 'boleh_diubah_siswa' => true, 'perlu_bukti' => true],
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->from('/siswa/pengajuan-perubahan')
            ->post('/siswa/pengajuan-perubahan', [
                'id_field' => '1',
                'nilai_baru' => 'Budi Santoso',
                'bukti' => $this->validUploadFixture('bukti-perubahan.pdf', 'pdf'),
            ])
            ->assertRedirect('/siswa/pengajuan-perubahan');

        $row = DB::table('detail_pengajuan')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->bukti_path);
        $this->assertStringStartsWith('bukti-pengajuan/', $row->bukti_path);
        $this->assertTrue(Storage::disk('private')->exists($row->bukti_path));
        $this->assertFalse(Storage::disk('public')->exists($row->bukti_path));
    }

    public function test_pengajuan_tanpa_bukti_terus_diterima_ketika_field_tidak_memerlukan_bukti(): void
    {
        Schema::dropIfExists('detail_pengajuan');
        Schema::dropIfExists('pengajuan_perubahan');
        Schema::dropIfExists('ref_field_data');
        Schema::dropIfExists('periode_update');

        Schema::create('periode_update', function (Blueprint $table) {
            $table->id('id_periode_update');
            $table->string('status', 20)->default('aktif');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
        });

        Schema::create('ref_field_data', function (Blueprint $table) {
            $table->id('id_field');
            $table->string('nama_tabel', 60);
            $table->string('nama_kolom', 80);
            $table->string('label_field', 120);
            $table->boolean('boleh_diubah_siswa')->default(false);
            $table->boolean('perlu_bukti')->default(false);
        });

        Schema::create('pengajuan_perubahan', function (Blueprint $table) {
            $table->id('id_pengajuan');
            $table->unsignedBigInteger('id_siswa');
            $table->unsignedBigInteger('id_periode_update');
            $table->timestamp('tgl_pengajuan')->nullable();
            $table->string('status_pengajuan', 20)->default('menunggu');
            $table->text('alasan')->nullable();
        });

        Schema::create('detail_pengajuan', function (Blueprint $table) {
            $table->id('id_detail_pengajuan');
            $table->unsignedBigInteger('id_pengajuan');
            $table->string('nama_field', 80);
            $table->longText('nilai_lama')->nullable();
            $table->longText('nilai_baru')->nullable();
            $table->string('bukti_path', 500)->nullable();
        });

        DB::table('periode_update')->insert([
            ['id_periode_update' => 1, 'status' => 'aktif', 'tanggal_mulai' => now()->subDay()->toDateString(), 'tanggal_selesai' => now()->addDay()->toDateString()],
        ]);

        DB::table('ref_field_data')->insert([
            ['id_field' => 2, 'nama_tabel' => 'siswa', 'nama_kolom' => 'email', 'label_field' => 'Email', 'boleh_diubah_siswa' => true, 'perlu_bukti' => false],
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->from('/siswa/pengajuan-perubahan')
            ->post('/siswa/pengajuan-perubahan', [
                'id_field' => '2',
                'nilai_baru' => 'budi@example.com',
            ])
            ->assertRedirect('/siswa/pengajuan-perubahan');

        $row = DB::table('detail_pengajuan')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->bukti_path);
    }

    public function test_nama_file_baru_tidak_menimpa_file_lain_saat_berkas_berbeda(): void
    {
        $payloadA = $this->validPpdbPayload([
            'nisn' => '1111111111',
            'nik' => '3201010101010101',
            'nomor_pendaftaran' => 'PPDB-UNIQ-0001',
            'berkas_0' => $this->validUploadFixture('kk-pertama.pdf', 'pdf'),
        ]);

        $payloadB = $this->validPpdbPayload([
            'nisn' => '1111111112',
            'nik' => '3201010101010102',
            'nomor_pendaftaran' => 'PPDB-UNIQ-0002',
            'berkas_0' => $this->validUploadFixture('kk-kedua.pdf', 'pdf'),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])->post('/ppdb', $payloadA)->assertRedirect();
        $this->withoutMiddleware([ValidateCsrfToken::class])->post('/ppdb', $payloadB)->assertRedirect();

        $paths = DB::table('dokumen_siswa')->pluck('path_file')->all();
        $this->assertSame(8, count($paths));
        $this->assertSame(count($paths), count(array_unique($paths)));
    }

    public function test_dokumen_siswa_tu_dapat_diupload_melalui_controller(): void
    {
        $request = new Request;
        $request->merge([
            'id_siswa' => '1',
            'id_jenis_dokumen' => 'Akta Kelahiran',
            'nomor_dokumen' => 'AK-2026-001',
            'ada_fisik' => '1',
        ]);
        $request->files->set('berkas', $this->validUploadFixture('akta_siswa.pdf', 'pdf'));

        $response = (new AksiFormController)->dokumenSimpan($request, 1, app(PengaturanService::class));

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'status_verifikasi' => 'menunggu',
        ]);
    }

    public function test_dokumen_nonaktif_mengubah_status_verifikasi_dan_menyimpan_catatan(): void
    {
        DB::table('dokumen_siswa')->insert([
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'lama.pdf',
            'path_file' => 'dokumen-siswa/lama.pdf',
            'status_verifikasi' => 'menunggu',
            'catatan' => 'Perlu ditinjau',
            'diunggah_pada' => now(),
        ]);

        $response = (new AksiFormController)->dokumenNonaktif(1, 1);

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('dokumen_siswa', ['id_dokumen' => 1, 'status_verifikasi' => 'ditolak']);
        $this->assertNotNull(DB::table('dokumen_siswa')->where('id_dokumen', 1)->value('catatan'));
    }

    public function test_reupload_status_perbaikan_mengarahkan_pendaftar_ke_formulir(): void
    {
        $id = 1;
        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => $id,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'lama.pdf',
            'path_file' => 'ppdb/'.$id.'/lama.pdf',
            'status_verifikasi' => 'perlu_perbaikan',
            'catatan' => 'Konten buram',
            'diunggah_pada' => now(),
        ]);

        $request = new Request;
        $request->merge([
            'kode_pendaftaran' => 'PPDB-TEST-0001',
            'nisn' => '2222222222',
            'id_jenis_dokumen' => 'Akta Kelahiran',
        ]);
        $request->files->set('berkas_ulang', $this->validUploadFixture('akta_baru.pdf', 'pdf'));

        $response = (new AksiFormController)->berkasUlang($request, app(PengaturanService::class));

        $this->assertTrue($response->isRedirect());
        $this->assertSame(route('ppdb', ['draf' => 'token-test-0001']), $response->getTargetUrl());
        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => $id, 'jenis_dokumen' => 'Akta Kelahiran', 'status_verifikasi' => 'perlu_perbaikan']);
        $this->assertDatabaseHas('pendaftaran_ppdb', ['id_pendaftaran' => $id, 'status_pendaftaran' => 'perlu_perbaikan']);
    }

    public function test_upload_dokumen_tu_hanya_bisa_dilakukan_oleh_role_tu_via_route_asli(): void
    {
        $payload = [
            'id_siswa' => '1',
            'id_jenis_dokumen' => 'Akta Kelahiran',
            'nomor_dokumen' => 'AK-2026-001',
            'ada_fisik' => '1',
        ];

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->from('/tu/siswa/1/dokumen')
            ->post('/tu/siswa/1/dokumen', $payload + ['berkas' => $this->validUploadFixture('akta_siswa.pdf', 'pdf')])
            ->assertNotFound();

        $this->assertDatabaseMissing('dokumen_siswa', ['id_siswa' => 1, 'jenis_dokumen' => 'Akta Kelahiran']);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->from('/tu/siswa/1/dokumen')
            ->post('/tu/siswa/1/dokumen', $payload + ['berkas' => $this->validUploadFixture('akta_siswa_baru.pdf', 'pdf')])
            ->assertRedirect('/tu/siswa/1/dokumen');

        $this->assertDatabaseHas('dokumen_siswa', ['id_siswa' => 1, 'jenis_dokumen' => 'Akta Kelahiran', 'status_verifikasi' => 'menunggu']);
    }

    public function test_penonaktifan_dokumen_hanya_bisa_dilakukan_oleh_role_tu_via_route_asli(): void
    {
        DB::table('dokumen_siswa')->insert([
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'lama.pdf',
            'path_file' => 'dokumen-siswa/lama.pdf',
            'status_verifikasi' => 'menunggu',
            'catatan' => 'Perlu ditinjau',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->patch('/tu/siswa/1/dokumen/1')
            ->assertNotFound();

        $this->assertDatabaseHas('dokumen_siswa', ['id_dokumen' => 1, 'status_verifikasi' => 'menunggu']);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->patch('/tu/siswa/1/dokumen/1')
            ->assertRedirect('/tu/siswa/1/dokumen');

        $this->assertDatabaseHas('dokumen_siswa', ['id_dokumen' => 1, 'status_verifikasi' => 'ditolak']);
        $this->assertNotNull(DB::table('dokumen_siswa')->where('id_dokumen', 1)->value('catatan'));
    }

    public function test_dokumen_siswa_lain_dan_route_tu_ditolak_oleh_middleware_role(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->get('/tu/siswa/1/dokumen')
            ->assertNotFound();

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/siswa/1/dokumen')
            ->assertOk();
    }

    public function test_file_dengan_ekstensi_dan_mime_tidak_cocok_ditolak_pada_upload_tu(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->from('/tu/siswa/1/dokumen')
            ->post('/tu/siswa/1/dokumen', [
                'id_siswa' => '1',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'nomor_dokumen' => 'AK-2026-002',
                'ada_fisik' => '1',
                'berkas' => UploadedFile::fake()->create('dokumen.txt', 512, 'text/plain'),
            ])
            ->assertSessionHasErrors(['berkas']);

        $this->assertDatabaseMissing('dokumen_siswa', ['id_siswa' => 1, 'jenis_dokumen' => 'Akta Kelahiran', 'nomor_dokumen' => 'AK-2026-002']);
    }

    public function test_reupload_route_redirects_to_the_matching_registration_form(): void
    {
        DB::table('pendaftaran_ppdb')->insert([
            ['id_pendaftaran' => 3, 'nomor_pendaftaran' => 'PPDB-SELF-0001', 'token_draf' => 'token-self-0001', 'status_pendaftaran' => 'perlu_perbaikan', 'nama_calon_siswa' => 'Calon A', 'nisn' => '2222222224', 'diajukan_pada' => now(), 'langkah_terakhir' => 3],
            ['id_pendaftaran' => 4, 'nomor_pendaftaran' => 'PPDB-SELF-0002', 'token_draf' => 'token-self-0002', 'status_pendaftaran' => 'perlu_perbaikan', 'nama_calon_siswa' => 'Calon B', 'nisn' => '2222222225', 'diajukan_pada' => now(), 'langkah_terakhir' => 3],
        ]);

        DB::table('dokumen_siswa')->insert([
            ['id_pendaftaran' => 3, 'jenis_dokumen' => 'Akta Kelahiran', 'nama_file_asli' => 'lama-3.pdf', 'path_file' => 'ppdb/3/lama-3.pdf', 'status_verifikasi' => 'perlu_perbaikan', 'catatan' => 'Harus diperbaiki', 'diunggah_pada' => now()],
            ['id_pendaftaran' => 4, 'jenis_dokumen' => 'Akta Kelahiran', 'nama_file_asli' => 'lama-4.pdf', 'path_file' => 'ppdb/4/lama-4.pdf', 'status_verifikasi' => 'perlu_perbaikan', 'catatan' => 'Harus diperbaiki', 'diunggah_pada' => now()],
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-SELF-0001',
                'nisn' => '2222222224',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('akta_baru.pdf', 'pdf'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-self-0001']));

        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => 3, 'jenis_dokumen' => 'Akta Kelahiran', 'status_verifikasi' => 'perlu_perbaikan']);
        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => 4, 'jenis_dokumen' => 'Akta Kelahiran', 'status_verifikasi' => 'perlu_perbaikan']);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-SELF-9999',
                'nisn' => '2222222224',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('akta_lain.pdf', 'pdf'),
            ])
            ->assertSessionHasErrors(['kode_pendaftaran']);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-SELF-0001',
                'nisn' => '2222222225',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('akta_tidak_cocok.pdf', 'pdf'),
            ])
            ->assertSessionHasErrors(['kode_pendaftaran']);

        $this->assertDatabaseMissing('dokumen_siswa', ['id_pendaftaran' => 3, 'nama_file_asli' => 'akta_tidak_cocok.pdf']);
    }

    public function test_upload_ulang_route_leaves_file_validation_to_the_formulir(): void
    {
        $id = 1;
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
                'kode_pendaftaran' => 'PPDB-TEST-0001',
                'nisn' => '2222222222',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => UploadedFile::fake()->create('dokumen.txt', 256, 'text/plain'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-test-0001']));

        $this->assertDatabaseHas('dokumen_siswa', ['id_pendaftaran' => $id, 'jenis_dokumen' => 'Akta Kelahiran', 'path_file' => 'ppdb/'.$id.'/lama.pdf', 'status_verifikasi' => 'perlu_perbaikan']);
        $this->assertSame(1, DB::table('dokumen_siswa')->where('id_pendaftaran', $id)->count());
    }

    public function test_dokumen_siswa_disimpan_di_private_storage(): void
    {
        $request = new Request;
        $request->merge([
            'id_siswa' => '1',
            'id_jenis_dokumen' => 'Akta Kelahiran',
            'nomor_dokumen' => 'AK-2026-010',
            'ada_fisik' => '1',
        ]);
        $request->files->set('berkas', $this->validUploadFixture('akta_private.pdf', 'pdf'));

        $response = (new AksiFormController)->dokumenSimpan($request, 1, app(PengaturanService::class));

        $this->assertTrue($response->isRedirect());
        $row = DB::table('dokumen_siswa')->where('id_siswa', 1)->latest('id_dokumen')->first();
        $this->assertNotNull($row);
        $this->assertTrue(Storage::disk('private')->exists($row->path_file));
        $this->assertFalse(Storage::disk('public')->exists($row->path_file));
    }

    public function test_upload_ulang_status_page_does_not_store_the_file_before_form_submission(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-TEST-0001',
                'nisn' => '2222222222',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('akta_baru.pdf', 'pdf'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-test-0001']));

        $this->assertDatabaseCount('dokumen_siswa', 0);
    }

    public function test_reupload_status_ditolak_mengarahkan_pendaftar_ke_formulir(): void
    {
        DB::table('pendaftaran_ppdb')
            ->where('id_pendaftaran', 1)
            ->update(['status_pendaftaran' => 'ditolak']);
        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta_lama.pdf',
            'path_file' => 'ppdb/1/akta_lama.pdf',
            'status_verifikasi' => 'ditolak',
            'catatan' => 'Berkas tidak terbaca.',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-TEST-0001',
                'nisn' => '2222222222',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('akta_diperbaiki.pdf', 'pdf'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-test-0001']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pendaftaran_ppdb', [
            'id_pendaftaran' => 1,
            'status_pendaftaran' => 'ditolak',
        ]);
        $this->assertDatabaseHas('dokumen_siswa', [
            'id_pendaftaran' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'akta_lama.pdf',
            'status_verifikasi' => 'ditolak',
        ]);
    }

    public function test_route_preview_dokumen_tu_hanya_untuk_tu_dan_mengambil_id_dokumen(): void
    {
        $path = 'dokumen-siswa/preview-safe.pdf';
        Storage::disk('private')->put($path, 'PDF-CONTENT');

        $dokumenId = DB::table('dokumen_siswa')->insertGetId([
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'preview-safe.pdf',
            'path_file' => $path,
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 20, 'role' => 'siswa'])
            ->get('/tu/dokumen/'.$dokumenId)
            ->assertNotFound();

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/dokumen/'.$dokumenId)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/dokumen/999999')
            ->assertNotFound();
    }

    public function test_akses_file_lama_di_public_storage_masih_bisa_dibuka_via_route_tu(): void
    {
        $path = 'dokumen-siswa/legacy-berkas.pdf';
        Storage::disk('public')->put($path, 'LEGACY-PDF');

        $dokumenId = DB::table('dokumen_siswa')->insertGetId([
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'legacy-berkas.pdf',
            'path_file' => $path,
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/dokumen/'.$dokumenId)
            ->assertOk();
    }

    public function test_file_lama_remains_unchanged_until_formulir_submission(): void
    {
        $legacyPath = 'ppdb/1/legacy-ppdb.pdf';
        Storage::disk('public')->put($legacyPath, 'LEGACY-PPDB');

        DB::table('dokumen_siswa')->insert([
            'id_pendaftaran' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'legacy-ppdb.pdf',
            'path_file' => $legacyPath,
            'status_verifikasi' => 'perlu_perbaikan',
            'catatan' => 'Harus diperbaiki',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->from('/ppdb/status')
            ->post('/ppdb/status/berkas', [
                'kode_pendaftaran' => 'PPDB-TEST-0001',
                'nisn' => '2222222222',
                'id_jenis_dokumen' => 'Akta Kelahiran',
                'berkas_ulang' => $this->validUploadFixture('replacement.pdf', 'pdf'),
            ])
            ->assertRedirect(route('ppdb', ['draf' => 'token-test-0001']));

        $this->assertTrue(Storage::disk('public')->exists($legacyPath));
        $row = DB::table('dokumen_siswa')->where('id_pendaftaran', 1)->where('jenis_dokumen', 'Akta Kelahiran')->first();
        $this->assertNotNull($row);
        $this->assertSame($legacyPath, $row->path_file);
    }

    public function test_file_tidak_tersedia_tidak_menyebabkan_error_aplikasi(): void
    {
        $dokumenId = DB::table('dokumen_siswa')->insertGetId([
            'id_siswa' => 1,
            'jenis_dokumen' => 'Akta Kelahiran',
            'nama_file_asli' => 'hilang.pdf',
            'path_file' => 'dokumen-siswa/hilang.pdf',
            'status_verifikasi' => 'menunggu',
            'diunggah_pada' => now(),
        ]);

        $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/dokumen/'.$dokumenId)
            ->assertNotFound();
    }

    public function test_detail_siswa_menampilkan_ukuran_dokumen_private_legacy_public_dan_tidak_tersedia(): void
    {
        $privatePath = 'dokumen-siswa/private-berkas.pdf';
        Storage::disk('private')->put($privatePath, str_repeat('A', 1024));

        $publicPath = 'dokumen-siswa/legacy-berkas.pdf';
        Storage::disk('public')->put($publicPath, str_repeat('B', 1024));

        DB::table('dokumen_siswa')->insert([
            ['id_siswa' => 1, 'jenis_dokumen' => 'Akta Kelahiran', 'nama_file_asli' => 'private-berkas.pdf', 'path_file' => $privatePath, 'status_verifikasi' => 'menunggu', 'diunggah_pada' => now()],
            ['id_siswa' => 1, 'jenis_dokumen' => 'Kartu Keluarga', 'nama_file_asli' => 'legacy-berkas.pdf', 'path_file' => $publicPath, 'status_verifikasi' => 'menunggu', 'diunggah_pada' => now()],
            ['id_siswa' => 1, 'jenis_dokumen' => 'Ijazah / SKL', 'nama_file_asli' => 'hilang.pdf', 'path_file' => 'dokumen-siswa/hilang.pdf', 'status_verifikasi' => 'menunggu', 'diunggah_pada' => now()],
        ]);

        $response = $this->withoutMiddleware([ValidateCsrfToken::class])
            ->withSession(['id_pengguna' => 10, 'role' => 'tu'])
            ->get('/tu/siswa/1');

        $response->assertOk();

        $content = $response->getContent();

        $this->assertSame(2, substr_count($content, '1 KB'));
        $this->assertStringContainsString('Akta Kelahiran', $content);
        $this->assertStringContainsString('Kartu Keluarga', $content);
    }

    public function test_akses_file_public_storage_tidak_dapat_dibuktikan_aman_tanpa_route_download_yang_ada(): void
    {
        $this->markTestIncomplete('Aplikasi tidak menyediakan route download/preview khusus untuk berkas siswa, dan URL yang dibuat dari Storage::disk("public")->url($path) hanya merupakan URL storage standar. Dalam lingkungan SQLite in-memory dengan Storage::fake(), tidak ada symlink web server nyata yang dapat dibuktikan mengizinkan akses tanpa autentikasi secara aman.');
    }

    public function test_rollback_penyimpanan_file_jika_storage_gagal_tidak_dapat_diuji_safely(): void
    {
        $this->markTestIncomplete('Simulasi kegagalan storage pada fake disk bisa dipaksa dengan mock, tetapi itu tidak merepresentasikan alur nyata yang aman atau deterministik. Kode aplikasi juga tidak memiliki transaction atomik file+database, sehingga uji rollback yang akurat memerlukan perubahan kode atau lingkungan hampir-produksi.');
    }
}
