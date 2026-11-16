<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiswaController extends Controller
{
    // Menampilkan daftar siswa dan pencarian
    public function index(Request $request)
    {
        $query = DB::table('siswa as s')
            ->leftJoin(
                'status_siswa as st',
                's.id_status_siswa',
                '=',
                'st.id_status_siswa'
            )
            ->select(
                's.id_siswa',
                's.nis',
                's.nisn',
                's.nama_lengkap',
                's.jenis_kelamin',
                'st.nama_status',
                DB::raw('(SELECT k.nama_kelas FROM keanggotaan_kelas kk JOIN kelas k ON k.id_kelas = kk.id_kelas JOIN semester sm ON sm.id_semester = kk.id_semester WHERE kk.id_siswa = s.id_siswa ORDER BY sm.tanggal_mulai DESC NULLS LAST, sm.id_semester DESC LIMIT 1) AS nama_kelas')
            );

        if ($request->filled('cari')) {
            $cari = $request->cari;

            $query->where(function ($q) use ($cari) {
                $q->where('s.nama_lengkap', 'ilike', "%{$cari}%")
                    ->orWhere('s.nisn', 'ilike', "%{$cari}%")
                    ->orWhere('s.nis', 'ilike', "%{$cari}%");
            });
        }

        $daftar = $query
            ->orderBy('s.nama_lengkap')
            ->paginate(15)
            ->through(fn ($row) => [
                'id' => $row->id_siswa,
                'nis' => $row->nis,
                'nisn' => $row->nisn,
                'nama' => $row->nama_lengkap,
                'jenis_kelamin' => $row->jenis_kelamin,
                'status' => $row->nama_status,
                'kelas' => $row->nama_kelas,
            ])
            ->withQueryString();

        return view('tu.siswa', compact('daftar'));
    }

    // Menampilkan detail siswa
    public function show(int $id)
    {
        $siswa = Siswa::findOrFail($id);

        $alamat = DB::table('alamat_siswa')
            ->where('id_siswa', $id)
            ->first();

        $orangTua = DB::table('orang_tua_wali')
            ->where('id_siswa', $id)
            ->get();

        $ppdb = $siswa->nisn
            ? DB::table('pendaftaran_ppdb as p')
                ->leftJoin('agama as a', 'a.id_agama', '=', 'p.id_agama')
                ->leftJoin('kelas as k', 'k.id_kelas', '=', 'p.id_kelas_diterima')
                ->where('p.nisn', $siswa->nisn)
                ->where('p.status_pendaftaran', '<>', 'draf')
                ->orderByDesc('p.diajukan_pada')
                ->first(['p.*', 'a.nama_agama as agama', 'k.nama_kelas as kelas_diterima'])
            : null;

        $ppdbAlamat = $ppdb
            ? DB::table('ppdb_alamat')->where('id_pendaftaran', $ppdb->id_pendaftaran)->first()
            : null;
        $ppdbPeriodik = $ppdb
            ? DB::table('ppdb_periodik')->where('id_pendaftaran', $ppdb->id_pendaftaran)->first()
            : null;
        $ppdbOrangTua = $ppdb
            ? DB::table('ppdb_orang_tua as o')
                ->leftJoin('pekerjaan as p', 'p.id_pekerjaan', '=', 'o.id_pekerjaan')
                ->where('o.id_pendaftaran', $ppdb->id_pendaftaran)
                ->orderBy('o.hubungan')
                ->get([
                    'o.hubungan', 'o.nama_lengkap', 'o.nik', 'o.tanggal_lahir', 'o.tempat_lahir',
                    'o.pendidikan', 'p.nama_pekerjaan as pekerjaan', 'o.penghasilan',
                    'o.berkebutuhan_khusus', 'o.no_telepon', 'o.alamat',
                ])
            : collect();
        $ppdbPrestasi = $ppdb
            ? DB::table('ppdb_prestasi')->where('id_pendaftaran', $ppdb->id_pendaftaran)->orderBy('id_prestasi')->get()
            : collect();
        $ppdbBeasiswa = $ppdb
            ? DB::table('ppdb_beasiswa')->where('id_pendaftaran', $ppdb->id_pendaftaran)->orderBy('id_beasiswa')->get()
            : collect();
        $ppdbKeluar = $ppdb
            ? DB::table('ppdb_keluar')->where('id_pendaftaran', $ppdb->id_pendaftaran)->first()
            : null;

        $dokumen = DB::table('dokumen_siswa')
            ->where('id_siswa', $id)
            ->where(function ($q) {
                $q->whereNull('status_verifikasi')
                    ->orWhere('status_verifikasi', '<>', 'ditolak');
            })
            ->orderByDesc('diunggah_pada')
            ->get()
            ->map(function ($d) {
                $path = $d->path_file ?? null;
                $ukuran = '-';

                try {
                    $disk = null;
                    foreach (['private', 'public'] as $candidate) {
                        if ($path && Storage::disk($candidate)->exists($path)) {
                            $disk = $candidate;
                            break;
                        }
                    }

                    if ($disk) {
                        $ukuran = number_format(Storage::disk($disk)->size($path) / 1024, 0).' KB';
                    }
                } catch (\Throwable $e) {
                    $ukuran = '-';
                }

                $d->ukuran = $ukuran;

                return $d;
            });

        return view('tu.siswa-detail', compact(
            'siswa',
            'alamat',
            'orangTua',
            'ppdb',
            'ppdbAlamat',
            'ppdbPeriodik',
            'ppdbOrangTua',
            'ppdbPrestasi',
            'ppdbBeasiswa',
            'ppdbKeluar',
            'dokumen'
        ));
    }

    // Menyimpan siswa baru
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nis' => ['nullable', 'string', 'max:30', Rule::unique('siswa', 'nis')],
            'nisn' => ['nullable', 'string', 'size:10', Rule::unique('siswa', 'nisn')],
            'nik' => ['nullable', 'string', 'size:16', Rule::unique('siswa', 'nik')],
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'no_telepon' => 'nullable|string|max:20',
        ], [
            'nis.unique' => 'NIS sudah dipakai siswa lain.',
            'nisn.size' => 'NISN harus 10 digit.',
            'nisn.unique' => 'NISN sudah dipakai siswa lain.',
            'nik.size' => 'NIK harus 16 digit.',
            'nik.unique' => 'NIK sudah dipakai siswa lain.',
        ]);

        try {
            DB::transaction(function () use ($data) {
                $data['id_pengguna'] = $this->buatAtauHubungkanAkun($data);
                $data['id_status_siswa'] = $this->statusSiswaAktif();
                $data['dibuat_pada'] = now();
                $data['diubah_pada'] = now();
                Siswa::create($data);
            });
        } catch (QueryException $e) {
            report($e);

            throw ValidationException::withMessages([
                'nama_lengkap' => $this->pesanGagalSimpan($e),
            ]);
        }

        return redirect()
            ->route('tu.siswa')
            ->with('success', 'Data siswa berhasil ditambahkan.');
    }

    // Memperbarui data siswa
    public function update(Request $request, int $id)
    {
        $siswa = Siswa::findOrFail($id);

        $data = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nis' => ['nullable', 'string', 'max:30', Rule::unique('siswa', 'nis')->ignore($siswa->id_siswa, 'id_siswa')],
            'nisn' => ['nullable', 'string', 'size:10', Rule::unique('siswa', 'nisn')->ignore($siswa->id_siswa, 'id_siswa')],
            'nik' => ['nullable', 'string', 'size:16', Rule::unique('siswa', 'nik')->ignore($siswa->id_siswa, 'id_siswa')],
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'no_telepon' => 'nullable|string|max:20',
        ], [
            'nis.unique' => 'NIS sudah dipakai siswa lain.',
            'nisn.size' => 'NISN harus 10 digit.',
            'nisn.unique' => 'NISN sudah dipakai siswa lain.',
            'nik.size' => 'NIK harus 16 digit.',
            'nik.unique' => 'NIK sudah dipakai siswa lain.',
        ]);

        $data['diubah_pada'] = now();

        try {
            $siswa->update($data);
        } catch (QueryException $e) {
            report($e);

            throw ValidationException::withMessages([
                'nama_lengkap' => $this->pesanGagalSimpan($e),
            ]);
        }

        return redirect()
            ->route('tu.siswa')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    // Menghapus siswa
    public function destroy(int $id)
    {
        $siswa = Siswa::findOrFail($id);

        // Riwayat akademik dan data terkait yang bersifat historis tidak boleh
        // ikut terhapus. Penghapusan hanya aman untuk siswa tanpa riwayat.
        if ($this->punyaRiwayat($id)) {
            return back()->with(
                'error',
                'Siswa ini sudah memiliki riwayat (keanggotaan kelas, nilai, absensi, rapor, mutasi, konfirmasi data, atau pengajuan perubahan) sehingga tidak dapat dihapus. Gunakan menu Mutasi Siswa untuk mencatat siswa keluar atau pindah.'
            );
        }

        try {
            DB::transaction(function () use ($siswa) {
                $idPengguna = $siswa->id_pengguna;

                $siswa->delete();

                if (! $idPengguna) {
                    return;
                }

                // Jangan hapus akun yang masih terhubung ke data lain; cukup
                // nonaktifkan agar riwayat login dan relasi tetap aman.
                $dipakaiLain = DB::table('guru')->where('id_pengguna', $idPengguna)->exists()
                    || DB::table('staf')->where('id_pengguna', $idPengguna)->exists();

                if (! $dipakaiLain) {
                    DB::table('pengguna')
                        ->where('id_pengguna', $idPengguna)
                        ->update(['is_aktif' => false, 'diubah_pada' => now()]);
                }
            });
        } catch (QueryException $e) {
            report($e);

            return back()->with(
                'error',
                'Data siswa tidak dapat dihapus karena masih terhubung dengan data lain.'
            );
        }

        return redirect()
            ->route('tu.siswa')
            ->with('success', 'Data siswa berhasil dihapus.');
    }

    /** Cari siswa dengan riwayat yang harus dipertahankan. */
    private function punyaRiwayat(int $idSiswa): bool
    {
        foreach ([
            'keanggotaan_kelas',
            'detail_absensi',
            'catatan_wali_kelas',
            'izin_siswa',
            'mutasi_siswa',
            'konfirmasi_data',
            'pengajuan_perubahan',
        ] as $tabel) {
            if (DB::table($tabel)->where('id_siswa', $idSiswa)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** Buat akun pengguna baru atau hubungkan akun siswa yang sudah ada. */
    private function buatAtauHubungkanAkun(array $data): ?int
    {
        $field = trim((string) ($data['nisn'] ?? '')) !== '' ? 'nisn' : 'nis';
        $username = trim((string) ($data[$field] ?? ''));

        if ($username === '') {
            return null;
        }

        $idRole = DB::table('role')->where('nama_role', 'siswa')->value('id_role');

        if (! $idRole) {
            throw ValidationException::withMessages([
                $field => 'Peran siswa belum tersedia di tabel role.',
            ]);
        }

        $akun = DB::table('pengguna')
            ->where('username', $username)
            ->first(['id_pengguna', 'id_role', 'is_aktif']);

        if ($akun) {
            if ((int) $akun->id_role !== (int) $idRole) {
                throw ValidationException::withMessages([
                    $field => 'NIS/NISN '.$username.' sudah dipakai akun dengan peran lain.',
                ]);
            }

            if (DB::table('siswa')->where('id_pengguna', $akun->id_pengguna)->exists()) {
                throw ValidationException::withMessages([
                    $field => 'Akun '.$username.' sudah terhubung ke data siswa lain.',
                ]);
            }

            $dipakaiLain = DB::table('guru')->where('id_pengguna', $akun->id_pengguna)->exists()
                || DB::table('staf')->where('id_pengguna', $akun->id_pengguna)->exists();

            if (! $akun->is_aktif) {
                if ($dipakaiLain) {
                    throw ValidationException::withMessages([
                        $field => 'Akun '.$username.' sedang nonaktif dan terhubung ke data lain. Periksa menu pengguna terlebih dahulu.',
                    ]);
                }

                // Akun siswa yatim (tanpa data siswa) dipakai ulang untuk NIS/NISN yang sama.
                DB::table('pengguna')
                    ->where('id_pengguna', $akun->id_pengguna)
                    ->update(['is_aktif' => true, 'diubah_pada' => now()]);
            }

            return (int) $akun->id_pengguna;
        }

        return (int) DB::table('pengguna')->insertGetId([
            'id_role' => $idRole,
            'username' => $username,
            'password_hash' => Hash::make($username),
            'is_aktif' => true,
            'dibuat_pada' => now(),
            'diubah_pada' => now(),
        ], 'id_pengguna');
    }

    /** Status awal siswa baru mengikuti referensi status yang sudah dipakai PPDB. */
    private function statusSiswaAktif(): int
    {
        $idStatus = DB::table('status_siswa')->where('kode_status', 'aktif')->value('id_status_siswa')
            ?? DB::table('status_siswa')->orderBy('id_status_siswa')->value('id_status_siswa');

        if (! $idStatus) {
            throw ValidationException::withMessages([
                'nama_lengkap' => 'Status siswa belum tersedia. Tambahkan data status siswa terlebih dahulu.',
            ]);
        }

        return (int) $idStatus;
    }

    /** Ubah pelanggaran constraint database menjadi pesan yang bisa dibaca. */
    private function pesanGagalSimpan(QueryException $e): string
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());

        if ($sqlState === '23505') {
            $pesan = (string) ($e->errorInfo[2] ?? '');

            if (stripos($pesan, 'nisn') !== false) {
                return 'NISN sudah dipakai siswa lain.';
            }

            if (stripos($pesan, 'nik') !== false) {
                return 'NIK sudah dipakai siswa lain.';
            }

            if (stripos($pesan, 'nis') !== false) {
                return 'NIS sudah dipakai siswa lain.';
            }

            if (stripos($pesan, 'username') !== false) {
                return 'Username siswa sudah dipakai akun lain.';
            }

            return 'Data siswa tidak dapat disimpan karena ada nilai yang sudah dipakai.';
        }

        return 'Data siswa tidak dapat disimpan. Periksa kembali isian dan data terkait.';
    }
}
