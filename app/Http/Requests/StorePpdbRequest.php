<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePpdbRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_pendaftaran' => ['nullable', 'string', 'max:40', 'unique:pendaftaran_ppdb,nomor_pendaftaran'],
            'nama_calon_siswa' => ['required', 'string', 'max:150'],
            'nisn' => ['nullable', 'digits:10'],
            'nik' => ['nullable', 'digits:16'],
            'no_kk' => ['nullable', 'digits:16'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:80'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'in:01,02,03,04,05,06,07'],
            'email' => ['nullable', 'email', 'max:120'],
            'sekolah_asal' => ['nullable', 'string', 'max:200'],
            'alamat' => ['nullable', 'string'],
            'no_telepon' => ['nullable', 'string', 'max:30'],
            'nama_ortu_wali' => ['nullable', 'string', 'max:120'],
            'tahun_pelajaran' => ['nullable', 'string', 'max:20'],
            'nis' => ['nullable', 'string', 'max:30'],
            'jenjang_tujuan' => ['nullable', 'string', 'max:10'],
            'kelas_tujuan' => ['nullable', 'string', 'max:80'],
            'no_seri_ijazah' => ['nullable', 'string', 'max:50'],
            'tahun_ijazah' => ['nullable', 'string', 'max:4'],
            'persetujuan_ortu' => ['nullable', 'boolean'],
            'pernyataan' => ['nullable', 'boolean'],
        ];
    }
}
