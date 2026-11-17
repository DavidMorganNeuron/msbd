<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Pengguna;
use App\Models\Role;
use App\Models\Staf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DFD 1.2 Kelola Akun Pengguna (Kepala Sekolah): ubah akun, aktif/nonaktif, daftar guru & staf.
 * Tambah akun (penggunaSimpan) dan form edit tetap di PageController buatan tim.
 * Versi di sini menambah pengaman: tidak menghapus data guru, tidak mengunci Kepala Sekolah.
 */
class PenggunaController extends Controller
{
    public function update(Request $r, int $id)
    {
        $user = Pengguna::query()
            ->select(['id_pengguna', 'id_role', 'username', 'is_aktif', 'diubah_pada'])
            ->with('role:id_role,nama_role')
            ->findOrFail($id);

        $data = $r->validate([
            'nama' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100',
                Rule::unique('pengguna', 'username')->ignore($user->id_pengguna, 'id_pengguna')],
            'id_role' => [
                'required',
                'integer',
                Rule::in(Role::whereIn('nama_role', ['tu', 'wali_kelas'])->pluck('id_role')->all()),
            ],
            'nip' => ['nullable', 'string', 'max:40'],
            'no_hp' => ['nullable', 'string', 'max:30'],
        ]);

        $roleBaru = Role::findOrFail($data['id_role']);
        $roleLama = $user->role->nama_role ?? null;
        $diriSendiri = (int) $r->session()->get('id_pengguna') === (int) $user->id_pengguna;

        if (! in_array($roleBaru->nama_role, ['tu', 'wali_kelas'], true) || $roleLama === 'siswa') {
            return $this->tolak('id_role', 'Peran yang dapat dikelola hanya Wali Kelas dan Tata Usaha.');
        }
        if ($diriSendiri && (int) $roleBaru->id_role !== (int) $user->id_role) {
            return $this->tolak('id_role', 'Peran akun sendiri tidak dapat diubah. Minta Kepala Sekolah lain jika perlu.');
        }
        if ($roleLama === 'kepsek' && $roleBaru->nama_role !== 'kepsek' && ! $this->adaKepsekAktifLain($user->id_pengguna)) {
            return $this->tolak('id_role', 'Harus tersisa minimal satu Kepala Sekolah aktif.');
        }

        $guru = Guru::where('id_pengguna', $user->id_pengguna)->first();
        $peranGuru = ['wali_kelas'];
        if (in_array($roleLama, $peranGuru, true)
            && ! in_array($roleBaru->nama_role, $peranGuru, true)
            && $guru
            && $this->guruMasihDipakai($guru->id_guru)) {
            return $this->tolak('id_role', 'Wali Kelas ini masih punya jadwal atau tugas sebagai wali kelas. Pindahkan dulu sebelum mengubah perannya.');
        }

        DB::transaction(function () use ($user, $data, $roleBaru) {
            $user->update([
                'id_role' => $roleBaru->id_role,
                'username' => $data['username'],
                'diubah_pada' => now(),
            ]);

            if ($roleBaru->nama_role === 'wali_kelas') {
                Staf::where('id_pengguna', $user->id_pengguna)->get()->each->update(['is_aktif' => false]);
                Guru::updateOrCreate(['id_pengguna' => $user->id_pengguna], [
                    'nip' => $data['nip'] ?: null,
                    'nama_guru' => $data['nama'],
                    'no_telepon' => $data['no_hp'] ?: null,
                    'is_aktif' => true,
                ]);
            } elseif (in_array($roleBaru->nama_role, ['tu', 'kepsek'], true)) {
                // Dinonaktifkan, bukan dihapus: riwayat tetap ada dan bisa diaktifkan lagi.
                Guru::where('id_pengguna', $user->id_pengguna)->get()->each->update(['is_aktif' => false]);
                Staf::updateOrCreate(['id_pengguna' => $user->id_pengguna], [
                    'nama_staf' => $data['nama'],
                    'jabatan' => $roleBaru->nama_role === 'tu' ? 'Tata Usaha' : 'Kepala Sekolah',
                    'no_telepon' => $data['no_hp'] ?: null,
                    'is_aktif' => true,
                ]);
            }
        });

        return redirect()->route('admin.pengguna')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function ubahStatus(Request $r, int $id)
    {
        $user = Pengguna::query()
            ->select(['id_pengguna', 'id_role', 'username', 'is_aktif', 'diubah_pada'])
            ->with('role:id_role,nama_role')
            ->findOrFail($id);

        if ((int) $r->session()->get('id_pengguna') === (int) $user->id_pengguna) {
            return back()->withErrors(['status' => 'Kamu tidak dapat menonaktifkan akun sendiri.']);
        }
        if ($user->is_aktif && ($user->role->nama_role ?? null) === 'kepsek' && ! $this->adaKepsekAktifLain($user->id_pengguna)) {
            return back()->withErrors(['status' => 'Harus tersisa minimal satu Kepala Sekolah aktif.']);
        }

        $user->is_aktif = ! $user->is_aktif;
        $user->diubah_pada = now();
        $user->save();

        return redirect()->route('admin.pengguna')->with(
            'success',
            $user->is_aktif ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.'
        );
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = Pengguna::query()
            ->select(['id_pengguna', 'id_role', 'username', 'is_aktif', 'diubah_pada'])
            ->with('role:id_role,nama_role')
            ->findOrFail($id);

        if ((int) $request->session()->get('id_pengguna') === (int) $user->id_pengguna) {
            return back()->withErrors(['pengguna' => 'Kamu tidak dapat menghapus akun sendiri.']);
        }

        if ($user->is_aktif && ($user->role->nama_role ?? null) === 'kepsek' && ! $this->adaKepsekAktifLain($user->id_pengguna)) {
            return back()->withErrors(['pengguna' => 'Harus tersisa minimal satu Kepala Sekolah aktif.']);
        }

        foreach ([
            'cetak_rapor' => 'id_pencetak',
            'ekspor_laporan' => 'id_pengguna',
            'mutasi_siswa' => 'id_pengaju',
            'nilai_siswa' => 'id_penginput',
            'rapor' => 'id_penyusun',
            'verifikasi_pendaftaran' => 'id_petugas',
        ] as $table => $column) {
            if (DB::table($table)->where($column, $user->id_pengguna)->exists()) {
                return back()->withErrors([
                    'pengguna' => 'Akun ini masih tercatat pada riwayat yang harus dipertahankan dan tidak dapat dihapus.',
                ]);
            }
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return redirect()
            ->route('admin.pengguna')
            ->with('success', 'Akun pengguna berhasil dihapus secara permanen.');
    }

    /** DFD 1.2: data pegawai (read-only). Perubahan data dilakukan lewat menu Pengguna. */
    public function staf(Request $r)
    {
        $cari = trim((string) $r->query('cari', ''));
        $like = '%'.addcslashes($cari, '%_\\').'%';

        $guru = DB::table('guru as g')
            ->join('pengguna as p', 'p.id_pengguna', '=', 'g.id_pengguna')
            ->leftJoin('role as r', 'r.id_role', '=', 'p.id_role')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('g.nama_guru', 'ilike', $like)->orWhere('g.nip', 'ilike', $like)->orWhere('p.username', 'ilike', $like)))
            ->orderBy('g.nama_guru')
            ->get(['g.nama_guru', 'g.nip', 'g.no_telepon', 'g.is_aktif', 'p.id_pengguna', 'p.username', 'r.nama_role']);
        $waliKelas = $guru->values();

        $staf = DB::table('staf as s')
            ->leftJoin('pengguna as p', 'p.id_pengguna', '=', 's.id_pengguna')
            ->leftJoin('role as r', 'r.id_role', '=', 'p.id_role')
            ->where('r.nama_role', 'tu')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('s.nama_staf', 'ilike', $like)->orWhere('s.jabatan', 'ilike', $like)->orWhere('p.username', 'ilike', $like)))
            ->orderBy('s.nama_staf')
            ->get(['s.nama_staf', 's.jabatan', 's.no_telepon', 's.is_aktif', 'p.id_pengguna', 'p.username']);

        return view('admin.staf', compact('waliKelas', 'guru', 'staf', 'cari'));
    }

    /* ---------------- helper ---------------- */

    private function tolak(string $kolom, string $pesan)
    {
        return back()->withInput()->withErrors([$kolom => $pesan]);
    }

    private function adaKepsekAktifLain(int $idPengguna): bool
    {
        return Pengguna::query()
            ->where('is_aktif', true)
            ->where('id_pengguna', '!=', $idPengguna)
            ->whereHas('role', fn ($q) => $q->where('nama_role', 'kepsek'))
            ->exists();
    }

    private function guruMasihDipakai(int $idGuru): bool
    {
        return DB::table('jadwal_pelajaran')->where('id_guru', $idGuru)->exists()
            || DB::table('penugasan_wali_kelas')->where('id_guru', $idGuru)->exists();
    }
}
