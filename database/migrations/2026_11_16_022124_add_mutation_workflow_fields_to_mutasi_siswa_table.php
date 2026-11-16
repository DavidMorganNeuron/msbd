<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mutasi_siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('id_siswa')->nullable()->change();

            if (! Schema::hasColumn('mutasi_siswa', 'id_sekolah_asal')) {
                $table->unsignedBigInteger('id_sekolah_asal')->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'id_kelas_tujuan')) {
                $table->unsignedBigInteger('id_kelas_tujuan')->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'nisn_baru')) {
                $table->string('nisn_baru', 20)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'nama_siswa_baru')) {
                $table->string('nama_siswa_baru', 150)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'jenis_kelamin_baru')) {
                $table->char('jenis_kelamin_baru', 1)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'tempat_lahir_baru')) {
                $table->string('tempat_lahir_baru', 100)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'tanggal_lahir_baru')) {
                $table->date('tanggal_lahir_baru')->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'email_baru')) {
                $table->string('email_baru')->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'no_telepon_baru')) {
                $table->string('no_telepon_baru', 30)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'tanggal_efektif')) {
                $table->date('tanggal_efektif')->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'path_lampiran')) {
                $table->string('path_lampiran', 500)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'nama_lampiran')) {
                $table->string('nama_lampiran', 255)->nullable();
            }
            if (! Schema::hasColumn('mutasi_siswa', 'catatan_keputusan')) {
                $table->text('catatan_keputusan')->nullable();
            }
        });

        foreach ([
            'mengundurkan_diri' => 'Mengundurkan Diri',
            'dikeluarkan' => 'Dikeluarkan',
            'putus_sekolah' => 'Putus Sekolah',
            'meninggal_dunia' => 'Meninggal Dunia',
        ] as $kode => $nama) {
            if (! DB::table('status_siswa')->where('kode_status', $kode)->exists()) {
                DB::table('status_siswa')->insert([
                    'kode_status' => $kode,
                    'nama_status' => $nama,
                    'boleh_diubah_siswa' => false,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('mutasi_siswa')->whereNull('id_siswa')->exists()) {
            throw new RuntimeException('Cannot roll back mutation fields while incoming student requests are not linked to students.');
        }

        Schema::table('mutasi_siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('id_siswa')->nullable(false)->change();

            $columns = [
                'id_sekolah_asal',
                'id_kelas_tujuan',
                'nisn_baru',
                'nama_siswa_baru',
                'jenis_kelamin_baru',
                'tempat_lahir_baru',
                'tanggal_lahir_baru',
                'email_baru',
                'no_telepon_baru',
                'tanggal_efektif',
                'path_lampiran',
                'nama_lampiran',
                'catatan_keputusan',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('mutasi_siswa', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
