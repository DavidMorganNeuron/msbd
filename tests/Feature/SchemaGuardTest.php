<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchemaGuardTest extends TestCase
{
    public function test_expected_current_schema_tables_are_present_in_postgres_schema_dump(): void
    {
        $schema = $this->readPostgresSchemaDump();

        foreach (['pengguna', 'siswa', 'pendaftaran_ppdb'] as $table) {
            $this->assertStringContainsString('CREATE TABLE public.'.$table, $schema);
        }
    }

    public function test_keanggotaan_kelas_allows_multiple_active_memberships_per_semester_and_tracks_statuses(): void
    {
        $schema = $this->readPostgresSchemaDump();

        $this->assertStringContainsString(
            'CREATE UNIQUE INDEX keanggotaan_kelas_one_active_per_semester',
            $schema
        );
        $this->assertStringNotContainsString(
            'CREATE UNIQUE INDEX keanggotaan_kelas_id_siswa_id_semester_unique',
            $schema
        );
        $this->assertStringNotContainsString(
            'ADD CONSTRAINT keanggotaan_kelas_id_siswa_id_semester_key',
            $schema
        );
        $this->assertStringContainsString('tidak_naik_kelas', $schema);
    }

    private function readPostgresSchemaDump(): string
    {
        $schema = file_get_contents(base_path('database/schema/pgsql-schema.sql'));

        $this->assertIsString($schema);

        return $schema;
    }
}
