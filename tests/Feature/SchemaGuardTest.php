<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaGuardTest extends TestCase
{
    public function test_expected_current_schema_tables_are_present_in_test_database(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Pemeriksaan skema akademik khusus PostgreSQL.'
            );
        }

        $this->assertTrue(Schema::hasTable('pengguna'));
        $this->assertTrue(Schema::hasTable('siswa'));
        $this->assertTrue(Schema::hasTable('pendaftaran_ppdb'));
    }

    public function test_keanggotaan_kelas_allows_multiple_active_memberships_per_semester_and_tracks_statuses(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Pemeriksaan skema akademik khusus PostgreSQL.');
        }

        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'keanggotaan_kelas'");
        $indexNames = array_map(fn ($row) => $row->indexname, $indexes);

        $this->assertNotContains('keanggotaan_kelas_id_siswa_id_semester_unique', $indexNames, 'Unique index pada (id_siswa, id_semester) tidak diperbolehkan karena riwayat kelas harus menyimpan data historis.');
        $this->assertNotContains('keanggotaan_kelas_id_siswa_id_semester_key', $indexNames, 'Constraint unik pada (id_siswa, id_semester) tidak boleh ada.');

        $constraints = DB::select("SELECT pg_get_constraintdef(con.oid) AS check_sql
            FROM pg_constraint con
            JOIN pg_class cls ON cls.oid = con.conrelid
            WHERE cls.relname = 'keanggotaan_kelas' AND con.contype = 'c'");

        $sql = strtolower(implode(' ', array_map(fn ($row) => (string) ($row->check_sql ?? ''), $constraints)));
        $this->assertStringContainsString('tidak_naik_kelas', $sql, 'Status tidak naik kelas harus tersedia pada check constraint keanggotaan_kelas.');
    }
}
