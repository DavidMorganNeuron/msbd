<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         if (! Schema::hasIndex('semester', ['id_semester'], 'unique')) {
             $hasDuplicateSemesterIds = DB::table('semester')
                 ->select('id_semester')
                 ->groupBy('id_semester')
                 ->havingRaw('COUNT(*) > 1')
                 ->exists();

             if ($hasDuplicateSemesterIds) {
                 throw new RuntimeException(
                     'Tabel semester memiliki id_semester duplikat. Perbaiki duplikasi sebelum migration absensi harian.'
                 );
             }

             Schema::table('semester', function (Blueprint $table) {
                 $table->unique('id_semester', 'semester_id_semester_unique');
             });
         }

        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kelas')->nullable();
            $table->unsignedBigInteger('id_semester')->nullable();

            $table->foreign('id_kelas')
                ->references('id_kelas')->on('kelas')
                ->restrictOnDelete();
            $table->foreign('id_semester')
                ->references('id_semester')->on('semester')
                ->restrictOnDelete();
            $table->index(['id_kelas', 'id_semester', 'tanggal']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE sesi_absensi AS sa
                SET id_kelas = j.id_kelas, id_semester = j.id_semester
                FROM jadwal_pelajaran AS j
                WHERE j.id_jadwal = sa.id_jadwal
            ');
        } else {
            $sesi = DB::table('sesi_absensi')
                ->whereNotNull('id_jadwal')
                ->get(['id_sesi', 'id_jadwal']);

            foreach ($sesi as $baris) {
                $jadwal = DB::table('jadwal_pelajaran')
                    ->where('id_jadwal', $baris->id_jadwal)
                    ->first(['id_kelas', 'id_semester']);

                if ($jadwal) {
                    DB::table('sesi_absensi')
                        ->where('id_sesi', $baris->id_sesi)
                        ->update([
                            'id_kelas' => $jadwal->id_kelas,
                            'id_semester' => $jadwal->id_semester,
                        ]);
                }
            }
        }

        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kelas')->nullable(false)->change();
            $table->unsignedBigInteger('id_semester')->nullable(false)->change();
            $table->unsignedBigInteger('id_jadwal')->nullable()->change();
        });

        DB::statement('
            CREATE UNIQUE INDEX sesi_absensi_harian_kelas_tanggal_unique
            ON sesi_absensi (id_kelas, id_semester, tanggal)
            WHERE id_jadwal IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sesi_absensi')->whereNull('id_jadwal')->exists()) {
            throw new RuntimeException('Absensi harian tanpa jadwal harus dipindahkan sebelum rollback.');
        }

        DB::statement('DROP INDEX IF EXISTS sesi_absensi_harian_kelas_tanggal_unique');
        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->dropIndex(['id_kelas', 'id_semester', 'tanggal']);
            $table->dropForeign(['id_kelas']);
            $table->dropForeign(['id_semester']);
            $table->dropColumn(['id_kelas', 'id_semester']);
            $table->unsignedBigInteger('id_jadwal')->nullable(false)->change();
        });

        if (Schema::hasIndex('semester', 'semester_id_semester_unique', 'unique')) {
            Schema::table('semester', function (Blueprint $table) {
                $table->dropUnique('semester_id_semester_unique');
            });
        }
    }
};
