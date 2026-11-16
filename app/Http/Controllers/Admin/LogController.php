<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\LogLogin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * DFD 1.5 Pantau Log Aktivitas (Kepala Sekolah): D26 Log Login, D25 Log Audit.
 * Hanya baca. Akses dibatasi middleware role:kepsek di routes/web.php.
 */
class LogController extends Controller
{
    private const ZONA = 'Asia/Jakarta';   // config app.timezone = UTC, jadi dikonversi saat tampil

    private const PER_HALAMAN = 25;

    public function login(Request $r)
    {
        $f = $r->validate([
            'cari' => 'nullable|string|max:100',
            'hasil' => 'nullable|in:berhasil,gagal',
            'dari' => 'nullable|date',
            'sampai' => 'nullable|date|after_or_equal:dari',
        ]);

        $log = LogLogin::query()
            ->when($f['cari'] ?? null, fn ($q, $v) => $q->where('username_dicoba', 'ilike', '%'.$this->escapeLike($v).'%'))
            ->when(($f['hasil'] ?? null) === 'berhasil', fn ($q) => $q->where('berhasil', true))
            ->when(($f['hasil'] ?? null) === 'gagal', fn ($q) => $q->where('berhasil', false))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('waktu', '>=', $this->awalHari($v)))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->where('waktu', '<', $this->awalHari($v)->addDay()))
            ->orderByDesc('waktu')->orderByDesc('id_log_login')
            ->paginate(self::PER_HALAMAN)->withQueryString()
            ->through(fn ($l) => [
                'waktu' => $this->tampilWaktu($l->waktu),
                'user' => $l->username_dicoba,
                'ok' => (bool) $l->berhasil,
                'ip' => $l->ip_address ?? '-',
                'perangkat' => $this->ringkasPerangkat($l->user_agent),
            ]);

        return view('admin.log-login', ['log' => $log, 'f' => $f]);
    }

    public function audit(Request $r)
    {
        $daftarTabel = LogAudit::query()->distinct()->orderBy('nama_tabel')->pluck('nama_tabel')
            ->groupBy(fn (string $tabel) => $this->namaTabel($tabel))
            ->map(fn ($tabel) => $tabel->values()->all())
            ->mapWithKeys(fn (array $tabel, string $label) => [Str::slug($label) => ['label' => $label, 'tabel' => $tabel]]);

        $f = $r->validate([
            'cari' => 'nullable|string|max:100',
            'tabel' => ['nullable', 'string', Rule::in($daftarTabel->keys()->all())],
            'aksi' => 'nullable|in:INSERT,UPDATE,DELETE',
            'dari' => 'nullable|date',
            'sampai' => 'nullable|date|after_or_equal:dari',
        ]);

        $log = LogAudit::query()
            ->when($f['cari'] ?? null, fn ($q, $v) => $q->where('username_pengubah', 'ilike', '%'.$this->escapeLike($v).'%'))
            ->when($f['tabel'] ?? null, fn ($q, $v) => $q->whereIn('nama_tabel', $daftarTabel[$v]['tabel']))
            ->when($f['aksi'] ?? null, fn ($q, $v) => $q->where('operasi', $v))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('waktu', '>=', $this->awalHari($v)))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->where('waktu', '<', $this->awalHari($v)->addDay()))
            ->orderByDesc('waktu')->orderByDesc('id_log')
            ->paginate(self::PER_HALAMAN)->withQueryString()
            ->through(fn (LogAudit $entry) => [
                'username' => $entry->username_pengubah ?? 'Pengguna tidak diketahui',
                'waktu' => $entry->waktu ? Carbon::parse($entry->waktu)->timezone(self::ZONA)->locale('id')->translatedFormat('d M Y H:i') : '-',
                'aktivitas' => $this->pesanAudit($entry),
            ]);

        return view('admin.audit', ['log' => $log, 'f' => $f, 'daftarTabel' => $daftarTabel]);
    }

    /* ---------------- helper ---------------- */

    private function escapeLike(string $v): string
    {
        return addcslashes($v, '%_\\');
    }

    private function awalHari(string $tanggal): Carbon
    {
        return Carbon::parse($tanggal, self::ZONA)->startOfDay()->utc();
    }

    private function tampilWaktu($waktu): string
    {
        return Carbon::parse($waktu)->timezone(self::ZONA)->locale('id')->translatedFormat('d M Y H:i');
    }

    private function pesanAudit(LogAudit $entry): string
    {
        if ($entry->nama_tabel === 'mutasi_siswa') {
            $baru = $entry->nilai_baru ?? [];
            $lama = $entry->nilai_lama ?? [];
            $jenis = $this->jenisMutasi((string) ($baru['jenis'] ?? $lama['jenis'] ?? ''));
            $nama = trim((string) ($baru['nama_siswa'] ?? ''));
            $siswa = $nama !== '' ? ' untuk '.$nama : ' siswa';

            if ($entry->operasi === 'INSERT') {
                if (($baru['status'] ?? null) === 'disetujui') {
                    return 'Mengajukan dan menyetujui mutasi '.$jenis.$siswa;
                }

                return 'Mengajukan mutasi '.$jenis.$siswa;
            }

            if (($baru['status'] ?? null) === 'disetujui') {
                return 'Menyetujui pengajuan mutasi '.$jenis.$siswa;
            }

            if (($baru['status'] ?? null) === 'ditolak') {
                return 'Menolak pengajuan mutasi '.$jenis.$siswa;
            }

            if (($baru['status'] ?? null) === 'dibatalkan') {
                return 'Membatalkan pengajuan mutasi '.$jenis.$siswa;
            }

            return 'Memperbarui pengajuan mutasi '.$jenis.$siswa;
        }

        $kataKerja = match ($entry->operasi) {
            'INSERT' => 'Menambahkan data',
            'UPDATE' => 'Memperbarui data',
            'DELETE' => 'Menghapus data',
            default => 'Mengubah data',
        };

        return $kataKerja.' '.$this->namaTabel($entry->nama_tabel);
    }

    private function namaTabel(?string $tabel): string
    {
        return match ($tabel) {
            'pengguna' => 'pengguna',
            'guru' => 'wali kelas',
            'staf' => 'staf sekolah',
            'siswa' => 'siswa',
            'kelas' => 'kelas',
            'keanggotaan_kelas' => 'anggota kelas',
            'jadwal_pelajaran' => 'jadwal pelajaran',
            'nilai_siswa', 'detail_rapor', 'rapor' => 'rapor siswa',
            'sesi_absensi', 'detail_absensi' => 'kehadiran siswa',
            'pendaftaran_ppdb', 'ppdb_alamat', 'ppdb_orang_tua' => 'pendaftaran siswa baru',
            'pengajuan_perubahan', 'detail_pengajuan' => 'pengajuan perubahan data siswa',
            'mutasi_siswa' => 'mutasi siswa',
            'pengaturan_sistem' => 'pengaturan sistem',
            'sekolah' => 'data sekolah',
            'provinsi', 'kota', 'kecamatan', 'kelurahan' => 'wilayah',
            default => 'data sekolah',
        };
    }

    private function jenisMutasi(string $jenis): string
    {
        return match ($jenis) {
            'pindah_masuk' => 'Pindah Masuk',
            'pindah_keluar' => 'Pindah Keluar',
            'mengundurkan_diri' => 'Mengundurkan Diri',
            'dikeluarkan' => 'Dikeluarkan',
            'putus_sekolah' => 'Putus Sekolah',
            'meninggal_dunia' => 'Meninggal Dunia',
            default => 'siswa',
        };
    }

    /** Return a device or operating-system family without exposing the browser name. */
    private function ringkasPerangkat(?string $ua): string
    {
        if (! $ua) {
            return '-';
        }
        if (preg_match('/\b(iPhone|iPad|iPod)\b/i', $ua, $matches)) {
            return match (strtolower($matches[1])) {
                'iphone' => 'iPhone',
                'ipad' => 'iPad',
                default => 'iPod',
            };
        }

        if (preg_match('/\b(ThinkPad|ROG Strix [A-Za-z0-9-]+)/i', $ua, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Android\s[\d.]+;\s*([^;)]+)/i', $ua, $matches)) {
            $model = trim(preg_replace('/\s+Build.*$/i', '', $matches[1]));

            return $model !== '' && ! in_array(strtolower($model), ['mobile', 'tablet', 'k'], true)
                ? 'Android · '.$model
                : 'Android';
        }

        return match (true) {
            str_contains($ua, 'Windows') => 'Windows PC',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux PC',
            default => 'Perangkat lain',
        };
    }
}
