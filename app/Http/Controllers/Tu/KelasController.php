<?php

namespace App\Http\Controllers\Tu;

use App\Http\Controllers\Controller;
use App\Services\KelasMassalService;
use App\Support\AuditCatat;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * DFD 2.3 Penugasan Wali Kelas (Tata Usaha) dan daftar siswa per kelas.
 * Aturan database: satu wali per kelas per semester (UNIQUE id_kelas, id_semester).
 * Seorang guru boleh menjadi wali di beberapa kelas; halaman hanya memberi peringatan.
 */
class KelasController extends Controller
{
    public function index(Request $r)
    {
        $semesters = $this->daftarSemester();
        $sem = $this->pilihSemester($r, $semesters);
        $filters = $r->validate([
            'tingkat' => ['nullable', Rule::in(['X', 'XI', 'XII'])],
            'jurusan' => ['nullable', Rule::in(['MIA', 'IIS'])],
        ]);
        $filterTingkat = $filters['tingkat'] ?? null;
        $filterJurusan = $filters['jurusan'] ?? null;
        $kelas = $sem ? $this->daftarKelas($sem, $filterTingkat, $filterJurusan) : collect();
        $guru = DB::table('guru as g')
            ->join('pengguna as p', 'p.id_pengguna', '=', 'g.id_pengguna')
            ->join('role as r', 'r.id_role', '=', 'p.id_role')
            ->where('g.is_aktif', true)
            ->where('p.is_aktif', true)
            ->whereIn('r.nama_role', ['guru', 'wali_kelas'])
            ->orderByRaw("CASE WHEN r.nama_role = 'wali_kelas' THEN 0 ELSE 1 END")
            ->orderBy('g.nama_guru')
            ->get(['g.id_guru', 'g.nama_guru', 'r.nama_role']);
        $tahunAjaran = DB::table('tahun_ajaran')
            ->orderByDesc('tanggal_mulai')
            ->get(['id_tahun_ajaran', 'nama_tahun_ajaran', 'is_aktif']);
        $tingkat = DB::table('tingkat')->orderBy('urutan')->get(['id_tingkat', 'nama_tingkat', 'urutan']);
        $jurusan = DB::table('jurusan')->where('is_aktif', true)->orderBy('kode_jurusan')->get(['id_jurusan', 'kode_jurusan', 'nama_jurusan']);

        return view('tu.kelas', compact(
            'semesters', 'sem', 'kelas', 'guru', 'tahunAjaran', 'tingkat', 'jurusan', 'filterTingkat', 'filterJurusan'
        ));
    }

    public function tetapkan(Request $r)
    {
        // Form Manajemen Kelas memakai endpoint yang sama agar route lama tidak berubah.
        if ($r->input('aksi') === 'kelas') {
            return $this->simpan($r);
        }
        if ($r->input('aksi') === 'duplikat') {
            return $this->duplikat($r);
        }
        if ($r->input('aksi') === 'buat') {
            return $this->buatOtomatis($r, app(KelasMassalService::class));
        }
        $d = $r->validate([
            'id_kelas' => 'required|integer|exists:kelas,id_kelas',
            'id_guru' => 'required|integer|exists:guru,id_guru',
            'id_semester' => 'required|integer|exists:semester,id_semester',
        ]);

        $kelas = DB::table('kelas')->where('id_kelas', $d['id_kelas'])->first();
        $guru = $this->waliKelasAktif((int) $d['id_guru']);
        $sem = DB::table('semester')->where('id_semester', $d['id_semester'])->first();

        $galat = match (true) {
            ! $kelas->is_aktif => 'Kelas ini sudah nonaktif.',
            ! $guru => 'Pilih akun Guru atau Wali Kelas yang aktif.',
            $kelas->id_tahun_ajaran !== null && (int) $kelas->id_tahun_ajaran !== (int) $sem->id_tahun_ajaran => 'Kelas ini milik tahun ajaran lain, bukan tahun ajaran semester yang dipilih.',
            default => null,
        };
        if ($galat) {
            return back()->withInput()->withErrors(['id_kelas' => $galat]);
        }

        try {
            $pesan = DB::transaction(function () use ($d, $kelas, $guru) {
                $ada = DB::table('penugasan_wali_kelas')
                    ->where('id_kelas', $d['id_kelas'])->where('id_semester', $d['id_semester'])
                    ->lockForUpdate()->first();

                if ($ada && (int) $ada->id_guru === (int) $d['id_guru']) {
                    return 'Wali Kelas tersebut sudah menjadi wali kelas ini. Tidak ada perubahan.';
                }

                if ($ada) {
                    $lamaNama = DB::table('guru')->where('id_guru', $ada->id_guru)->value('nama_guru');
                    DB::table('penugasan_wali_kelas')->where('id_penugasan_wali_kelas', $ada->id_penugasan_wali_kelas)
                        ->update(['id_guru' => $d['id_guru'], 'ditugaskan_pada' => now()]);
                    AuditCatat::tulis('penugasan_wali_kelas', 'UPDATE', $ada->id_penugasan_wali_kelas,
                        ['kelas' => $kelas->nama_kelas, 'wali' => $lamaNama],
                        ['kelas' => $kelas->nama_kelas, 'wali' => $guru->nama_guru]);

                    return "Wali kelas {$kelas->nama_kelas} diganti menjadi {$guru->nama_guru}.";
                }

                $id = DB::table('penugasan_wali_kelas')->insertGetId([
                    'id_kelas' => $d['id_kelas'], 'id_guru' => $d['id_guru'],
                    'id_semester' => $d['id_semester'], 'ditugaskan_pada' => now(),
                ], 'id_penugasan_wali_kelas');
                AuditCatat::tulis('penugasan_wali_kelas', 'INSERT', $id, null,
                    ['kelas' => $kelas->nama_kelas, 'wali' => $guru->nama_guru, 'id_semester' => (int) $d['id_semester']]);

                return "{$guru->nama_guru} ditetapkan sebagai wali kelas {$kelas->nama_kelas}.";
            });
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()->withErrors(['id_kelas' => 'Kelas ini baru saja diberi wali oleh pengguna lain. Muat ulang halaman.']);
        }

        return redirect()->route('tu.kelas', ['semester' => $d['id_semester']])->with('success', $pesan);
    }

    public function cabut(Request $r, int $id)
    {
        // Hapus kelas memakai endpoint yang sama agar route lama tidak berubah.
        if ($r->input('jenis') === 'kelas') {
            return $this->hapus($id);
        }

        $baris = DB::transaction(function () use ($id) {
            $p = DB::table('penugasan_wali_kelas as pw')
                ->join('kelas as k', 'k.id_kelas', '=', 'pw.id_kelas')
                ->join('guru as g', 'g.id_guru', '=', 'pw.id_guru')
                ->where('pw.id_penugasan_wali_kelas', $id)->lockForUpdate()
                ->first(['pw.id_semester', 'k.nama_kelas', 'g.nama_guru']);
            if (! $p) {
                return null;
            }

            DB::table('penugasan_wali_kelas')->where('id_penugasan_wali_kelas', $id)->delete();
            AuditCatat::tulis('penugasan_wali_kelas', 'DELETE', $id, ['kelas' => $p->nama_kelas, 'wali' => $p->nama_guru], null);

            return $p;
        });
        abort_unless($baris, 404);

        return redirect()->route('tu.kelas', ['semester' => $baris->id_semester])
            ->with('success', "Wali kelas {$baris->nama_kelas} dicabut.");
    }

    /** Ubah kelas yang sudah ada. */
    public function simpan(Request $r)
    {
        $r->merge(['nama_kelas' => trim((string) $r->input('nama_kelas'))]);
        $id = $r->input('id_kelas') ? (int) $r->input('id_kelas') : null;

        $d = $r->validate([
            'id_kelas' => 'required|integer|exists:kelas,id_kelas',
            'nama_kelas' => [
                'required', 'string', 'max:80',
                Rule::unique('kelas', 'nama_kelas')
                    ->where(fn ($q) => $q->where('id_tahun_ajaran', $r->input('id_tahun_ajaran')))
                    ->ignore($id, 'id_kelas'),
            ],
            'id_tahun_ajaran' => 'required|integer|exists:tahun_ajaran,id_tahun_ajaran',
            'id_tingkat' => 'required|integer|exists:tingkat,id_tingkat',
            'id_jurusan' => 'required|integer|exists:jurusan,id_jurusan',
            'kapasitas' => 'nullable|integer|min:1|max:100',
            'id_guru' => 'nullable|integer|exists:guru,id_guru',
            'id_semester' => 'nullable|integer|exists:semester,id_semester',
        ]);

        $id = (int) $d['id_kelas'];
        $sem = $this->semesterUntukTahun((int) $d['id_tahun_ajaran'], (int) ($d['id_semester'] ?? 0));
        $nama = trim($d['nama_kelas']);
        $idGuru = ! empty($d['id_guru']) ? (int) $d['id_guru'] : null;

        if ($idGuru !== null && ! $this->waliKelasAktif($idGuru)) {
            return back()->withInput()->withErrors(['id_guru' => 'Pilih akun Guru atau Wali Kelas yang aktif.']);
        }

        try {
            $pesan = DB::transaction(function () use ($id, $d, $sem, $nama, $idGuru) {
                $kelas = DB::table('kelas')->where('id_kelas', $id)->lockForUpdate()->first();
                abort_unless($kelas, 404);

                $identitasBerubah = $kelas->nama_kelas !== $nama
                    || (int) $kelas->id_tahun_ajaran !== (int) $d['id_tahun_ajaran']
                    || (int) $kelas->id_tingkat !== (int) $d['id_tingkat']
                    || (int) $kelas->id_jurusan !== (int) $d['id_jurusan'];

                if ($identitasBerubah && DB::table('keanggotaan_kelas')->where('id_kelas', $id)->exists()) {
                    throw ValidationException::withMessages([
                        'nama_kelas' => 'Identitas kelas yang sudah memiliki siswa tidak dapat diubah. Pemindahan siswa hanya dilakukan melalui proses kenaikan kelas.',
                    ]);
                }

                $pindahTahun = (int) $kelas->id_tahun_ajaran !== (int) $d['id_tahun_ajaran'];
                DB::table('kelas')->where('id_kelas', $id)->update([
                    'nama_kelas' => $nama,
                    'id_tahun_ajaran' => (int) $d['id_tahun_ajaran'],
                    'id_tingkat' => (int) $d['id_tingkat'],
                    'id_jurusan' => (int) $d['id_jurusan'],
                    'kapasitas' => ! empty($d['kapasitas']) ? (int) $d['kapasitas'] : null,
                ]);

                if ($pindahTahun) {
                    DB::table('penugasan_wali_kelas')->where('id_kelas', $id)->delete();
                }

                AuditCatat::tulis('kelas', 'UPDATE', $id,
                    [
                        'nama_kelas' => $kelas->nama_kelas,
                        'id_tahun_ajaran' => $kelas->id_tahun_ajaran,
                        'id_tingkat' => $kelas->id_tingkat,
                        'id_jurusan' => $kelas->id_jurusan,
                        'kapasitas' => $kelas->kapasitas,
                    ],
                    [
                        'nama_kelas' => $nama,
                        'id_tahun_ajaran' => (int) $d['id_tahun_ajaran'],
                        'id_tingkat' => (int) $d['id_tingkat'],
                        'id_jurusan' => (int) $d['id_jurusan'],
                        'kapasitas' => ! empty($d['kapasitas']) ? (int) $d['kapasitas'] : null,
                    ]);

                if ($sem) {
                    $this->terapkanWali($id, $idGuru, (int) $sem->id_semester);
                }

                return "Kelas {$nama} berhasil diperbarui.";
            });
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()->withErrors(['nama_kelas' => 'Nama kelas ini sudah dipakai pada tahun ajaran tersebut.']);
        } catch (QueryException $e) {
            report($e);

            return back()->withInput()->withErrors(['nama_kelas' => 'Kelas tidak dapat disimpan karena masih terhubung dengan data lain.']);
        }

        return redirect()
            ->route('tu.kelas', array_filter(['semester' => $sem?->id_semester ?? ($d['id_semester'] ?? null)]))
            ->with('success', $pesan);
    }

    public function hapus(int $id)
    {
        $kelas = DB::table('kelas')->where('id_kelas', $id)->first();
        abort_unless($kelas, 404);

        $dipakai = DB::table('keanggotaan_kelas')->where('id_kelas', $id)->exists()
            || DB::table('jadwal_pelajaran')->where('id_kelas', $id)->exists();

        if ($dipakai) {
            return back()->with('error', "Kelas {$kelas->nama_kelas} tidak bisa dihapus karena sudah memiliki siswa atau jadwal. Nonaktifkan kelas ini bila sudah tidak dipakai.");
        }

        try {
            DB::transaction(function () use ($id, $kelas) {
                DB::table('penugasan_wali_kelas')->where('id_kelas', $id)->delete();
                DB::table('kelas')->where('id_kelas', $id)->delete();

                AuditCatat::tulis('kelas', 'DELETE', $id,
                    ['nama_kelas' => $kelas->nama_kelas, 'id_tahun_ajaran' => $kelas->id_tahun_ajaran], null);
            });
        } catch (QueryException $e) {
            return back()->with('error', "Kelas {$kelas->nama_kelas} tidak bisa dihapus karena masih terhubung dengan data lain.");
        }

        return back()->with('success', "Kelas {$kelas->nama_kelas} dihapus.");
    }

    /** Salin daftar kelas dari tahun ajaran sebelumnya ke tahun ajaran yang sedang dipilih. */
    public function duplikat(Request $r)
    {
        $d = $r->validate([
            'id_tahun_ajaran' => 'required|integer|exists:tahun_ajaran,id_tahun_ajaran',
            'id_semester' => 'nullable|integer|exists:semester,id_semester',
        ]);

        $target = DB::table('tahun_ajaran')->where('id_tahun_ajaran', $d['id_tahun_ajaran'])->first();
        $sumberTahun = DB::table('tahun_ajaran')
            ->where('tanggal_mulai', '<', $target->tanggal_mulai)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $sumberTahun) {
            return back()->with('error', 'Tidak ada tahun ajaran sebelumnya untuk diduplikat.');
        }

        $sumber = DB::table('kelas')
            ->where('id_tahun_ajaran', $sumberTahun->id_tahun_ajaran)
            ->where('is_aktif', true)
            ->orderBy('nama_kelas')
            ->get(['nama_kelas', 'kapasitas', 'id_tingkat', 'id_jurusan']);

        if ($sumber->isEmpty()) {
            return back()->with('error', "Tidak ada kelas pada tahun ajaran {$sumberTahun->nama_tahun_ajaran} untuk diduplikat.");
        }

        $namaAda = DB::table('kelas')
            ->where('id_tahun_ajaran', $target->id_tahun_ajaran)
            ->pluck('nama_kelas')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->all();

        $jumlah = 0;
        $dilewati = 0;

        try {
            DB::transaction(function () use ($sumber, $target, $sumberTahun, $namaAda, &$jumlah, &$dilewati) {
                foreach ($sumber as $k) {
                    $nama = trim((string) $k->nama_kelas);
                    $kunci = mb_strtolower($nama);

                    if (in_array($kunci, $namaAda, true)) {
                        $dilewati++;

                        continue;
                    }

                    $id = DB::table('kelas')->insertGetId([
                        'nama_kelas' => $nama,
                        'kapasitas' => $k->kapasitas,
                        'id_tahun_ajaran' => $target->id_tahun_ajaran,
                        'id_tingkat' => $k->id_tingkat,
                        'id_jurusan' => $k->id_jurusan,
                        'is_aktif' => true,
                    ], 'id_kelas');

                    $namaAda[] = $kunci;
                    $jumlah++;

                    AuditCatat::tulis('kelas', 'INSERT', $id, null, [
                        'nama_kelas' => $nama,
                        'id_tahun_ajaran' => $target->id_tahun_ajaran,
                        'id_tingkat' => $k->id_tingkat,
                        'id_jurusan' => $k->id_jurusan,
                        'duplikat_dari' => $sumberTahun->nama_tahun_ajaran,
                    ]);
                }
            });
        } catch (QueryException $e) {
            return back()->with('error', 'Duplikat kelas gagal disimpan. Coba muat ulang halaman lalu ulangi.');
        }

        $pesan = "{$jumlah} kelas diduplikat dari tahun ajaran {$sumberTahun->nama_tahun_ajaran}.";
        if ($dilewati > 0) {
            $pesan .= " {$dilewati} kelas dilewati karena namanya sudah ada.";
        }

        return back()->with('success', $pesan);
    }

    /** Buat struktur kelas standar (X–XII, MIA & IIS, 1–20) pada tahun ajaran terpilih. */
    public function buatOtomatis(Request $r, KelasMassalService $service)
    {
        $d = $r->validate([
            'id_tahun_ajaran' => 'required|integer|exists:tahun_ajaran,id_tahun_ajaran',
            'id_semester' => 'nullable|integer|exists:semester,id_semester',
        ]);

        $hasil = $service->buat((int) $d['id_tahun_ajaran']);

        $pesan = "{$hasil['dibuat']} kelas berhasil dibuat";
        if ($hasil['dilewati'] > 0) {
            $pesan .= ", {$hasil['dilewati']} kelas dilewati karena sudah ada";
        }
        $pesan .= ". Total kelas standar: {$hasil['total']}.";

        return redirect()
            ->route('tu.kelas', array_filter(['semester' => $d['id_semester'] ?? null]))
            ->with('success', $pesan);
    }

    public function detail(Request $r, int $id)
    {
        [$kelas, $semesters, $sem, $wali, $siswa, $cari] = $this->dataDetail($r, $id);

        return view('tu.kelas-detail', compact('kelas', 'semesters', 'sem', 'wali', 'siswa', 'cari'));
    }

    public function unduh(Request $r, int $id)
    {
        [$kelas, , $sem, , $siswa] = $this->dataDetail($r, $id, true);
        abort_unless($sem, 404);

        $aman = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v;   // cegah formula Excel
        $nama = 'kelas-'.preg_replace('/[^A-Za-z0-9]+/', '-', $kelas->nama_kelas).'.csv';

        return response()->streamDownload(function () use ($siswa, $aman) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No', 'Nama', 'NIS', 'NISN']);
            foreach ($siswa as $i => $s) {
                fputcsv($out, [$i + 1, $aman($s->nama_lengkap), $aman($s->nis), $aman($s->nisn)]);
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ---------------- helper ---------------- */

    private function daftarSemester()
    {
        return DB::table('semester as s')
            ->join('tahun_ajaran as t', 't.id_tahun_ajaran', '=', 's.id_tahun_ajaran')
            ->orderByDesc('t.tanggal_mulai')->orderByDesc('s.tanggal_mulai')
            ->get(['s.id_semester', 's.nama_semester', 's.is_aktif', 't.id_tahun_ajaran', 't.nama_tahun_ajaran']);
    }

    /** Semester dari ?semester=, jika tidak ada pakai yang aktif, lalu yang terbaru. */
    private function pilihSemester(Request $r, $semesters)
    {
        $dipilih = (int) $r->input('semester', 0);

        return $semesters->firstWhere('id_semester', $dipilih)
            ?? $semesters->firstWhere('is_aktif', true)
            ?? $semesters->first();
    }

    private function daftarKelas($sem, ?string $filterTingkat = null, ?string $filterJurusan = null)
    {
        return DB::table('kelas as k')
            ->leftJoin('tingkat as t', 't.id_tingkat', '=', 'k.id_tingkat')
            ->leftJoin('jurusan as j', 'j.id_jurusan', '=', 'k.id_jurusan')
            ->leftJoin('tahun_ajaran as ta', 'ta.id_tahun_ajaran', '=', 'k.id_tahun_ajaran')
            ->leftJoin('penugasan_wali_kelas as pw', function ($j) use ($sem) {
                $j->on('pw.id_kelas', '=', 'k.id_kelas')->where('pw.id_semester', '=', $sem->id_semester);
            })
            ->leftJoin('guru as g', 'g.id_guru', '=', 'pw.id_guru')
            ->where('k.is_aktif', true)
            ->where(fn ($q) => $q->where('k.id_tahun_ajaran', $sem->id_tahun_ajaran)->orWhereNull('k.id_tahun_ajaran'))
            ->when($filterTingkat, fn ($q, $tingkat) => $q->where('t.nama_tingkat', $tingkat))
            ->when($filterJurusan, fn ($q, $jurusan) => $q->where('j.kode_jurusan', $jurusan))
            ->selectRaw("k.id_kelas, k.nama_kelas, k.kapasitas, k.id_tahun_ajaran, k.id_tingkat, k.id_jurusan,
                ta.nama_tahun_ajaran, t.nama_tingkat, t.urutan as urutan_tingkat, j.nama_jurusan, j.kode_jurusan,
                pw.id_penugasan_wali_kelas, g.id_guru, g.nama_guru, r.nama_role as role_wali,
                (select count(*) from keanggotaan_kelas kk where kk.id_kelas = k.id_kelas and kk.id_semester = ? and kk.status = 'aktif') as jumlah",
                [$sem->id_semester])
            ->leftJoin('pengguna as p', 'p.id_pengguna', '=', 'g.id_pengguna')
            ->leftJoin('role as r', 'r.id_role', '=', 'p.id_role')
            ->get()
            ->sortBy(function ($kelas) {
                $urutanJurusan = match ($kelas->kode_jurusan) {
                    'MIA' => 0,
                    'IIS' => 1,
                    default => 99,
                };

                return sprintf(
                    '%02d-%02d-%02d',
                    $kelas->urutan_tingkat ?? 99,
                    $urutanJurusan,
                    (int) preg_replace('/^.*\s/', '', $kelas->nama_kelas)
                );
            })
            ->values();
    }

    /** Semester yang cocok untuk tahun ajaran terpilih (utamakan semester dari halaman, lalu yang aktif). */
    private function semesterUntukTahun(int $idTahunAjaran, int $idSemester = 0)
    {
        if ($idSemester > 0) {
            $sem = DB::table('semester')->where('id_semester', $idSemester)->first();
            if ($sem && (int) $sem->id_tahun_ajaran === $idTahunAjaran) {
                return $sem;
            }
        }

        return DB::table('semester')
            ->where('id_tahun_ajaran', $idTahunAjaran)
            ->orderByDesc('is_aktif')
            ->orderBy('tanggal_mulai')
            ->first();
    }

    /** Tetapkan / ganti / cabut wali kelas pada satu semester tanpa mengganggu data lain. */
    private function terapkanWali(int $idKelas, ?int $idGuru, int $idSemester): void
    {
        $ada = DB::table('penugasan_wali_kelas')
            ->where('id_kelas', $idKelas)->where('id_semester', $idSemester)
            ->lockForUpdate()->first();

        if ($idGuru === null) {
            if ($ada) {
                DB::table('penugasan_wali_kelas')
                    ->where('id_penugasan_wali_kelas', $ada->id_penugasan_wali_kelas)->delete();
            }

            return;
        }

        if ($ada) {
            if ((int) $ada->id_guru !== $idGuru) {
                DB::table('penugasan_wali_kelas')
                    ->where('id_penugasan_wali_kelas', $ada->id_penugasan_wali_kelas)
                    ->update(['id_guru' => $idGuru, 'ditugaskan_pada' => now()]);
            }

            return;
        }

        DB::table('penugasan_wali_kelas')->insert([
            'id_kelas' => $idKelas,
            'id_guru' => $idGuru,
            'id_semester' => $idSemester,
            'ditugaskan_pada' => now(),
        ]);
    }

    private function waliKelasAktif(int $idGuru): ?object
    {
        return DB::table('guru as g')
            ->join('pengguna as p', 'p.id_pengguna', '=', 'g.id_pengguna')
            ->join('role as r', 'r.id_role', '=', 'p.id_role')
            ->where('g.id_guru', $idGuru)
            ->where('g.is_aktif', true)
            ->where('p.is_aktif', true)
            ->whereIn('r.nama_role', ['guru', 'wali_kelas'])
            ->first(['g.id_guru', 'g.nama_guru']);
    }

    private function dataDetail(Request $r, int $id, bool $semuaSiswa = false): array
    {
        $kelas = DB::table('kelas as k')
            ->leftJoin('tingkat as t', 't.id_tingkat', '=', 'k.id_tingkat')
            ->leftJoin('jurusan as j', 'j.id_jurusan', '=', 'k.id_jurusan')
            ->where('k.id_kelas', $id)
            ->first(['k.id_kelas', 'k.nama_kelas', 'k.kapasitas', 't.nama_tingkat', 'j.nama_jurusan', 'j.kode_jurusan']);
        abort_unless($kelas, 404);

        $semesters = $this->daftarSemester();
        $sem = $this->pilihSemester($r, $semesters);
        $cari = $semuaSiswa ? '' : trim((string) $r->query('cari', ''));
        $like = '%'.addcslashes($cari, '%_\\').'%';

        $wali = $sem ? DB::table('penugasan_wali_kelas as pw')->join('guru as g', 'g.id_guru', '=', 'pw.id_guru')
            ->where('pw.id_kelas', $id)->where('pw.id_semester', $sem->id_semester)->value('g.nama_guru') : null;

        $siswa = $sem ? DB::table('keanggotaan_kelas as kk')->join('siswa as s', 's.id_siswa', '=', 'kk.id_siswa')
            ->where('kk.id_kelas', $id)->where('kk.id_semester', $sem->id_semester)->where('kk.status', 'aktif')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('s.nama_lengkap', 'ilike', $like)->orWhere('s.nisn', 'ilike', $like)->orWhere('s.nis', 'ilike', $like)))
            ->orderBy('s.nama_lengkap')
            ->get(['s.nama_lengkap', 's.nis', 's.nisn']) : collect();

        return [$kelas, $semesters, $sem, $wali, $siswa, $cari];
    }
}
