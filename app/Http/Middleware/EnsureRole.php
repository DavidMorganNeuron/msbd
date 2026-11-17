<?php

namespace App\Http\Middleware;

use App\Models\Pengguna;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aturan: belum login ATAU peran tidak diizinkan -> halaman 404,
 * supaya orang yang mengetik URL manual tidak tahu halaman itu ada.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $idPengguna = $request->session()->get('id_pengguna');
        $username = $request->session()->get('username');

        // Belum login
        abort_unless($idPengguna, 404);

        // Cocokkan identitas sesi selain ID agar sesi usang tidak mengambil akun lain.
        $query = Pengguna::with('role')->where('id_pengguna', $idPengguna);

        if ($username) {
            $query->where('username', $username);
        }

        $user = $query->first();

        // Akun sudah dihapus atau dinonaktifkan setelah login: keluarkan dengan pesan
        if (! $user || ! $user->is_aktif || ! $user->role) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Akun tidak ditemukan atau tidak aktif. Silakan login kembali.']);
        }

        // Peran tidak diizinkan
        abort_unless(in_array($user->role->kode(), $roles, true), 404);

        $request->setUserResolver(static fn (?string $guard = null): Pengguna => $user);

        $databaseRole = match ($user->role->kode()) {
            'siswa' => 'role_siswa',
            'wali_kelas' => 'role_wali_kelas',
            'tu' => 'role_tu',
            'kepsek' => 'role_kepsek',
            default => null,
        };

        if ($databaseRole === null) {
            throw new LogicException('Peran aplikasi tidak memiliki role PostgreSQL yang sesuai.');
        }

        $connection = DB::connection();
        $usesPostgreSqlRole = $connection->getDriverName() === 'pgsql';

        if (! $usesPostgreSqlRole && ! app()->environment('testing')) {
            throw new LogicException('Request berperan memerlukan koneksi PostgreSQL.');
        }

        if ($usesPostgreSqlRole) {
            $connection->statement("SET ROLE {$databaseRole}");
        }

        try {
            $response = $next($request);
        } finally {
            if ($usesPostgreSqlRole) {
                $connection->statement('RESET ROLE');
            }
        }

        // Halaman ber-otentikasi tidak boleh disimpan di cache/bfcache browser.
        // Dengan begitu tombol back setelah logout tidak menampilkan halaman lama.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
