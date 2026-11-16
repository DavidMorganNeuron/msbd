<?php

namespace App\Support;

/** Penyamaran data pribadi yang ditampilkan di daftar/tabel. */
class Sensor
{
    public static function nomor(?string $nilai, int $tampil = 4): string
    {
        $nilai = trim((string) $nilai);

        if ($nilai === '') {
            return '-';
        }

        $panjang = mb_strlen($nilai);

        if ($panjang <= $tampil) {
            return str_repeat('*', $panjang);
        }

        return str_repeat('*', $panjang - $tampil).mb_substr($nilai, -$tampil);
    }

    public static function nik(?string $nilai): string
    {
        return self::nomor($nilai, 4);
    }

    public static function kk(?string $nilai): string
    {
        return self::nomor($nilai, 4);
    }
}
