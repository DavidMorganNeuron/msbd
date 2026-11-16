<?php

namespace App\Http\Middleware;

use App\Models\Pengguna;
use Closure;
use Illuminate\Http\Request;
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

        // Cocokkan juga username/peran sesi. Ini mencegah 404 ketika tabel pengguna
        // memiliki id yang sama untuk akun berbeda (mis. akibat urutan sequence lama),
        // sehingga find() bisa mengambil akun dengan peran yang salah.
        $query = Pengguna::with('role')->where('id_pengguna', $idPengguna);

        if ($username) {
            $query->where(function ($sub) use ($username) {
                $sub->where('username', $username)
                    ->orWhere('nama_pengguna', $username);
            });
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

        $response = $next($request);

        // Halaman ber-otentikasi tidak boleh disimpan di cache/bfcache browser.
        // Dengan begitu tombol back setelah logout tidak menampilkan halaman lama.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
