<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kota;
use Illuminate\Http\JsonResponse;

class WilayahController extends Controller
{
    public function kabupaten(int $provinsi): JsonResponse
    {
        $daftarKabupaten = Kota::query()
            ->where('id_provinsi', $provinsi)
            ->where('nama_kota', 'like', 'Kabupaten %')
            ->orderBy('nama_kota')
            ->get(['id_kota', 'nama_kota'])
            ->map(fn (Kota $kota) => [
                'id' => $kota->id_kota,
                'nama' => $kota->nama_kota,
            ])
            ->values();

        return response()->json($daftarKabupaten);
    }
}
