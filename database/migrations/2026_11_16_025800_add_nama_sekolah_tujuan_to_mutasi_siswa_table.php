<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mutasi_siswa', 'nama_sekolah_tujuan')) {
            Schema::table('mutasi_siswa', function (Blueprint $table) {
                $table->string('nama_sekolah_tujuan', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mutasi_siswa', 'nama_sekolah_tujuan')) {
            Schema::table('mutasi_siswa', function (Blueprint $table) {
                $table->dropColumn('nama_sekolah_tujuan');
            });
        }
    }
};
