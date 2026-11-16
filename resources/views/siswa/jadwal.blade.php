@extends('layouts.app')
@section('title', 'Jadwal Pelajaran')
@section('heading', 'Jadwal Pelajaran')
@section('sub', 'Jadwal pelajaran harian kelasmu.')
@section('content')
@php
    $daftarHari = $daftarHari ?? \App\Http\Controllers\PageController::daftarHariJadwal();
    $daftarSlot = $daftarSlot ?? \App\Http\Controllers\PageController::daftarSlotJadwal();
    $matriks = $matriks ?? [];
@endphp

@if (!$kelas)
    <div class="card text-sm text-mute">
        Kamu belum terdaftar di kelas pada semester aktif. Hubungi Tata Usaha agar kelasmu diatur terlebih dahulu.
    </div>
@else
    <div class="space-y-4" data-jadwal-mode-switch>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-mute">
                Kelas <b class="text-ink text-base">{{ $kelas }}</b>
                @if ($semester)
                    <span> · {{ $semester->nama_semester }}</span>
                @endif
            </div>
            <div class="inline-flex rounded-full border border-line bg-white p-1" role="group" aria-label="Mode tampilan jadwal">
                <button class="rounded-full bg-flame px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition" type="button" aria-pressed="true" data-jadwal-mode-button="harian">
                    Jadwal harian
                </button>
                <button class="rounded-full px-3 py-1.5 text-xs font-semibold text-mute transition hover:text-ink" type="button" aria-pressed="false" data-jadwal-mode-button="roster">
                    Roster lengkap
                </button>
            </div>
        </div>

        <div data-jadwal-mode-panel="harian">
            <x-jadwal-harian
                :jadwal="$jadwalHarian"
                :hari="$hariAktif"
                detail-key="guru"
                detail-label="Guru Pengajar"
                id-prefix="siswa"
            />
        </div>

        <div data-jadwal-mode-panel="roster" hidden>
            <div class="mb-4 text-xs text-mute">
                * Kolom ganda menandakan jeda istirahat (09:00 - 09:20 dan 11:20 - 11:30)
            </div>

            <div class="card p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse min-w-[950px]">
                        <thead>
                            <tr class="bg-tint/60 text-xs">
                                <th class="border border-line p-2 text-center w-28 min-w-[105px]">
                                    <div class="flex flex-col justify-between h-12">
                                        <span class="text-right text-[11px] font-bold text-ink">JAM</span>
                                        <span class="text-left text-[11px] font-bold text-ink">HARI</span>
                                    </div>
                                </th>
                                @foreach ($daftarSlot as $s => $slot)
                                    <th class="border border-line p-2 text-center min-w-[115px] {{ in_array($s, [3, 6]) ? 'border-r-4 border-r-line' : '' }}">
                                        <div class="font-bold text-ink text-xs">{{ $slot['label'] }}</div>
                                        <div class="text-[11px] text-mute font-normal mt-0.5">{{ $slot['waktu'] }}</div>
                                    </th>
                            @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftarHari as $h => $namaHari)
                                <tr class="hover:bg-tint/10 transition-colors">
                                    <td class="border border-line bg-tint/40 p-2 text-center font-bold text-xs tracking-wider text-ink align-middle">
                                        {{ $namaHari }}
                                    </td>
                                    @foreach ($daftarSlot as $s => $slot)
                                        @php
                                            $cell = $matriks[$h][$s] ?? null;
                                        @endphp
                                        <td class="border border-line p-2 text-center align-middle {{ in_array($s, [3, 6]) ? 'border-r-4 border-r-line' : '' }} {{ $cell ? 'bg-tint/5' : '' }}">
                                            @if ($cell)
                                                <div class="font-semibold text-ink text-sm leading-tight">{{ $cell['mapel'] }}</div>
                                                @if (!empty($cell['guru']) && !in_array(strtolower($cell['mapel']), ['upacara', 'kebaktian', 'istirahat']))
                                                    <div class="text-[11px] text-mute mt-1 line-clamp-1" title="{{ $cell['guru'] }}">{{ $cell['guru'] }}</div>
                                                @endif
                                                @if (!empty($cell['ruang']) && $cell['ruang'] !== '-')
                                                    <div class="text-[10px] text-mute/80 mt-0.5">R. {{ $cell['ruang'] }}</div>
                                                @endif
                                            @else
                                                <span class="text-mute/30 text-xs">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
