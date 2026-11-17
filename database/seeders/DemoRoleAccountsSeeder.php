<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoRoleAccountsSeeder extends Seeder
{
    private const USERS = [
        'siswa' => ['username' => 'demo_siswa'],
        'tu' => ['username' => 'demo_tu'],
        'kepsek' => ['username' => 'demo_kepsek'],
        'wali_kelas' => ['username' => 'demo_wali_kelas'],
    ];

    private const DEMO_NIS = 'DEMO-0001';

    private const DEMO_NISN = '9999999999';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Akun demo hanya boleh dibuat pada environment local.');
        }

        $password = config('demo.role_password');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Atur DEMO_ROLE_PASSWORD di .env lokal agar akun demo dapat dibuat.');
        }

        $this->ensurePostgreSqlWriteAccess();

        DB::transaction(function () use ($password): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select("SELECT set_config('app.user_id', '', true), set_config('app.username', 'demo-role-accounts-seeder', true)");
            }

            $roleIds = DB::table('role')
                ->whereIn('nama_role', array_keys(self::USERS))
                ->pluck('id_role', 'nama_role');

            if ($roleIds->count() !== count(self::USERS)) {
                throw new RuntimeException('Role siswa, tu, dan kepsek harus tersedia sebelum seeding akun demo.');
            }

            $statusSiswaId = DB::table('status_siswa')
                ->where('kode_status', 'aktif')
                ->value('id_status_siswa');

            if (! is_numeric($statusSiswaId)) {
                throw new RuntimeException('Status siswa aktif belum tersedia untuk akun demo.');
            }

            $userIds = [];

            foreach (self::USERS as $roleName => $user) {
                DB::table('pengguna')->updateOrInsert(
                    ['username' => $user['username']],
                    [
                        'id_role' => $roleIds[$roleName],
                        'password_hash' => Hash::make($password),
                        'is_aktif' => true,
                        'diubah_pada' => now(),
                    ]
                );

                $userId = DB::table('pengguna')
                    ->where('username', $user['username'])
                    ->value('id_pengguna');

                if (! is_numeric($userId)) {
                    throw new RuntimeException("Akun demo {$roleName} gagal dibuat.");
                }

                $userIds[$roleName] = (int) $userId;
            }

            $studentByNis = DB::table('siswa')->where('nis', self::DEMO_NIS)->first();
            $studentByNisn = DB::table('siswa')->where('nisn', self::DEMO_NISN)->first();

            if (
                $studentByNis !== null
                && $studentByNisn !== null
                && (int) $studentByNis->id_siswa !== (int) $studentByNisn->id_siswa
            ) {
                throw new RuntimeException('Identitas siswa demo sudah dipakai oleh dua data siswa berbeda.');
            }

            $student = $studentByNis ?? $studentByNisn;

            if (
                $student !== null
                && $student->id_pengguna !== null
                && (int) $student->id_pengguna !== $userIds['siswa']
            ) {
                throw new RuntimeException('Identitas siswa demo sudah terhubung ke akun lain.');
            }

            $studentAttributes = [
                'id_pengguna' => $userIds['siswa'],
                'id_status_siswa' => (int) $statusSiswaId,
                'nis' => self::DEMO_NIS,
                'nisn' => self::DEMO_NISN,
                'nama_lengkap' => 'Siswa Demo',
                'jenis_kelamin' => 'L',
                'diubah_pada' => now(),
            ];

            if ($student === null) {
                $studentAttributes['dibuat_pada'] = now();
                DB::table('siswa')->insert($studentAttributes);
            } else {
                DB::table('siswa')
                    ->where('id_siswa', $student->id_siswa)
                    ->update($studentAttributes);
            }
        });
    }

    private function ensurePostgreSqlWriteAccess(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $permissions = DB::selectOne(<<<'SQL'
            SELECT has_table_privilege(current_user, 'public.pengguna', 'INSERT') AS insert_pengguna,
                   has_table_privilege(current_user, 'public.pengguna', 'UPDATE') AS update_pengguna,
                   has_table_privilege(current_user, 'public.siswa', 'INSERT') AS insert_siswa,
                   has_table_privilege(current_user, 'public.siswa', 'UPDATE') AS update_siswa,
                   has_sequence_privilege(current_user, 'public.pengguna_id_pengguna_seq', 'USAGE') AS sequence_pengguna,
                   has_sequence_privilege(current_user, 'public.siswa_id_siswa_seq', 'USAGE') AS sequence_siswa
            SQL);

        if (
            ! $permissions->insert_pengguna
            || ! $permissions->update_pengguna
            || ! $permissions->insert_siswa
            || ! $permissions->update_siswa
            || ! $permissions->sequence_pengguna
            || ! $permissions->sequence_siswa
        ) {
            throw new RuntimeException(
                'Koneksi PostgreSQL tidak berwenang menulis akun demo. Gunakan koneksi pemeliharaan yang berwenang; jangan memperluas hak tulis app_login.'
            );
        }
    }
}
