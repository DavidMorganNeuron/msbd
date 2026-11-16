<?php

namespace Database\Seeders;

use App\Support\WilayahIndonesia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WilayahSeeder extends Seeder
{
    /**
     * Kode provinsi resmi Kemendagri.
     *
     * @var array<string, string>
     */
    private const KODE_PROVINSI = [
        'Aceh' => '11',
        'Sumatera Utara' => '12',
        'Sumatera Barat' => '13',
        'Riau' => '14',
        'Jambi' => '15',
        'Sumatera Selatan' => '16',
        'Bengkulu' => '17',
        'Lampung' => '18',
        'Kepulauan Bangka Belitung' => '19',
        'Kepulauan Riau' => '21',
        'DKI Jakarta' => '31',
        'Jawa Barat' => '32',
        'Jawa Tengah' => '33',
        'DI Yogyakarta' => '34',
        'Jawa Timur' => '35',
        'Banten' => '36',
        'Bali' => '51',
        'Nusa Tenggara Barat' => '52',
        'Nusa Tenggara Timur' => '53',
        'Kalimantan Barat' => '61',
        'Kalimantan Tengah' => '62',
        'Kalimantan Selatan' => '63',
        'Kalimantan Timur' => '64',
        'Kalimantan Utara' => '65',
        'Sulawesi Utara' => '71',
        'Sulawesi Tengah' => '72',
        'Sulawesi Selatan' => '73',
        'Sulawesi Tenggara' => '74',
        'Gorontalo' => '75',
        'Sulawesi Barat' => '76',
        'Maluku' => '81',
        'Maluku Utara' => '82',
        'Papua' => '91',
        'Papua Barat' => '92',
        'Papua Selatan' => '93',
        'Papua Tengah' => '94',
        'Papua Pegunungan' => '95',
        'Papua Barat Daya' => '96',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (WilayahIndonesia::semua() as $namaProvinsi => $daftarKota) {
            $kode = self::KODE_PROVINSI[$namaProvinsi] ?? null;

            DB::table('provinsi')->updateOrInsert(
                ['nama_provinsi' => $namaProvinsi],
                ['nama_provinsi' => $namaProvinsi, 'kode_provinsi' => $kode]
            );

            $idProvinsi = DB::table('provinsi')
                ->where('nama_provinsi', $namaProvinsi)
                ->value('id_provinsi');

            foreach ($daftarKota as $namaKota) {
                DB::table('kota')->updateOrInsert(
                    ['id_provinsi' => $idProvinsi, 'nama_kota' => $namaKota],
                    ['id_provinsi' => $idProvinsi, 'nama_kota' => $namaKota]
                );
            }
        }
    }
}
