<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['siswa', 'guru', 'wali_kelas', 'tu', 'kepsek'] as $role) {
            DB::table('role')->updateOrInsert(
                ['nama_role' => $role],
                ['deskripsi' => $role]
            );
        }
    }
}
