<?php

namespace App\Support;

use App\Models\LogAudit;
use Illuminate\Support\Facades\DB;

/** PostgreSQL audita por trigger; el helper conserva la ruta de respaldo de las pruebas SQLite. */
class AuditCatat
{
    public static function tulis(string $tabel, string $operasi, int|string|null $idData, ?array $lama, ?array $baru): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return;
        }

        $p = AuditPelaku::sekarang();

        LogAudit::create([
            'nama_tabel' => $tabel,
            'operasi' => $operasi,
            'id_data' => $idData !== null ? (string) $idData : null,
            'nilai_lama' => $lama,
            'nilai_baru' => $baru,
            'id_pengguna_pengubah' => $p['id'] ?? null,
            'username_pengubah' => $p['username'] ?? null,
            'waktu' => now(),
        ]);
    }
}
