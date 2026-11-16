<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $primaryKey = 'id_kelas';

    public $timestamps = false;

    protected $guarded = ['id_kelas'];

    public function tingkat(): BelongsTo
    {
        return $this->belongsTo(Tingkat::class, 'id_tingkat', 'id_tingkat');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'id_jurusan', 'id_jurusan');
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_kelas', 'id_kelas');
    }

    public function keanggotaanKelas(): HasMany
    {
        return $this->hasMany(KeanggotaanKelas::class, 'id_kelas', 'id_kelas');
    }

    public function penugasanWaliKelas(): HasMany
    {
        return $this->hasMany(PenugasanWaliKelas::class, 'id_kelas', 'id_kelas');
    }

    /** Tingkat kelas berdasarkan nama, mis. "X MIA 1" menjadi "X". */
    public function tingkatLabel(): string
    {
        $namaKelas = trim((string) $this->nama_kelas);

        if (preg_match('/^(XII|XI|X)\b/i', $namaKelas, $bagian)) {
            return strtoupper($bagian[1]);
        }

        return strtoupper(strtok($namaKelas, ' ') ?: '');
    }

    /** Jurusan kelas berdasarkan nama, mis. "X MIA 1" menjadi "MIA". */
    public function jurusanLabel(): string
    {
        $namaKelas = trim((string) $this->nama_kelas);

        if (preg_match('/^(?:XII|XI|X)\s+([A-Za-z]+)/i', $namaKelas, $bagian)) {
            return strtoupper($bagian[1]);
        }

        return '';
    }

    /** Nomor rombel numerik di akhir nama kelas. */
    public function nomorKelas(): int
    {
        $namaKelas = trim((string) $this->nama_kelas);

        return preg_match('/(\d+)\s*$/', $namaKelas, $bagian)
            ? (int) $bagian[1]
            : 0;
    }

    /**
     * Urutkan kelas secara natural: tingkat X, XI, XII; lalu jurusan; lalu nomor rombel numerik.
     *
     * @param  Collection<int, self>  $daftarKelas
     * @return Collection<int, self>
     */
    public static function urutAlami(Collection $daftarKelas): Collection
    {
        $urutanTingkat = ['X' => 1, 'XI' => 2, 'XII' => 3];

        return $daftarKelas->sort(function (self $kelasPertama, self $kelasKedua) use ($urutanTingkat): int {
            $kunciPertama = [
                $urutanTingkat[$kelasPertama->tingkatLabel()] ?? 99,
                $kelasPertama->jurusanLabel(),
                $kelasPertama->nomorKelas(),
            ];
            $kunciKedua = [
                $urutanTingkat[$kelasKedua->tingkatLabel()] ?? 99,
                $kelasKedua->jurusanLabel(),
                $kelasKedua->nomorKelas(),
            ];

            return ($kunciPertama <=> $kunciKedua)
                ?: strnatcasecmp((string) $kelasPertama->nama_kelas, (string) $kelasKedua->nama_kelas);
        })->values();
    }
}
