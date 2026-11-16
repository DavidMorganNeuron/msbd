<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AbsensiHarianMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge();
    }

    public function test_backfills_existing_sessions_and_adds_missing_semester_uniqueness(): void
    {
        $this->createLegacyAttendanceSchema([1]);

        $migration = require database_path('migrations/2026_11_16_002845_add_daily_class_fields_to_sesi_absensi.php');
        $migration->up();

        $this->assertDatabaseHas('sesi_absensi', [
            'id_sesi' => 1,
            'id_kelas' => 1,
            'id_semester' => 1,
        ]);
        $this->assertTrue(Schema::hasIndex('semester', ['id_semester'], 'unique'));
        $this->assertTrue(Schema::hasIndex('sesi_absensi', ['id_kelas', 'id_semester', 'tanggal']));
        $this->assertTrue(Schema::hasIndex(
            'sesi_absensi',
            'sesi_absensi_harian_kelas_tanggal_unique',
            'unique'
        ));
    }

    public function test_rejects_duplicate_semester_ids_before_changing_existing_sessions(): void
    {
        $this->createLegacyAttendanceSchema([1, 1]);

        $migration = require database_path('migrations/2026_11_16_002845_add_daily_class_fields_to_sesi_absensi.php');

        try {
            $migration->up();
            $this->fail('The migration should reject duplicate semester IDs.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('id_semester duplikat', $exception->getMessage());
        }

        $this->assertDatabaseCount('sesi_absensi', 1);
        $this->assertFalse(Schema::hasColumn('sesi_absensi', 'id_kelas'));
        $this->assertFalse(Schema::hasIndex('semester', ['id_semester'], 'unique'));
    }

    private function createLegacyAttendanceSchema(array $semesterIds): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->bigInteger('id_kelas')->primary();
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->bigInteger('id_semester');
        });

        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->bigInteger('id_jadwal')->primary();
            $table->bigInteger('id_kelas');
            $table->bigInteger('id_semester');
        });

        Schema::create('sesi_absensi', function (Blueprint $table) {
            $table->bigInteger('id_sesi')->primary();
            $table->bigInteger('id_jadwal');
            $table->bigInteger('id_guru_pencatat');
            $table->date('tanggal');
            $table->string('status_sesi');
        });

        DB::table('kelas')->insert(['id_kelas' => 1]);
        foreach ($semesterIds as $semesterId) {
            DB::table('semester')->insert(['id_semester' => $semesterId]);
        }

        DB::table('jadwal_pelajaran')->insert([
            'id_jadwal' => 10,
            'id_kelas' => 1,
            'id_semester' => $semesterIds[0],
        ]);

        DB::table('sesi_absensi')->insert([
            'id_sesi' => 1,
            'id_jadwal' => 10,
            'id_guru_pencatat' => 1,
            'tanggal' => '2026-11-16',
            'status_sesi' => 'dibuka',
        ]);
    }
}
