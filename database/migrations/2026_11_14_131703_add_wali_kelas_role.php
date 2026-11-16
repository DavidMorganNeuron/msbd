<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $idRole = DB::table('role')->where('nama_role', 'wali_kelas')->value('id_role');

        if (! $idRole) {
            $idRole = DB::table('role')->insertGetId([
                'nama_role' => 'wali_kelas',
                'deskripsi' => 'Wali Kelas',
            ], 'id_role');
        }

        $hak = [
            'dashboard.view' => 'Melihat dashboard',
            'siswa.view' => 'Melihat data siswa',
            'siswa.update.approve' => 'Menyetujui perubahan data siswa',
        ];

        foreach ($hak as $kode => $nama) {
            DB::table('hak_akses')->updateOrInsert(
                ['kode_hak_akses' => $kode],
                ['nama_hak_akses' => $nama]
            );

            $idHak = DB::table('hak_akses')->where('kode_hak_akses', $kode)->value('id_hak_akses');
            DB::table('role_hak_akses')->updateOrInsert(
                ['id_role' => $idRole, 'id_hak_akses' => $idHak],
                []
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $idRole = DB::table('role')->where('nama_role', 'wali_kelas')->value('id_role');

        if (! $idRole) {
            return;
        }

        if (DB::table('pengguna')->where('id_role', $idRole)->exists()) {
            throw new RuntimeException('Wali Kelas accounts must be reassigned before this role can be removed.');
        }

        DB::table('role_hak_akses')->where('id_role', $idRole)->delete();
        DB::table('role')->where('id_role', $idRole)->delete();
    }
};
