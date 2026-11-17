<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kota;
use Illuminate\Http\JsonResponse;

class WilayahController extends Controller
{
    public function kabupaten(int $provinsi): JsonResponse
    {
        $daftarKabupatenKota = Kota::query()
            ->where('id_provinsi', $provinsi)
            ->where(fn ($query) => $query
                ->where('nama_kota', 'like', 'Kabupaten %')
                ->orWhere('nama_kota', 'like', 'Kota %'))
            ->orderBy('nama_kota')
            ->get(['id_kota', 'nama_kota'])
            ->map(fn (Kota $kota) => [
                'id' => $kota->id_kota,
                'nama' => $kota->nama_kota,
            ])
            ->values();

        return response()->json($daftarKabupatenKota);
    }
}
