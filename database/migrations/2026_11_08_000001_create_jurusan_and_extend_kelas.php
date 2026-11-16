<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurusan', function (Blueprint $table) {
            $table->bigIncrements('id_jurusan');
            $table->string('kode_jurusan', 20)->unique();
            $table->string('nama_jurusan', 120);
            $table->boolean('is_aktif')->default(true);
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_jurusan')->nullable()->after('id_tingkat');
            $table->foreign('id_jurusan')
                ->references('id_jurusan')
                ->on('jurusan')
                ->nullOnDelete();
            $table->index('id_jurusan');
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['id_jurusan']);
            $table->dropColumn('id_jurusan');
        });

        Schema::dropIfExists('jurusan');
    }
};
