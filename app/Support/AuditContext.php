<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menjalankan penulisan dalam transaksi sambil memasang identitas pelaku
 * sebagai SET LOCAL app.user_id/app.username. Trigger fn_audit_trail membaca
 * nilai tersebut, dan nilainya otomatis hilang saat transaksi selesai.
 */
class AuditContext
{
    public static function transaksi(callable $callback, int $attempts = 3)
    {
        return DB::transaction(function () use ($callback) {
            self::pasang();

            return $callback();
        }, $attempts);
    }

    public static function pasang(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $pelaku = AuditPelaku::sekarang();

        DB::select(
            "select set_config('app.user_id', ?, true), set_config('app.username', ?, true)",
            [(string) ($pelaku['id'] ?? ''), (string) ($pelaku['username'] ?? '')]
        );
    }
}
