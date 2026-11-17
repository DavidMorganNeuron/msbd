<?php

namespace App\Observers;

use App\Support\AuditCatat;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    private const RAHASIA = [
        'password',
        'password_hash',
        'remember_token',
        'token',
        'api_token',
        'nik',
        'no_telepon',
        'email',
        'nis',
        'nisn',
    ];

    public function created(Model $model): void
    {
        $this->catat($model, 'INSERT', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = array_keys($model->getChanges());

        // Abaikan perubahan yang hanya memperbarui waktu login.
        if (
            ! empty($changes) &&
            empty(array_diff($changes, ['terakhir_login']))
        ) {
            return;
        }

        $this->catat(
            $model,
            'UPDATE',
            $model->getRawOriginal(),
            $model->getAttributes()
        );
    }

    public function deleted(Model $model): void
    {
        $this->catat($model, 'DELETE', $model->getAttributes(), null);
    }

    private function catat(
        Model $model,
        string $operasi,
        ?array $lama,
        ?array $baru
    ): void {
        $bersihkan = function (?array $atribut): ?array {
            if ($atribut === null) {
                return null;
            }

            foreach (array_keys($atribut) as $kolom) {
                if (in_array(strtolower($kolom), self::RAHASIA, true)) {
                    unset($atribut[$kolom]);
                }
            }

            return $atribut;
        };

        AuditCatat::tulis(
            $model->getTable(),
            $operasi,
            $model->getKey(),
            $bersihkan($lama),
            $bersihkan($baru),
        );
    }
}
