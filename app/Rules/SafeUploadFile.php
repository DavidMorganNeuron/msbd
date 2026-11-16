<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SafeUploadFile implements ValidationRule
{
    private const FORMAT_TANDA_TANGAN = [
        'pdf' => '%PDF-',
        'jpg' => "\xFF\xD8\xFF",
        'jpeg' => "\xFF\xD8\xFF",
        'png' => "\x89PNG\r\n\x1A\n",
    ];

    public function __construct(private array $formatDiizinkan = ['pdf', 'jpg', 'jpeg', 'png']) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Berkas tidak valid atau tidak berhasil diunggah.');

            return;
        }

        $originalName = strtolower((string) ($value->getClientOriginalName() ?? ''));
        $ext = strtolower((string) $value->getClientOriginalExtension());

        $format = array_values(array_intersect(
            array_keys(self::FORMAT_TANDA_TANGAN),
            array_map('strtolower', $this->formatDiizinkan)
        ));

        if ($format === []) {
            $fail('Belum ada format berkas yang diizinkan pada Pengaturan Sistem.');

            return;
        }

        $polaFormat = implode('|', array_map('preg_quote', $format));
        if ($originalName !== '' && preg_match('/\.('.$polaFormat.')\.[a-z0-9]+$/i', $originalName)) {
            $fail('Nama file berisi ekstensi ganda yang tidak diizinkan.');

            return;
        }

        if (! in_array($ext, $format, true)) {
            $fail('Format berkas tidak diizinkan pada Pengaturan Sistem.');

            return;
        }

        $path = $value->getRealPath();
        if (! $path || ! is_file($path)) {
            $fail('Berkas tidak dapat dibaca untuk validasi isi.');

            return;
        }

        $head = @file_get_contents($path, false, null, 0, 8192);
        if ($head === false || $head === '') {
            $fail('Isi berkas tidak valid.');

            return;
        }

        $signature = self::FORMAT_TANDA_TANGAN[$ext] ?? '';
        $isValid = $ext === 'pdf'
            ? str_contains($head, $signature)
            : str_starts_with($head, $signature);

        if (! $isValid) {
            $fail('Isi berkas tidak cocok dengan tipe file yang dipilih.');
        }
    }
}
