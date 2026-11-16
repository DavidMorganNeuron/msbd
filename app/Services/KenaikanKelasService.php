<?php

namespace App\Services;

use App\Support\AuditCatat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pemetaan dan proses kenaikan kelas massal untuk seluruh kelas pada satu tahun ajaran.
 * Tujuan otomatis: tingkat berikutnya, jurusan sama, rombel sama, pada semester ganjil tahun ajaran berikutnya.
 */
class KenaikanKelasService
{
    public function tahunAjaran(): Collection
    {
        return DB::table('tahun_ajaran')
            ->orderByDesc('tanggal_mulai')
            ->get(['id_tahun_ajaran', 'nama_tahun_ajaran', 'tanggal_mulai', 'tanggal_selesai', 'is_aktif']);
    }

    public function tahunAjaranAktifId(): ?int
    {
        $id = DB::table('tahun_ajaran')->where('is_aktif', true)->orderByDesc('tanggal_mulai')->value('id_tahun_ajaran');

        return $id !== null
            ? (int) $id
            : (($x = DB::table('tahun_ajaran')->orderByDesc('tanggal_mulai')->value('id_tahun_ajaran')) !== null ? (int) $x : null);
    }

    public function pratinjau(int $idTahunAjaranAsal): array
    {
        $asal = DB::table('tahun_ajaran')->where('id_tahun_ajaran', $idTahunAjaranAsal)->first();
        if (! $asal) {
            throw ValidationException::withMessages(['id_tahun_ajaran' => 'Tahun ajaran asal tidak ditemukan.']);
        }

        $tujuan = DB::table('tahun_ajaran')
            ->where('tanggal_mulai', '>', $asal->tanggal_mulai)
            ->orderBy('tanggal_mulai')
            ->first();

        $semesterTujuan = $tujuan ? $this->semesterGanjil((int) $tujuan->id_tahun_ajaran) : null;

        $peringatan = [];
        if (! $tujuan) {
            $peringatan[] = 'Tahun ajaran berikutnya belum ada. Tambahkan tahun ajaran dan semester ganjil-nya di Konfigurasi Akademik.';
        } elseif (! $semesterTujuan) {
            $peringatan[] = 'Semester ganjil pada tahun ajaran '.$tujuan->nama_tahun_ajaran.' belum ada.';
        }

        $kelasAsal = DB::table('kelas as k')
            ->leftJoin('tingkat as t', 't.id_tingkat', '=', 'k.id_tingkat')
            ->leftJoin('jurusan as j', 'j.id_jurusan', '=', 'k.id_jurusan')
            ->where('k.id_tahun_ajaran', $asal->id_tahun_ajaran)
            ->where('k.is_aktif', true)
            ->orderByRaw('COALESCE(t.urutan, 99)')
            ->orderBy('k.nama_kelas')
            ->get(['k.id_kelas', 'k.nama_kelas', 'k.id_tingkat', 'k.id_jurusan', 't.nama_tingkat', 't.urutan', 'j.kode_jurusan']);

        $baris = [];
        $jumlahSiswa = 0;

        foreach ($kelasAsal as $k) {
            $tingkatAkhir = $this->tingkatBerikutnya($k->urutan) === null;
            $tujuanNaik = $tujuan ? $this->cocokKelas($k, (int) $tujuan->id_tahun_ajaran, true) : null;
            $tujuanTinggal = $tujuan ? $this->cocokKelas($k, (int) $tujuan->id_tahun_ajaran, false) : null;
            $siswa = $this->siswaAktifKelas((int) $k->id_kelas, (int) $asal->id_tahun_ajaran);
            $jumlahSiswa += $siswa->count();

            if ($tujuan && ! $tingkatAkhir && ! $tujuanNaik) {
                $peringatan[] = 'Kelas tujuan untuk '.$k->nama_kelas.' belum ada pada tahun ajaran '.$tujuan->nama_tahun_ajaran.'.';
            }

            $baris[] = [
                'kelas_asal' => [
                    'id' => (int) $k->id_kelas,
                    'nama' => $k->nama_kelas,
                    'tingkat' => $k->nama_tingkat,
                    'urutan' => $k->urutan,
                    'kode_jurusan' => $k->kode_jurusan,
                ],
                'kelas_tujuan' => $tujuanNaik ? [
                    'id' => (int) $tujuanNaik->id_kelas,
                    'nama' => $tujuanNaik->nama_kelas,
                ] : null,
                'kelas_tujuan_tinggal' => $tujuanTinggal ? [
                    'id' => (int) $tujuanTinggal->id_kelas,
                    'nama' => $tujuanTinggal->nama_kelas,
                ] : null,
                'tingkat_akhir' => $tingkatAkhir,
                'siswa' => $siswa,
            ];
        }

        return [
            'tahun_asal' => $asal,
            'tahun_tujuan' => $tujuan,
            'semester_tujuan' => $semesterTujuan,
            'baris' => $baris,
            'jumlah_siswa' => $jumlahSiswa,
            'peringatan' => $peringatan,
        ];
    }

    /**
     * @param  array<int,int|string>  $idSiswaNaik
     */
    public function proses(
        int $idTahunAjaranAsal,
        array $idSiswaNaik,
        ?string $tingkat = null,
        ?string $program = null
    ): array {
        $rencana = $this->pratinjau($idTahunAjaranAsal);
        $rencana['baris'] = array_values(array_filter($rencana['baris'], fn (array $baris) => (! $tingkat || $baris['kelas_asal']['tingkat'] === $tingkat)
            && (! $program || $baris['kelas_asal']['kode_jurusan'] === $program)
        ));

        if (! $rencana['tahun_tujuan'] || ! $rencana['semester_tujuan']) {
            throw ValidationException::withMessages([
                'id_tahun_ajaran' => 'Tahun ajaran berikutnya beserta semester ganjilnya belum tersedia. Lengkapi di Konfigurasi Akademik.',
            ]);
        }

        $idSiswaNaik = array_map('intval', $idSiswaNaik);
        $naikSet = array_flip($idSiswaNaik);
        $idSemesterTujuan = (int) $rencana['semester_tujuan']->id_semester;
        $tanggalTujuan = $rencana['semester_tujuan']->tanggal_mulai
            ? Carbon::parse($rencana['semester_tujuan']->tanggal_mulai)->toDateString()
            : now()->toDateString();

        foreach ($rencana['baris'] as $baris) {
            foreach ($baris['siswa'] as $s) {
                $idSiswa = (int) ($s->id_siswa ?? $s['id_siswa']);
                $naik = isset($naikSet[$idSiswa]);

                if ($baris['tingkat_akhir'] && $naik) {
                    continue;
                }

                $tujuanKelas = $naik ? $baris['kelas_tujuan'] : $baris['kelas_tujuan_tinggal'];
                if (! $tujuanKelas) {
                    throw ValidationException::withMessages([
                        'kelas_tujuan' => 'Kelas tujuan untuk siswa '.($s->nama_lengkap ?? $s['nama_lengkap'] ?? $idSiswa).' belum tersedia. Proses kenaikan dibatalkan.',
                    ]);
                }
            }
        }

        $hasil = ['naik' => 0, 'tidak_naik' => 0, 'lulus' => 0, 'tanpa_tujuan' => 0];

        DB::transaction(function () use ($rencana, $naikSet, $idSemesterTujuan, $tanggalTujuan, &$hasil) {
            foreach ($rencana['baris'] as $baris) {
                foreach ($baris['siswa'] as $s) {
                    $idSiswa = (int) ($s->id_siswa ?? $s['id_siswa']);
                    $naik = isset($naikSet[$idSiswa]);

                    $tanggalMulai = $s->tanggal_mulai ?? ($s['tanggal_mulai'] ?? null);
                    $tanggalSelesai = $tanggalTujuan;
                    if ($tanggalMulai && $tanggalSelesai < $tanggalMulai) {
                        $tanggalSelesai = $tanggalMulai;
                    }

                    if ($baris['tingkat_akhir'] && $naik) {
                        $this->tutupKeanggotaan((int) ($s->id_keanggotaan ?? $s['id_keanggotaan']), 'lulus', $tanggalSelesai);
                        $hasil['lulus']++;

                        continue;
                    }

                    $tujuanKelas = $naik ? $baris['kelas_tujuan'] : $baris['kelas_tujuan_tinggal'];

                    $this->tutupKeanggotaan((int) ($s->id_keanggotaan ?? $s['id_keanggotaan']), $naik ? 'naik_kelas' : 'tidak_naik_kelas', $tanggalSelesai);
                    $this->bukaKeanggotaan($idSiswa, (int) $tujuanKelas['id'], $idSemesterTujuan, $tanggalTujuan);

                    $hasil[$naik ? 'naik' : 'tidak_naik']++;
                }
            }
        });

        return $hasil;
    }

    private function tutupKeanggotaan(int $idKeanggotaan, string $status, string $tanggalSelesai): void
    {
        DB::table('keanggotaan_kelas')
            ->where('id_keanggotaan', $idKeanggotaan)
            ->update(['status' => $status, 'tanggal_selesai' => $tanggalSelesai]);

        AuditCatat::tulis('keanggotaan_kelas', 'UPDATE', $idKeanggotaan, null, ['status' => $status, 'tanggal_selesai' => $tanggalSelesai]);
    }

    private function bukaKeanggotaan(int $idSiswa, int $idKelas, int $idSemester, string $tanggalMulai): void
    {
        $ada = DB::table('keanggotaan_kelas')
            ->where('id_siswa', $idSiswa)
            ->where('id_semester', $idSemester)
            ->where('status', 'aktif')
            ->orderByDesc('tanggal_mulai')
            ->lockForUpdate()
            ->first();

        if ($ada) {
            DB::table('keanggotaan_kelas')->where('id_keanggotaan', $ada->id_keanggotaan)->update([
                'id_kelas' => $idKelas,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => null,
                'status' => 'aktif',
            ]);
            AuditCatat::tulis('keanggotaan_kelas', 'UPDATE', (int) $ada->id_keanggotaan, null, [
                'id_kelas' => $idKelas, 'id_semester' => $idSemester, 'status' => 'aktif', 'sumber' => 'kenaikan',
            ]);

            return;
        }

        $id = DB::table('keanggotaan_kelas')->insertGetId([
            'id_siswa' => $idSiswa,
            'id_kelas' => $idKelas,
            'id_semester' => $idSemester,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => null,
            'status' => 'aktif',
        ], 'id_keanggotaan');

        AuditCatat::tulis('keanggotaan_kelas', 'INSERT', $id, null, [
            'id_siswa' => $idSiswa, 'id_kelas' => $idKelas, 'id_semester' => $idSemester, 'status' => 'aktif', 'sumber' => 'kenaikan',
        ]);
    }

    private function semesterGanjil(int $idTahunAjaran): ?object
    {
        return DB::table('semester')
            ->where('id_tahun_ajaran', $idTahunAjaran)
            ->whereRaw('LOWER(nama_semester) LIKE ?', ['%ganjil%'])
            ->orderBy('tanggal_mulai')
            ->first()
            ?? DB::table('semester')
                ->where('id_tahun_ajaran', $idTahunAjaran)
                ->orderBy('tanggal_mulai')
                ->first();
    }

    private function tingkatBerikutnya(?int $urutan): ?object
    {
        if ($urutan === null) {
            return null;
        }

        return DB::table('tingkat')->where('urutan', '>', $urutan)->orderBy('urutan')->first(['id_tingkat', 'nama_tingkat', 'urutan']);
    }

    private function cocokKelas(object $kelasAsal, int $idTahunTujuan, bool $naik): ?object
    {
        $tingkat = $naik
            ? $this->tingkatBerikutnya($kelasAsal->urutan)
            : (object) ['id_tingkat' => $kelasAsal->id_tingkat];

        if (! $tingkat) {
            return null;
        }

        $q = DB::table('kelas')
            ->where('id_tahun_ajaran', $idTahunTujuan)
            ->where('id_tingkat', $tingkat->id_tingkat)
            ->where('is_aktif', true);

        if ($kelasAsal->id_jurusan !== null) {
            $q->where('id_jurusan', $kelasAsal->id_jurusan);
        }

        $kandidat = $q->get(['id_kelas', 'nama_kelas', 'id_tingkat', 'id_jurusan']);

        if ($kandidat->isEmpty()) {
            return null;
        }

        $rombel = $this->rombel($kelasAsal->nama_kelas);
        if ($rombel !== null) {
            $match = $kandidat->first(fn ($c) => $this->rombel($c->nama_kelas) === $rombel);
            if ($match) {
                return $match;
            }

            return null;
        }

        return $kandidat->first();
    }

    private function rombel(?string $nama): ?int
    {
        return preg_match('/(\d+)\s*$/', trim((string) $nama), $m) ? (int) $m[1] : null;
    }

    private function siswaAktifKelas(int $idKelas, int $idTahunAjaran): Collection
    {
        return DB::table('keanggotaan_kelas as kk')
            ->join('siswa as s', 's.id_siswa', '=', 'kk.id_siswa')
            ->join('semester as sm', 'sm.id_semester', '=', 'kk.id_semester')
            ->where('kk.id_kelas', $idKelas)
            ->where('sm.id_tahun_ajaran', $idTahunAjaran)
            ->where('kk.status', 'aktif')
            ->orderByRaw('sm.tanggal_mulai DESC NULLS LAST')
            ->orderByDesc('kk.id_keanggotaan')
            ->get(['kk.id_keanggotaan', 's.id_siswa', 's.nama_lengkap', 's.nisn', 'kk.id_semester', 'kk.tanggal_mulai'])
            ->unique('id_siswa')
            ->sortBy('nama_lengkap')
            ->values();
    }
}
