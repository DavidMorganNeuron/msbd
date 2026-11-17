<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferensiDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->jenjangDanTingkat();
        $this->agamaDanPekerjaan();
        $this->statusSiswa();
        $this->sekolah();
        $this->dokumen();
        $this->alasanDanKehadiran();
        $this->pengaturan();
        $this->fieldPengajuan();
    }

    private function jenjangDanTingkat(): void
    {
        DB::table('jenjang')->updateOrInsert(
            ['nama_jenjang' => 'SMA'],
            ['nama_jenjang' => 'SMA']
        );

        $idJenjang = DB::table('jenjang')->where('nama_jenjang', 'SMA')->value('id_jenjang');

        foreach ([['X', 10], ['XI', 11], ['XII', 12]] as [$nama, $urutan]) {
            DB::table('tingkat')->updateOrInsert(
                ['nama_tingkat' => $nama],
                ['nama_tingkat' => $nama, 'urutan' => $urutan, 'id_jenjang' => $idJenjang]
            );
        }

        foreach ([['MIA', 'MIA'], ['IIS', 'IIS']] as [$kode, $nama]) {
            DB::table('jurusan')->updateOrInsert(
                ['kode_jurusan' => $kode],
                ['kode_jurusan' => $kode, 'nama_jurusan' => $nama, 'is_aktif' => true]
            );
        }
    }

    private function agamaDanPekerjaan(): void
    {
        foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'] as $agama) {
            DB::table('agama')->updateOrInsert(
                ['nama_agama' => $agama],
                ['nama_agama' => $agama, 'is_aktif' => true]
            );
        }

        $pekerjaan = [
            'Tidak Bekerja', 'Nelayan', 'Petani', 'Peternak', 'PNS/ASN',
            'Karyawan Swasta', 'Pedagang', 'Wiraswasta', 'TNI/Polri', 'Guru/Dosen',
            'Tenaga Kesehatan', 'Buruh', 'Ibu Rumah Tangga', 'Pensiunan', 'Lainnya',
        ];
        foreach ($pekerjaan as $nama) {
            DB::table('pekerjaan')->updateOrInsert(
                ['nama_pekerjaan' => $nama],
                ['nama_pekerjaan' => $nama, 'is_aktif' => true]
            );
        }
    }

    private function statusSiswa(): void
    {
        foreach ([
            ['calon', 'Calon Siswa'],
            ['aktif', 'Aktif'],
            ['cuti', 'Cuti'],
            ['mutasi', 'Mutasi'],
            ['lulus', 'Lulus'],
            ['keluar', 'Keluar'],
            ['mengundurkan_diri', 'Mengundurkan Diri'],
            ['dikeluarkan', 'Dikeluarkan'],
            ['putus_sekolah', 'Putus Sekolah'],
            ['meninggal_dunia', 'Meninggal Dunia'],
        ] as [$kodeStatus, $namaStatus]) {
            DB::table('status_siswa')->updateOrInsert(
                ['kode_status' => $kodeStatus],
                [
                    'nama_status' => $namaStatus,
                    'boleh_diubah_siswa' => false,
                ]
            );
        }
    }

    private function sekolah(): void
    {
        $sekolah = [
            ['nama_sekolah' => 'SMP Swasta Methodist 2 Medan', 'npsn' => '10260001'],
            ['nama_sekolah' => 'SMP Negeri 1 Medan', 'npsn' => '10210001'],
            ['nama_sekolah' => 'SMP Negeri 2 Medan', 'npsn' => '10210002'],
            ['nama_sekolah' => 'SMP Swasta Santo Thomas 1 Medan', 'npsn' => '10260002'],
            ['nama_sekolah' => 'SMP Swasta Methodist 1 Medan', 'npsn' => '10260003'],
        ];

        foreach ($sekolah as $data) {
            DB::table('sekolah')->updateOrInsert(['npsn' => $data['npsn']], $data + [
                'alamat' => 'Medan, Sumatera Utara',
                'is_aktif' => true,
            ]);
        }
    }

    private function dokumen(): void
    {
        $dokumen = [
            'Kartu Keluarga',
            'Akta Kelahiran',
            'Ijazah/SKL',
            'Pas Foto 3x4',
            'Lainnya',
        ];

        foreach ($dokumen as $nama) {
            DB::table('jenis_dokumen')->updateOrInsert(
                ['nama_dokumen' => $nama],
                ['nama_dokumen' => $nama]
            );
        }

        $checklist = [
            'Kartu Keluarga' => ['Nomor KK terbaca jelas', 'Data anggota keluarga sesuai'],
            'Akta Kelahiran' => ['Nama dan tanggal lahir sesuai', 'Tercatat di catatan sipil'],
            'Ijazah/SKL' => ['Tercap resmi sekolah', 'Nilai dan identitas terbaca'],
            'Pas Foto 3x4' => ['Latar belakang merah atau biru', 'Wajah terlihat jelas'],
            'Lainnya' => ['Dokumen pendukung relevan'],
        ];

        foreach ($checklist as $namaDokumen => $items) {
            $id = DB::table('jenis_dokumen')->where('nama_dokumen', $namaDokumen)->value('id_jenis_dokumen');

            foreach ($items as $item) {
                DB::table('ref_checklist_item')->updateOrInsert(
                    ['id_jenis_dokumen' => $id, 'nama_item' => $item],
                    ['id_jenis_dokumen' => $id, 'nama_item' => $item, 'wajib' => true]
                );
            }
        }
    }

    private function alasanDanKehadiran(): void
    {
        foreach ([
            'Berkas buram/tidak terbaca',
            'Data tidak sesuai dokumen',
            'Bukti pendukung belum lengkap',
            'Dokumen kedaluwarsa',
            'Lainnya',
        ] as $alasan) {
            DB::table('ref_alasan_pengembalian')->updateOrInsert(
                ['nama_alasan' => $alasan],
                ['nama_alasan' => $alasan]
            );
        }

        foreach ([
            ['H', 'Hadir'],
            ['S', 'Sakit'],
            ['I', 'Izin'],
            ['A', 'Alpa'],
        ] as [$kode, $nama]) {
            DB::table('status_kehadiran')->updateOrInsert(
                ['kode' => $kode],
                ['kode' => $kode, 'nama_status' => $nama]
            );
        }
    }

    private function pengaturan(): void
    {
        $pengaturan = [
            'nama_sekolah' => ['SMA Methodist 2 Medan', 'Nama sekolah yang tampil di aplikasi'],
            'alamat_sekolah' => ['Jl. Gatot Subroto No. 12, Medan', 'Alamat sekolah'],
            'max_login_gagal' => ['5', 'Batas login gagal sebelum akun dikunci sementara'],
            'durasi_kunci' => ['15', 'Durasi kunci akun dalam menit'],
            'ukuran_berkas_mb' => ['5', 'Ukuran maksimum berkas unggahan dalam MB'],
            'format_berkas' => ['pdf,jpg,jpeg,png', 'Format berkas yang diizinkan'],
            'tahun_ajaran_aktif' => ['2026/2027', 'Tahun ajaran aktif'],
            'fitur.nilai' => ['0', 'Aktifkan modul nilai (1/0)'],
            'fitur.jadwal' => ['0', 'Aktifkan modul jadwal (1/0)'],
            'fitur.absensi' => ['0', 'Aktifkan modul absensi (1/0)'],
        ];

        foreach ($pengaturan as $kunci => [$nilai, $deskripsi]) {
            DB::table('pengaturan_sistem')->updateOrInsert(
                ['kunci' => $kunci],
                [
                    'nilai' => $nilai,
                    'deskripsi' => $deskripsi,
                    'id_pengguna_pengubah' => null,
                    'tgl_diubah' => now(),
                ]
            );
        }
    }

    private function fieldPengajuan(): void
    {
        $dokumen = DB::table('jenis_dokumen')->pluck('id_jenis_dokumen', 'nama_dokumen');

        $fields = [
            ['siswa', 'nama_lengkap', 'Nama lengkap', true, true, 'Akta Kelahiran'],
            ['siswa', 'jenis_kelamin', 'Jenis kelamin', true, true, 'Akta Kelahiran'],
            ['siswa', 'tempat_lahir', 'Tempat lahir', true, true, 'Akta Kelahiran'],
            ['siswa', 'tanggal_lahir', 'Tanggal lahir', true, true, 'Akta Kelahiran'],
            ['siswa', 'email', 'Email', true, false, null],
            ['siswa', 'no_telepon', 'Nomor telepon', true, false, null],
            ['siswa', 'nis', 'NIS', true, false, null],
            ['siswa', 'nisn', 'NISN', true, false, null],
            ['siswa', 'nik', 'NIK', true, true, 'Kartu Keluarga'],

            ['alamat_siswa', 'alamat', 'Alamat', true, true, 'Kartu Keluarga'],
            ['alamat_siswa', 'rt', 'RT', true, false, null],
            ['alamat_siswa', 'rw', 'RW', true, false, null],
            ['alamat_siswa', 'kelurahan', 'Kelurahan/Desa', true, true, 'Kartu Keluarga'],
            ['alamat_siswa', 'kecamatan', 'Kecamatan', true, true, 'Kartu Keluarga'],
            ['alamat_siswa', 'kota', 'Kota/Kabupaten', true, true, 'Kartu Keluarga'],
            ['alamat_siswa', 'provinsi', 'Provinsi', true, true, 'Kartu Keluarga'],
            ['alamat_siswa', 'kode_pos', 'Kode pos', true, false, null],

            ['orang_tua_wali', 'ayah_nama_lengkap', 'Nama ayah', true, true, 'Kartu Keluarga'],
            ['orang_tua_wali', 'ayah_no_telepon', 'Telepon ayah', true, false, null],
            ['orang_tua_wali', 'ayah_email', 'Email ayah', true, false, null],
            ['orang_tua_wali', 'ayah_alamat', 'Alamat ayah', true, false, null],
            ['orang_tua_wali', 'ibu_nama_lengkap', 'Nama ibu', true, true, 'Kartu Keluarga'],
            ['orang_tua_wali', 'ibu_no_telepon', 'Telepon ibu', true, false, null],
            ['orang_tua_wali', 'ibu_email', 'Email ibu', true, false, null],
            ['orang_tua_wali', 'ibu_alamat', 'Alamat ibu', true, false, null],
            ['orang_tua_wali', 'wali_nama_lengkap', 'Nama wali', true, true, 'Kartu Keluarga'],
            ['orang_tua_wali', 'wali_no_telepon', 'Telepon wali', true, false, null],
            ['orang_tua_wali', 'wali_email', 'Email wali', true, false, null],
            ['orang_tua_wali', 'wali_alamat', 'Alamat wali', true, false, null],
        ];

        foreach ($fields as [$tabel, $kolom, $label, $bolehDiubah, $perluBukti, $namaDokumen]) {
            DB::table('ref_field_data')->updateOrInsert(
                ['nama_tabel' => $tabel, 'nama_kolom' => $kolom],
                [
                    'nama_tabel' => $tabel,
                    'nama_kolom' => $kolom,
                    'label_field' => $label,
                    'boleh_diubah_siswa' => $bolehDiubah,
                    'perlu_bukti' => $perluBukti,
                    'id_jenis_dokumen_bukti' => $namaDokumen ? ($dokumen[$namaDokumen] ?? null) : null,
                ]
            );
        }
    }
}
