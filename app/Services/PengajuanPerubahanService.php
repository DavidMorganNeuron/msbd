<?php

namespace App\Services;

use App\Models\PengajuanPerubahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengajuanPerubahanService
{
    public function waliKelasMenanganiSiswa(int $idPengguna, int $idSiswa): bool
    {
        $semester = DB::table('semester')
            ->where('is_aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id_semester')
            ->first(['id_semester']);

        if (! $semester) {
            return false;
        }

        $idGuru = DB::table('guru')
            ->where('id_pengguna', $idPengguna)
            ->where('is_aktif', true)
            ->value('id_guru');

        if (! $idGuru) {
            return false;
        }

        return DB::table('penugasan_wali_kelas as pw')
            ->join('keanggotaan_kelas as kk', function ($join) use ($semester) {
                $join->on('kk.id_kelas', '=', 'pw.id_kelas')
                    ->where('kk.id_semester', '=', $semester->id_semester)
                    ->where('kk.status', '=', 'aktif');
            })
            ->where('pw.id_guru', $idGuru)
            ->where('pw.id_semester', $semester->id_semester)
            ->where('kk.id_siswa', $idSiswa)
            ->exists();
    }

    public function ajukan(int $idSiswa, int $idPeriode, string $alasan, array $detail): PengajuanPerubahan
    {
        if (count($detail) === 0) {
            throw ValidationException::withMessages([
                'detail' => 'Minimal satu detail perubahan harus dikirim.',
            ]);
        }

        return DB::transaction(function () use ($idSiswa, $idPeriode, $alasan, $detail) {
            $p = PengajuanPerubahan::create([
                'id_siswa' => $idSiswa,
                'id_periode_update' => $idPeriode,
                'alasan' => $alasan,
                'status_pengajuan' => 'menunggu',
                'tgl_pengajuan' => now(),
            ]);

            foreach ($detail as $d) {
                $nama = trim((string) ($d['nama_field'] ?? ''));

                $ref = DB::table('ref_field_data')
                    ->where('nama_kolom', $nama)
                    ->first(['nama_kolom', 'boleh_diubah_siswa']);

                if (! $ref) {
                    $ref = DB::table('ref_field_data')
                        ->whereRaw('lower(label_field) = ?', [mb_strtolower($nama)])
                        ->orderBy('id_field')
                        ->first(['nama_kolom', 'boleh_diubah_siswa']);
                }

                if (! $ref || ! $ref->boleh_diubah_siswa) {
                    throw ValidationException::withMessages(['detail' => 'Field tidak diizinkan']);
                }

                $p->detail()->create([
                    'nama_field' => $ref->nama_kolom,
                    'nilai_lama' => $d['nilai_lama'] ?? null,
                    'nilai_baru' => $d['nilai_baru'] ?? null,
                    'bukti_path' => $d['bukti_path'] ?? null,
                ]);
            }

            return $p->load('detail');
        }, 3);
    }

    public function putuskan(int $id, int $petugas, string $keputusan, ?string $catatan = null): void
    {
        if (! in_array($keputusan, ['disetujui', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status' => 'Keputusan tidak valid']);
        }

        DB::transaction(function () use ($id, $petugas, $keputusan, $catatan) {
            $p = PengajuanPerubahan::query()->lockForUpdate()->findOrFail($id);

            if ($p->status_pengajuan !== 'menunggu') {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah diproses']);
            }

            if ($keputusan === 'disetujui') {
                DB::statement('CALL sp_setujui_pengajuan(?, ?, ?)', [$id, $petugas, $catatan]);

                return;
            }

            $p->update([
                'status_pengajuan' => $keputusan,
                'id_pemeriksa' => $petugas,
                'catatan_pemeriksa' => $catatan,
                'diputuskan_pada' => now(),
            ]);
        }, 3);
    }
}
