@extends('layouts.app')
@section('title', 'Rapor Siswa')
@section('heading', 'Rapor Siswa')
@section('sub', 'Isi nilai rapor siswa di kelas yang kamu walikan.')
@section('content')
@if (!$kelasWali)
    <div class="card text-sm text-mute">
        Kamu belum ditetapkan sebagai wali kelas pada semester aktif. Hubungi Tata Usaha untuk penugasan wali kelas.
    </div>
@else
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="kelas">Kelas</label>
            <input class="input" id="kelas" type="text" value="{{ $kelas['nama'] }}" readonly>
        </div>
        <div>
            <label class="label" for="mapel">Mata pelajaran</label>
            <select class="input" id="mapel" name="mapel" onchange="this.form.submit()">
                @forelse ($mapel as $m)
                    <option value="{{ $m['id'] }}" @selected($mapelTerpilih && $mapelTerpilih['id'] === $m['id'])>{{ $m['nama'] }}</option>
                @empty
                    <option value="">Belum ada mata pelajaran</option>
                @endforelse
            </select>
        </div>
        <noscript><button class="btn btn-flame">Tampilkan</button></noscript>
    </form>

    @if (!$siswa)
        <div class="card text-sm text-mute">Belum ada siswa aktif di kelas ini.</div>
    @elseif (!$mapel)
        <div class="card text-sm text-mute">
            Belum ada mata pelajaran. Tambahkan mata pelajaran di Konfigurasi Akademik atau susun jadwal pelajaran kelas.
        </div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <div class="card !p-4">
                <div class="text-xs text-mute">Hadir (kelas)</div>
                <div class="mt-1 text-2xl font-bold">{{ $rekapKelas['hadir'] }}</div>
            </div>
            <div class="card !p-4">
                <div class="text-xs text-mute">Sakit (kelas)</div>
                <div class="mt-1 text-2xl font-bold">{{ $rekapKelas['sakit'] }}</div>
            </div>
            <div class="card !p-4">
                <div class="text-xs text-mute">Izin (kelas)</div>
                <div class="mt-1 text-2xl font-bold">{{ $rekapKelas['izin'] }}</div>
            </div>
            <div class="card !p-4">
                <div class="text-xs text-mute">Alpha (kelas)</div>
                <div class="mt-1 text-2xl font-bold">{{ $rekapKelas['alpha'] }}</div>
            </div>
        </div>

        <form class="card" method="POST" action="{{ route('guru.rapor.simpan') }}"
              x-data="{
                  nilai: @js(old('nilai_mentah', $nilaiMentah)),
                  bobot: @js($bobotKomponen),
                  tersimpan: @js($nilaiAkhir),
                  siswaIds: @js(array_column($siswa, 'id')),
                  init() {
                      this.siswaIds.forEach((id) => {
                          if (!this.nilai[id]) {
                              this.nilai[id] = { tugas: null, keterampilan: null, ujian: null };
                          }
                      });
                  },
                  angka(nilai) {
                      if (nilai === null || nilai === '' || typeof nilai === 'undefined') {
                          return 0;
                      }

                      const angka = parseFloat(nilai);

                      return isNaN(angka) ? 0 : angka;
                  },
                  adaNilai(id) {
                      const baris = this.nilai[id] || {};

                      return ['tugas', 'keterampilan', 'ujian'].some((jenis) => {
                          const nilai = baris[jenis];

                          return nilai !== null && nilai !== '' && typeof nilai !== 'undefined';
                      });
                  },
                  hitung(id) {
                      if (!this.adaNilai(id)) {
                          return null;
                      }

                      const baris = this.nilai[id];
                      let jumlah = 0;
                      let totalBobot = 0;

                      ['tugas', 'keterampilan', 'ujian'].forEach((jenis) => {
                          const nilai = baris[jenis];

                          if (nilai === null || nilai === '' || typeof nilai === 'undefined') {
                              return;
                          }

                          const bobot = parseFloat(this.bobot[jenis] ?? 0);

                          if (isNaN(bobot) || bobot <= 0) {
                              return;
                          }

                          jumlah += this.angka(nilai) * bobot;
                          totalBobot += bobot;
                      });

                      return totalBobot > 0 ? jumlah / totalBobot : null;
                  },
                  format(nilai) {
                      return (Math.round(nilai * 100) / 100).toFixed(2);
                  },
                  tampil(id) {
                      const nilai = this.hitung(id);

                      if (nilai !== null) {
                          return this.format(nilai);
                      }

                      const tersimpan = this.tersimpan[id];

                      return (tersimpan === null || tersimpan === '' || typeof tersimpan === 'undefined')
                          ? '—'
                          : this.format(tersimpan);
                  }
              }">
            @csrf
            <input type="hidden" name="id_kelas" value="{{ $kelas['id'] }}">
            <input type="hidden" name="id_mapel" value="{{ $mapelTerpilih['id'] }}">

            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="sec-title !mb-0">Legger Nilai {{ $mapelTerpilih['nama'] }}</h2>
                    <p class="text-xs text-mute">{{ $kelas['nama'] }} - {{ $semester->nama_semester ?? '-' }}</p>
                </div>
                <p class="text-xs text-mute max-w-sm sm:text-right">
                    Nilai akhir dihitung otomatis memakai bobot komponen penilaian.
                    Komponen yang belum diisi tidak dihitung sebagai 0.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[980px]">
                    <thead>
                        <tr class="text-left text-xs text-mute border-b-2 border-line">
                            <th class="p-2 w-12">No</th>
                            <th class="p-2 min-w-[180px]">Nama siswa</th>
                            <th class="p-2 w-28 text-center">Tugas</th>
                            <th class="p-2 w-32 text-center">Keterampilan</th>
                            <th class="p-2 w-28 text-center">Ujian</th>
                            <th class="p-2 w-32 text-center">Nilai akhir</th>
                            <th class="p-2 min-w-[190px]">Catatan wali kelas</th>
                            <th class="p-2 w-32">Status rapor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($siswa as $i => $s)
                            @php
                                $mentah = $nilaiMentah[$s['id']] ?? ['tugas' => null, 'keterampilan' => null, 'ujian' => null];
                                $tampil = $nilaiTampil[$s['id']] ?? null;
                            @endphp
                            <tr class="border-b border-line align-top">
                                <td class="p-2.5 text-mute">{{ $i + 1 }}</td>
                                <td class="p-2.5">
                                    <div class="font-medium text-ink">{{ $s['nama'] }}</div>
                                    <div class="text-xs text-mute">NISN {{ $s['nisn'] }}</div>
                                </td>
                                <td class="p-2.5">
                                    <input class="input !py-1.5 !px-2 text-center" type="number" step="0.01" min="1" max="100"
                                           inputmode="decimal" placeholder="1-100"
                                           x-model.number="nilai[{{ $s['id'] }}].tugas"
                                           name="nilai_mentah[{{ $s['id'] }}][tugas]"
                                           value="{{ old('nilai_mentah.'.$s['id'].'.tugas', $mentah['tugas']) }}">
                                </td>
                                <td class="p-2.5">
                                    <input class="input !py-1.5 !px-2 text-center" type="number" step="0.01" min="1" max="100"
                                           inputmode="decimal" placeholder="1-100"
                                           x-model.number="nilai[{{ $s['id'] }}].keterampilan"
                                           name="nilai_mentah[{{ $s['id'] }}][keterampilan]"
                                           value="{{ old('nilai_mentah.'.$s['id'].'.keterampilan', $mentah['keterampilan']) }}">
                                </td>
                                <td class="p-2.5">
                                    <input class="input !py-1.5 !px-2 text-center" type="number" step="0.01" min="1" max="100"
                                           inputmode="decimal" placeholder="1-100"
                                           x-model.number="nilai[{{ $s['id'] }}].ujian"
                                           name="nilai_mentah[{{ $s['id'] }}][ujian]"
                                           value="{{ old('nilai_mentah.'.$s['id'].'.ujian', $mentah['ujian']) }}">
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="inline-flex min-w-[70px] items-center justify-center rounded-lg bg-tint px-2 py-1 font-bold text-navy"
                                          x-text="tampil({{ $s['id'] }})">{{ $tampil !== null ? number_format((float) $tampil, 2, '.', '') : '—' }}</span>
                                </td>
                                <td class="p-2.5">
                                    <input class="input !py-1.5" type="text" maxlength="2000" placeholder="Catatan (opsional)"
                                           name="catatan_siswa[{{ $s['id'] }}]"
                                           value="{{ old('catatan_siswa.'.$s['id'], $catatanSiswa[$s['id']] ?? '') }}">
                                </td>
                                <td class="p-2.5">
                                    <select class="input !py-1.5" name="status_siswa[{{ $s['id'] }}]">
                                        <option value="draft" @selected(old('status_siswa.'.$s['id'], $statusSiswa[$s['id']] ?? 'draft') === 'draft')>Draft</option>
                                        <option value="final" @selected(old('status_siswa.'.$s['id'], $statusSiswa[$s['id']] ?? 'draft') === 'final')>Final</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-mute mt-3">
                Nilai tugas, keterampilan, dan ujian disimpan ke komponen penilaian yang tersedia.
                Nilai akhir memakai bobot masing-masing komponen; komponen kosong tidak dihitung.
                Baris yang seluruh komponennya dikosongkan akan menghapus nilai mata pelajaran tersebut.
            </p>
            <button class="btn btn-flame mt-3 w-full sm:w-auto">Simpan Semua Nilai</button>
        </form>
    @endif
@endif
@endsection
