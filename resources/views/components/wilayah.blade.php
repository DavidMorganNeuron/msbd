@props(['wajib' => false, 'provinsi' => '', 'kota' => '', 'kecamatan' => '', 'kelurahan' => ''])
@php
    $provinsiTerpilih = old('prov', $provinsi);
    $kotaTerpilih = old('kota', $kota);
    $daftarProvinsi = \Illuminate\Support\Facades\Schema::hasTable('provinsi')
        ? \App\Models\Provinsi::query()
            ->orderBy('nama_provinsi')
            ->get(['id_provinsi', 'nama_provinsi'])
        : collect();
    $provinsiAwal = $daftarProvinsi->firstWhere('nama_provinsi', $provinsiTerpilih);
    $daftarKabupaten = $provinsiAwal && \Illuminate\Support\Facades\Schema::hasTable('kota')
        ? \App\Models\Kota::query()
            ->where('id_provinsi', $provinsiAwal->id_provinsi)
            ->where('nama_kota', 'like', 'Kabupaten %')
            ->orderBy('nama_kota')
            ->get(['id_kota', 'nama_kota'])
        : collect();
@endphp
<div id="wilayah" class="grid gap-4 sm:grid-cols-2">
    <div class="min-w-0">
        <label class="label" for="w-prov">Provinsi</label>
        <select class="input min-w-0 disabled:bg-paper disabled:text-mute/70 disabled:cursor-not-allowed"
                id="w-prov" name="prov" @if ($wajib) data-wajib @endif>
            <option value="">Pilih Provinsi</option>
            @foreach ($daftarProvinsi as $provinsiItem)
                <option value="{{ $provinsiItem->nama_provinsi }}"
                        data-id="{{ $provinsiItem->id_provinsi }}"
                        @selected($provinsiTerpilih === $provinsiItem->nama_provinsi)>{{ $provinsiItem->nama_provinsi }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label class="label" for="w-kota">Kabupaten</label>
        <select class="input min-w-0 disabled:bg-paper disabled:text-mute/70 disabled:cursor-not-allowed"
                id="w-kota" name="kota" @disabled(! $provinsiAwal) @if ($wajib) data-wajib @endif>
            <option value="">{{ $provinsiAwal ? 'Pilih Kabupaten' : 'Pilih Provinsi dulu' }}</option>
            @foreach ($daftarKabupaten as $kabupatenItem)
                <option value="{{ $kabupatenItem->nama_kota }}" @selected($kotaTerpilih === $kabupatenItem->nama_kota)>{{ $kabupatenItem->nama_kota }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label class="label" for="w-kec">Kecamatan</label>
        <input class="input min-w-0" id="w-kec" name="kec" type="text" value="{{ old('kec', $kecamatan) }}" @if ($wajib) data-wajib @endif>
    </div>
    <div class="min-w-0">
        <label class="label" for="w-kel">Kelurahan</label>
        <input class="input min-w-0" id="w-kel" name="kel" type="text" value="{{ old('kel', $kelurahan) }}" @if ($wajib) data-wajib @endif>
    </div>
</div>
<script>
(function () {
    var prov = document.getElementById('w-prov');
    var kota = document.getElementById('w-kota');
    var url = @json(route('wilayah.kabupaten', ['provinsi' => '__ID__']));

    function isiKabupaten(daftar) {
        kota.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = daftar.length ? 'Pilih Kabupaten' : 'Kabupaten belum tersedia';
        kota.appendChild(placeholder);

        daftar.forEach(function (kabupaten) {
            var opsi = document.createElement('option');
            opsi.value = kabupaten.nama;
            opsi.textContent = kabupaten.nama;
            kota.appendChild(opsi);
        });

        kota.disabled = daftar.length === 0;
    }

    function muatKabupaten() {
        var opsi = prov.options[prov.selectedIndex];
        var idProvinsi = opsi ? opsi.dataset.id : '';

        if (!idProvinsi) {
            isiKabupaten([]);
            return;
        }

        kota.disabled = true;
        fetch(url.replace('__ID__', idProvinsi), { headers: { Accept: 'application/json' } })
            .then(function (respons) {
                if (!respons.ok) throw new Error('Gagal memuat data kabupaten.');
                return respons.json();
            })
            .then(function (data) { isiKabupaten(data); })
            .catch(function () {
                isiKabupaten([]);
                kota.options[0].textContent = 'Gagal memuat kabupaten. Pilih ulang provinsi.';
            });
    }

    prov.addEventListener('change', muatKabupaten);
})();
</script>
