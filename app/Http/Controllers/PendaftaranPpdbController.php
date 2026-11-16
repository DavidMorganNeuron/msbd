<?php

namespace App\Http\Controllers;

use App\Rules\SafeUploadFile;
use App\Services\PengaturanService;
use App\Services\PpdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PendaftaranPpdbController extends Controller
{
    public function __construct(
        private PpdbService $ppdb,
        private PengaturanService $pengaturan
    ) {}

    public function store(Request $request)
    {
        return $this->simpan($request, true);
    }

    public function draft(Request $request)
    {
        return $this->simpan($request, false);
    }

    private function simpan(Request $request, bool $final)
    {
        $token = $request->input('token_draf');
        $kelasTujuan = $this->ppdb->kelasTujuan();
        $modePerbaikan = $final && $this->ppdb->tokenPerluPerbaikan($token);
        $kelasTerpilihSebelumnya = $modePerbaikan
            ? DB::table('pendaftaran_ppdb')->where('token_draf', $token)->value('kelas_tujuan')
            : null;

        if ($kelasTerpilihSebelumnya) {
            $kelasTujuan[] = $kelasTerpilihSebelumnya;
            $kelasTujuan = array_values(array_unique($kelasTujuan));
        }

        $aturan = $this->aturan($final, $kelasTujuan, $modePerbaikan);

        if ($final) {
            foreach (PpdbService::DOKUMEN as $index => $jenis) {
                if (! $this->ppdb->punyaBerkas($token, $jenis)) {
                    $aturan['berkas_'.$index] = ['required', 'file', new SafeUploadFile($this->pengaturan->formatBerkasDiizinkan()), 'max:'.$this->maxBerkasKb()];
                }
            }
        }

        $data = $request->validate($aturan);
        if ($kelasTerpilihSebelumnya) {
            $data['kelas_tujuan'] = $kelasTerpilihSebelumnya;
        }

        $data['provinsi'] = (string) $request->input('prov', '');
        $data['kecamatan'] = (string) $request->input('kec', '');
        $data['kelurahan'] = (string) $request->input('kel', '');
        $data['persetujuan_ortu'] = $request->boolean('persetujuan_ortu');
        $data['pernyataan'] = $request->boolean('pernyataan');
        $data['langkah_terakhir'] = (int) $request->input('langkah_terakhir', 0);

        $berkas = [];
        foreach (array_keys(PpdbService::DOKUMEN) as $index) {
            $berkas[$index] = $request->file('berkas_'.$index);
        }

        $hasil = $this->ppdb->simpan($data, $token, $berkas, $final);

        if (! $final) {
            return redirect()
                ->route('ppdb', ['draf' => $hasil['token']])
                ->with('success', 'Draf pendaftaran disimpan. Lanjutkan kapan saja dengan tautan ini.');
        }

        return redirect()
            ->route('ppdb.status', ['cek_kode' => $hasil['nomor']])
            ->with(
                'success',
                $modePerbaikan
                    ? 'Perbaikan berhasil dikirim. Pendaftaran menunggu pemeriksaan kembali.'
                    : 'Pendaftaran berhasil dikirim. Nomor pendaftaran: '.$hasil['nomor']
            );
    }

    private function aturan(bool $final, array $kelasTujuan, bool $modePerbaikan): array
    {
        $wajib = $final ? 'required' : 'nullable';
        $wajibKataSandi = $final && ! $modePerbaikan ? 'required' : 'nullable';
        $maxBerkas = $this->maxBerkasKb();

        return [
            'token_draf' => ['nullable', 'string', 'max:64'],
            'langkah_terakhir' => ['nullable', 'integer', 'min:0', 'max:3'],
            'nomor_pendaftaran' => ['nullable', 'string', 'max:40'],
            'tanggal' => ['nullable', 'date'],
            'tahun_pelajaran' => ['nullable', 'string', 'max:20'],
            'nis' => ['nullable', 'string', 'max:30'],
            'pendaftaran_untuk' => [$wajib, 'in:SMA'],
            'jenjang_tujuan' => [$wajib, 'in:SMA'],
            'kelas_tujuan' => [$wajib, 'string', 'max:80', Rule::in($kelasTujuan)],
            'no_seri_skhun' => ['nullable', 'string', 'max:50'],
            'tahun_skhun' => ['nullable', 'string', 'max:4'],
            'no_seri_ijazah' => ['nullable', 'string', 'max:50'],
            'tahun_ijazah' => ['nullable', 'string', 'max:4'],
            'sekolah_asal' => [$wajib, 'string', 'max:200'],
            'no_peserta_ujian' => ['nullable', 'string', 'max:50'],
            'hobi' => ['nullable', 'string', 'max:10'],
            'cita_cita' => ['nullable', 'string', 'max:10'],
            'nama_lengkap' => [$wajib, 'string', 'max:150'],
            'password' => [$wajibKataSandi, 'string', 'min:8', 'confirmed'],
            'password_confirmation' => [$wajibKataSandi, 'string'],
            'jenis_kelamin' => [$wajib, 'in:L,P'],
            'nisn' => [$wajib, 'digits:10'],
            'nik' => [$wajib, 'digits:16'],
            'tempat_lahir' => [$wajib, 'string', 'max:80'],
            'tanggal_lahir' => [$wajib, 'date'],
            'anak_ke' => ['nullable', 'integer', 'min:1'],
            'status_keluarga' => ['nullable', 'string', 'max:50'],
            'agama' => [$wajib, Rule::in(array_keys($this->ppdb->agamaOptions()))],
            'berkebutuhan_khusus' => ['nullable', Rule::in(array_keys(PpdbService::KEBUTUHAN_KHUSUS))],
            'no_kk' => [$wajib, 'digits:16'],
            'no_hp' => [$wajib, 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'tempat_tinggal' => ['nullable', 'string', 'max:10'],
            'moda_transportasi' => ['nullable', 'string', 'max:10'],
            'penerima_kps' => ['nullable', 'in:YA,TIDAK'],
            'no_kps' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'in:WNI,WNA'],
            'negara' => ['nullable', 'string', 'max:80'],
            'layak_pip' => ['nullable', 'in:YA,TIDAK'],
            'alasan_pip' => ['nullable', 'string', 'max:10'],
            'prov' => [$wajib, 'string', 'max:80'],
            'kota' => [$wajib, 'string', 'max:80'],
            'kec' => [$wajib, 'string', 'max:80'],
            'kel' => [$wajib, 'string', 'max:80'],
            'jalan' => [$wajib, 'string', 'max:255'],
            'rt' => [$wajib, 'string', 'max:5'],
            'rw' => [$wajib, 'string', 'max:5'],
            'telp' => ['nullable', 'string', 'max:30'],
            'no_hp_alamat' => ['nullable', 'string', 'max:30'],
            'dusun' => ['nullable', 'string', 'max:80'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'ortu_nama_0' => [$final ? 'required' : 'nullable', 'string', 'max:120'],
            'ortu_nama_1' => [$final ? 'required' : 'nullable', 'string', 'max:120'],
            'ortu_nama_2' => ['nullable', 'string', 'max:120'],
            'ortu_nik_0' => ['nullable', 'digits:16'],
            'ortu_nik_1' => ['nullable', 'digits:16'],
            'ortu_nik_2' => ['nullable', 'digits:16'],
            'ortu_tanggal_lahir_0' => ['nullable', 'date'],
            'ortu_tanggal_lahir_1' => ['nullable', 'date'],
            'ortu_tanggal_lahir_2' => ['nullable', 'date'],
            'ortu_tempat_lahir_0' => ['nullable', 'string', 'max:80'],
            'ortu_tempat_lahir_1' => ['nullable', 'string', 'max:80'],
            'ortu_tempat_lahir_2' => ['nullable', 'string', 'max:80'],
            'ortu_pendidikan_0' => ['nullable', 'string', 'max:10'],
            'ortu_pendidikan_1' => ['nullable', 'string', 'max:10'],
            'ortu_pendidikan_2' => ['nullable', 'string', 'max:10'],
            'ortu_kerja_0' => [$final ? 'required' : 'nullable', 'string', 'max:10'],
            'ortu_kerja_1' => [$final ? 'required' : 'nullable', 'string', 'max:10'],
            'ortu_kerja_2' => ['nullable', 'string', 'max:10'],
            'ortu_penghasilan_0' => ['nullable', 'string', 'max:10'],
            'ortu_penghasilan_1' => ['nullable', 'string', 'max:10'],
            'ortu_penghasilan_2' => ['nullable', 'string', 'max:10'],
            'ortu_kebutuhan_0' => ['nullable', Rule::in(array_keys(PpdbService::KEBUTUHAN_KHUSUS))],
            'ortu_kebutuhan_1' => ['nullable', Rule::in(array_keys(PpdbService::KEBUTUHAN_KHUSUS))],
            'ortu_kebutuhan_2' => ['nullable', Rule::in(array_keys(PpdbService::KEBUTUHAN_KHUSUS))],
            'ortu_hp_0' => ['nullable', 'string', 'max:30'],
            'ortu_hp_1' => ['nullable', 'string', 'max:30'],
            'ortu_hp_2' => ['nullable', 'string', 'max:30'],
            'ortu_alamat_0' => ['nullable', 'string'],
            'ortu_alamat_1' => ['nullable', 'string'],
            'ortu_alamat_2' => ['nullable', 'string'],
            'tinggi_badan' => ['nullable', 'integer'],
            'berat_badan' => ['nullable', 'integer'],
            'gol_darah' => ['nullable', 'string', 'max:3'],
            'jarak' => ['nullable', 'string', 'max:5'],
            'jarak_km' => ['nullable', 'numeric', 'min:0'],
            'waktu_jam' => ['nullable', 'integer', 'min:0'],
            'waktu_menit' => ['nullable', 'integer', 'min:0', 'max:59'],
            'jumlah_saudara' => ['nullable', 'integer', 'min:0'],
            'prestasi_jenis' => ['nullable', 'array'],
            'prestasi_jenis.*' => ['nullable', 'string', 'max:10'],
            'prestasi_tingkat' => ['nullable', 'array'],
            'prestasi_tingkat.*' => ['nullable', 'string', 'max:10'],
            'prestasi_nama' => ['nullable', 'array'],
            'prestasi_nama.*' => ['nullable', 'string', 'max:150'],
            'prestasi_tahun' => ['nullable', 'array'],
            'prestasi_tahun.*' => ['nullable', 'string', 'max:4'],
            'prestasi_penyelenggara' => ['nullable', 'array'],
            'prestasi_penyelenggara.*' => ['nullable', 'string', 'max:150'],
            'prestasi_dikelola' => ['nullable', 'in:1'],
            'beasiswa_jenis' => ['nullable', 'array'],
            'beasiswa_jenis.*' => ['nullable', 'string', 'max:10'],
            'beasiswa_keterangan' => ['nullable', 'array'],
            'beasiswa_keterangan.*' => ['nullable', 'string'],
            'beasiswa_tahun_mulai' => ['nullable', 'array'],
            'beasiswa_tahun_mulai.*' => ['nullable', 'string', 'max:4'],
            'beasiswa_tahun_selesai' => ['nullable', 'array'],
            'beasiswa_tahun_selesai.*' => ['nullable', 'string', 'max:4'],
            'beasiswa_dikelola' => ['nullable', 'in:1'],
            'keluar_karena' => ['nullable', 'string', 'max:10'],
            'tanggal_keluar' => ['nullable', 'date'],
            'alasan_keluar' => ['nullable', 'string'],
            'nama_ortu_wali' => [$wajib, 'string', 'max:120'],
            'nomor_dokumen_1' => ['nullable', 'string', 'max:100'],
            'nomor_dokumen_2' => ['nullable', 'string', 'max:100'],
            'persetujuan_ortu' => [$final ? 'accepted' : 'nullable'],
            'pernyataan' => [$final ? 'accepted' : 'nullable'],
            'berkas_0' => ['nullable', 'file', new SafeUploadFile($this->pengaturan->formatBerkasDiizinkan()), 'max:'.$maxBerkas],
            'berkas_1' => ['nullable', 'file', new SafeUploadFile($this->pengaturan->formatBerkasDiizinkan()), 'max:'.$maxBerkas],
            'berkas_2' => ['nullable', 'file', new SafeUploadFile($this->pengaturan->formatBerkasDiizinkan()), 'max:'.$maxBerkas],
            'berkas_3' => ['nullable', 'file', new SafeUploadFile($this->pengaturan->formatBerkasDiizinkan()), 'max:'.$maxBerkas],
        ];
    }

    private function maxBerkasKb(): int
    {
        return ((int) ($this->pengaturan->nilai('ukuran_berkas_mb') ?: 2)) * 1024;
    }
}
