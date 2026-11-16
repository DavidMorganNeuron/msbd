<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_agama_id_agama ON agama (id_agama)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_kelas_id_kelas ON kelas (id_kelas)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_pekerjaan_id_pekerjaan ON pekerjaan (id_pekerjaan)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_pendaftaran_ppdb_id ON pendaftaran_ppdb (id_pendaftaran)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_ref_alasan_pengembalian_id ON ref_alasan_pengembalian (id_alasan)');

        Schema::table('pendaftaran_ppdb', function (Blueprint $table) {
            $table->string('token_draf', 64)->nullable()->unique();
            $table->smallInteger('langkah_terakhir')->default(0);
            $table->char('jenis_kelamin', 1)->nullable();
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

            $table->foreign('id_agama')->references('id_agama')->on('agama')->nullOnDelete();
            $table->foreign('id_kelas_diterima')->references('id_kelas')->on('kelas')->nullOnDelete();
            $table->index('status_pendaftaran');
        });

        DB::statement('ALTER TABLE pendaftaran_ppdb DROP CONSTRAINT IF EXISTS pendaftaran_ppdb_status_pendaftaran_check');
        DB::statement("ALTER TABLE pendaftaran_ppdb ADD CONSTRAINT pendaftaran_ppdb_status_pendaftaran_check CHECK (status_pendaftaran IN ('draf','menunggu','diverifikasi','diterima','ditolak','perlu_perbaikan'))");

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
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
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
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
        });

        Schema::create('ppdb_orang_tua', function (Blueprint $table) {
            $table->bigIncrements('id_orang_tua');
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
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
            $table->foreign('id_pekerjaan')->references('id_pekerjaan')->on('pekerjaan')->nullOnDelete();
            $table->unique(['id_pendaftaran', 'hubungan']);
        });

        Schema::create('ppdb_prestasi', function (Blueprint $table) {
            $table->bigIncrements('id_prestasi');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis', 10)->nullable();
            $table->string('tingkat', 10)->nullable();
            $table->string('nama_prestasi', 150)->nullable();
            $table->string('tahun', 4)->nullable();
            $table->string('penyelenggara', 150)->nullable();
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
        });

        Schema::create('ppdb_beasiswa', function (Blueprint $table) {
            $table->bigIncrements('id_beasiswa');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis', 10)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('tahun_mulai', 4)->nullable();
            $table->string('tahun_selesai', 4)->nullable();
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
        });

        Schema::create('ppdb_keluar', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pendaftaran')->primary();
            $table->string('keluar_karena', 10)->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->text('alasan')->nullable();
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('pendaftaran_ppdb')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_keluar');
        Schema::dropIfExists('ppdb_beasiswa');
        Schema::dropIfExists('ppdb_prestasi');
        Schema::dropIfExists('ppdb_orang_tua');
        Schema::dropIfExists('ppdb_periodik');
        Schema::dropIfExists('ppdb_alamat');

        DB::statement('ALTER TABLE pendaftaran_ppdb DROP CONSTRAINT IF EXISTS pendaftaran_ppdb_status_pendaftaran_check');
        DB::statement("ALTER TABLE pendaftaran_ppdb ADD CONSTRAINT pendaftaran_ppdb_status_pendaftaran_check CHECK (status_pendaftaran IN ('menunggu','diverifikasi','diterima','ditolak','perlu_perbaikan'))");

        Schema::table('pendaftaran_ppdb', function (Blueprint $table) {
            $table->dropForeign(['id_agama']);
            $table->dropForeign(['id_kelas_diterima']);
            $table->dropIndex(['status_pendaftaran']);
            $table->dropUnique(['token_draf']);
            $table->dropColumn([
                'token_draf', 'langkah_terakhir', 'jenis_kelamin', 'nik', 'no_kk', 'tempat_lahir',
                'email', 'id_agama', 'jenjang_tujuan', 'kelas_tujuan', 'tahun_pelajaran', 'nis',
                'nama_ortu_wali', 'persetujuan_ortu', 'pernyataan', 'id_kelas_diterima',
                'pendaftaran_untuk', 'no_seri_skhun', 'tahun_skhun', 'no_seri_ijazah',
                'tahun_ijazah', 'no_peserta_ujian', 'hobi', 'cita_cita', 'anak_ke',
                'status_keluarga', 'berkebutuhan_khusus', 'tempat_tinggal', 'moda_transportasi',
                'penerima_kps', 'no_kps', 'kewarganegaraan', 'negara', 'layak_pip', 'alasan_pip',
            ]);
        });

        DB::statement('DROP INDEX IF EXISTS uq_ref_alasan_pengembalian_id');
        DB::statement('DROP INDEX IF EXISTS uq_pendaftaran_ppdb_id');
        DB::statement('DROP INDEX IF EXISTS uq_pekerjaan_id_pekerjaan');
        DB::statement('DROP INDEX IF EXISTS uq_kelas_id_kelas');
        DB::statement('DROP INDEX IF EXISTS uq_agama_id_agama');
    }
};
