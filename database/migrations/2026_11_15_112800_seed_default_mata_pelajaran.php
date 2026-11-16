<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mata_pelajaran')->insertOrIgnore([
            ['kode_mapel' => 'UPACARA', 'nama_mapel' => 'UPACARA', 'is_aktif' => true],
            ['kode_mapel' => 'BIO', 'nama_mapel' => 'Biologi', 'is_aktif' => true],
            ['kode_mapel' => 'WRI', 'nama_mapel' => 'Writing', 'is_aktif' => true],
            ['kode_mapel' => 'MAT-P', 'nama_mapel' => 'Mate (P)', 'is_aktif' => true],
            ['kode_mapel' => 'MAT-W', 'nama_mapel' => 'Mate (W)', 'is_aktif' => true],
            ['kode_mapel' => 'B-ING', 'nama_mapel' => 'B.Inggris', 'is_aktif' => true],
            ['kode_mapel' => 'PPKN', 'nama_mapel' => 'PPKn', 'is_aktif' => true],
            ['kode_mapel' => 'KEBAKTIAN', 'nama_mapel' => 'Kebaktian', 'is_aktif' => true],
            ['kode_mapel' => 'B-IND', 'nama_mapel' => 'B. Indonesia', 'is_aktif' => true],
            ['kode_mapel' => 'FIS', 'nama_mapel' => 'Fisika', 'is_aktif' => true],
            ['kode_mapel' => 'TINKOM', 'nama_mapel' => 'Tinkom', 'is_aktif' => true],
            ['kode_mapel' => 'AGAMA', 'nama_mapel' => 'Agama', 'is_aktif' => true],
            ['kode_mapel' => 'B-MAN', 'nama_mapel' => 'B. Mandarin', 'is_aktif' => true],
            ['kode_mapel' => 'SEJ', 'nama_mapel' => 'Sejarah', 'is_aktif' => true],
            ['kode_mapel' => 'KIM', 'nama_mapel' => 'Kimia', 'is_aktif' => true],
            ['kode_mapel' => 'S-BUD', 'nama_mapel' => 'S. Budaya', 'is_aktif' => true],
        ]);
    }

    public function down(): void
    {
        // Keep these reference rows because schedules and grades may use them after deployment.
    }
};
