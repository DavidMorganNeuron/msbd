<?php

namespace App\Services;

use App\Support\AuditCatat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuat struktur kelas standar SMA: tingkat X, XI, XII dengan jurusan MIA dan IIS,
 * masing-masing 20 rombel (total 120 kelas) untuk satu tahun ajaran.
 * Proses idempoten: kelas yang namanya sudah ada pada tahun ajaran tersebut dilewati.
 */
class KelasMassalService
{
    /** @var list<string> */
    public const TINGKAT = ['X', 'XI', 'XII'];

    /** @var list<string> */
    public const JURUSAN = ['MIA', 'IIS'];

    public const JUMLAH_ROMBEL = 20;

    public const KAPASITAS = 32;

    /**
     * @return array{dibuat: int, dilewati: int, total: int}
     */
    public function buat(int $idTahunAjaran): array
    {
        $tahun = DB::table('tahun_ajaran')->where('id_tahun_ajaran', $idTahunAjaran)->first();
        if (! $tahun) {
            throw ValidationException::withMessages([
                'id_tahun_ajaran' => 'Tahun ajaran tidak ditemukan.',
            ]);
        }

        $tingkat = DB::table('tingkat')->pluck('id_tingkat', 'nama_tingkat');
        $jurusan = DB::table('jurusan')->pluck('id_jurusan', 'kode_jurusan');

        $referensiHilang = collect(self::TINGKAT)->reject(fn (string $nama) => $tingkat->has($nama))
            ->merge(collect(self::JURUSAN)->reject(fn (string $kode) => $jurusan->has($kode)));

        if ($referensiHilang->isNotEmpty()) {
            throw ValidationException::withMessages([
                'id_tahun_ajaran' => 'Data referensi belum lengkap: '.$referensiHilang->join(', ').'. Tambahkan di Data Master terlebih dahulu.',
            ]);
        }

        $namaAda = DB::table('kelas')
            ->where('id_tahun_ajaran', $idTahunAjaran)
            ->pluck('nama_kelas')
            ->map(fn ($nama) => mb_strtolower(trim((string) $nama)))
            ->all();

        $dibuat = 0;
        $dilewati = 0;

        DB::transaction(function () use ($idTahunAjaran, $tingkat, $jurusan, &$namaAda, &$dibuat, &$dilewati) {
            foreach (self::TINGKAT as $namaTingkat) {
                foreach (self::JURUSAN as $kodeJurusan) {
                    foreach (range(1, self::JUMLAH_ROMBEL) as $nomorKelas) {
                        $namaKelas = "{$namaTingkat} {$kodeJurusan} {$nomorKelas}";
                        $kunci = mb_strtolower($namaKelas);

                        if (in_array($kunci, $namaAda, true)) {
                            $dilewati++;

                            continue;
                        }

                        $id = DB::table('kelas')->insertGetId([
                            'nama_kelas' => $namaKelas,
                            'id_tahun_ajaran' => $idTahunAjaran,
                            'id_tingkat' => $tingkat[$namaTingkat],
                            'id_jurusan' => $jurusan[$kodeJurusan],
                            'kapasitas' => self::KAPASITAS,
                            'is_aktif' => true,
                        ], 'id_kelas');

                        $namaAda[] = $kunci;
                        $dibuat++;

                        AuditCatat::tulis('kelas', 'INSERT', $id, null, [
                            'nama_kelas' => $namaKelas,
                            'id_tahun_ajaran' => $idTahunAjaran,
                            'id_tingkat' => $tingkat[$namaTingkat],
                            'id_jurusan' => $jurusan[$kodeJurusan],
                            'kapasitas' => self::KAPASITAS,
                        ]);
                    }
                }
            }
        });

        return [
            'dibuat' => $dibuat,
            'dilewati' => $dilewati,
            'total' => count(self::TINGKAT) * count(self::JURUSAN) * self::JUMLAH_ROMBEL,
        ];
    }
}
