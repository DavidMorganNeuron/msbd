<?php

namespace Tests\Feature\Admin;

use App\Models\Pengguna;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PenggunaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_kepsek_can_permanently_delete_an_unreferenced_account_and_its_tokens(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleGuru = $this->createRole('wali_kelas');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);
        $idTarget = $this->createAccount('guru-hapus', $idRoleGuru);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => Pengguna::class,
            'tokenable_id' => $idTarget,
            'name' => 'api',
            'token' => str_repeat('a', 64),
            'abilities' => '["*"]',
        ]);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->from(route('admin.pengguna'))
            ->delete(route('admin.pengguna.hapus', $idTarget))
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHas('success', 'Akun pengguna berhasil dihapus secara permanen.');

        $this->assertDatabaseMissing('pengguna', ['id_pengguna' => $idTarget]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $idTarget]);
    }

    public function test_deleted_teacher_account_is_hidden_from_staff_list_without_deleting_teacher_profile(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleWaliKelas = $this->createRole('wali_kelas');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);
        $idTarget = $this->createAccount('wali-naruto', $idRoleWaliKelas);
        $idGuru = DB::table('guru')->insertGetId([
            'id_pengguna' => $idTarget,
            'nama_guru' => 'Naruto',
            'is_aktif' => true,
        ]);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->from(route('admin.pengguna'))
            ->delete(route('admin.pengguna.hapus', $idTarget))
            ->assertRedirect(route('admin.pengguna'));

        $this->assertDatabaseHas('guru', [
            'id_guru' => $idGuru,
            'id_pengguna' => null,
            'nama_guru' => 'Naruto',
        ]);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->get(route('admin.staf'))
            ->assertOk()
            ->assertDontSee('Naruto');
    }

    public function test_kepsek_cannot_delete_their_own_account(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->from(route('admin.pengguna'))
            ->delete(route('admin.pengguna.hapus', $idActor))
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHasErrors([
                'pengguna' => 'Kamu tidak dapat menghapus akun sendiri.',
            ]);

        $this->assertDatabaseHas('pengguna', ['id_pengguna' => $idActor]);
    }

    public function test_kepsek_cannot_delete_an_account_referenced_by_protected_history(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleGuru = $this->createRole('wali_kelas');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);
        $idTarget = $this->createAccount('guru-riwayat', $idRoleGuru);
        DB::table('rapor')->insert(['id_penyusun' => $idTarget]);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->from(route('admin.pengguna'))
            ->delete(route('admin.pengguna.hapus', $idTarget))
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHasErrors([
                'pengguna' => 'Akun ini masih tercatat pada riwayat yang harus dipertahankan dan tidak dapat dihapus.',
            ]);

        $this->assertDatabaseHas('pengguna', ['id_pengguna' => $idTarget]);
        $this->assertDatabaseHas('rapor', ['id_penyusun' => $idTarget]);
    }

    public function test_kepsek_cannot_assign_roles_outside_wali_kelas_and_tata_usaha(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleWaliKelas = $this->createRole('wali_kelas');
        $idRoleGuru = $this->createRole('guru');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);
        $idTarget = $this->createAccount('wali-lama', $idRoleWaliKelas);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->from(route('admin.pengguna'))
            ->put(route('admin.pengguna.update', $idTarget), [
                'nama' => 'Wali Kelas',
                'username' => 'wali-lama',
                'id_role' => $idRoleGuru,
            ])
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHasErrors('id_role');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $idTarget,
            'id_role' => $idRoleWaliKelas,
        ]);
    }

    public function test_management_page_shows_a_confirmation_dialog_for_permanent_deletion(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleGuru = $this->createRole('wali_kelas');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);
        $this->createAccount('guru-daftar', $idRoleGuru);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->get(route('admin.pengguna'))
            ->assertOk()
            ->assertSee('Hapus akun secara permanen?')
            ->assertSee('Hapus permanen')
            ->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_kepsek_can_create_a_wali_kelas_account_and_teacher_profile(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleWaliKelas = $this->createRole('wali_kelas');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->post('/kepsek/pengguna/simpan', [
                'nama' => 'Guru Baru',
                'username' => 'guru-baru',
                'id_role' => $idRoleWaliKelas,
                'nip' => 'NIP-001',
                'no_hp' => '08123456789',
                'password' => 'SandiGuru123',
            ])
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHas('success', 'Pengguna berhasil ditambahkan.');

        $guru = DB::table('pengguna')->where('username', 'guru-baru')->first();

        $this->assertNotNull($guru);
        $this->assertTrue(Hash::check('SandiGuru123', $guru->password_hash));
        $this->assertDatabaseHas('guru', [
            'id_pengguna' => $guru->id_pengguna,
            'nama_guru' => 'Guru Baru',
            'nip' => 'NIP-001',
            'no_telepon' => '08123456789',
        ]);
    }

    public function test_kepsek_can_create_a_tu_account_and_staff_profile(): void
    {
        $idRoleKepsek = $this->createRole('kepsek');
        $idRoleTu = $this->createRole('tu');
        $idActor = $this->createAccount('kepsek-aktif', $idRoleKepsek);

        $this->withSession($this->sessionKepsek($idActor, 'kepsek-aktif'))
            ->post('/kepsek/pengguna/simpan', [
                'nama' => 'Staf Baru',
                'username' => 'staf-baru',
                'id_role' => $idRoleTu,
                'no_hp' => '08123456780',
                'password' => 'SandiStaf123',
            ])
            ->assertRedirect(route('admin.pengguna'))
            ->assertSessionHas('success', 'Pengguna berhasil ditambahkan.');

        $staf = DB::table('pengguna')->where('username', 'staf-baru')->first();

        $this->assertNotNull($staf);
        $this->assertTrue(Hash::check('SandiStaf123', $staf->password_hash));
        $this->assertDatabaseHas('staf', [
            'id_pengguna' => $staf->id_pengguna,
            'nama_staf' => 'Staf Baru',
            'jabatan' => 'Tata Usaha',
            'no_telepon' => '08123456780',
        ]);
    }

    private function createSchema(): void
    {
        foreach ([
            'cetak_rapor',
            'ekspor_laporan',
            'mutasi_siswa',
            'nilai_siswa',
            'rapor',
            'verifikasi_pendaftaran',
            'personal_access_tokens',
            'notifikasi',
            'log_audit',
            'guru',
            'staf',
            'pengguna',
            'role',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('role', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role');
            $table->string('kode_role')->nullable();
        });

        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->unsignedBigInteger('id_role');
            $table->string('username');
            $table->string('password_hash');
            $table->boolean('is_aktif')->default(true);
            $table->timestamp('terakhir_login')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diubah_pada')->nullable();
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('nama_guru')->nullable();
            $table->string('nip')->nullable();
            $table->string('no_telepon')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->nullOnDelete();
        });

        Schema::create('staf', function (Blueprint $table) {
            $table->id('id_staf');
            $table->unsignedBigInteger('id_pengguna')->nullable();
            $table->string('nama_staf')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telepon')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->nullOnDelete();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->unsignedBigInteger('id_pengguna');
            $table->boolean('dibaca')->default(false);
        });

        Schema::create('log_audit', function (Blueprint $table) {
            $table->id('id_log');
            $table->string('nama_tabel');
            $table->string('operasi');
            $table->text('id_data')->nullable();
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->unsignedBigInteger('id_pengguna_pengubah')->nullable();
            $table->text('username_pengubah')->nullable();
            $table->timestamp('waktu')->nullable();
        });

        foreach ([
            'cetak_rapor' => 'id_pencetak',
            'ekspor_laporan' => 'id_pengguna',
            'mutasi_siswa' => 'id_pengaju',
            'nilai_siswa' => 'id_penginput',
            'rapor' => 'id_penyusun',
            'verifikasi_pendaftaran' => 'id_petugas',
        ] as $table => $column) {
            Schema::create($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->id();
                $blueprint->unsignedBigInteger($column)->nullable();
            });
        }
    }

    private function createRole(string $name): int
    {
        return (int) DB::table('role')->insertGetId([
            'nama_role' => $name,
            'kode_role' => $name,
        ], 'id_role');
    }

    private function createAccount(string $username, int $idRole): int
    {
        return (int) DB::table('pengguna')->insertGetId([
            'id_role' => $idRole,
            'username' => $username,
            'password_hash' => 'hash',
            'is_aktif' => true,
        ], 'id_pengguna');
    }

    private function sessionKepsek(int $idPengguna, string $username): array
    {
        return [
            'id_pengguna' => $idPengguna,
            'username' => $username,
            'role' => 'kepsek',
            'nama' => $username,
        ];
    }
}
