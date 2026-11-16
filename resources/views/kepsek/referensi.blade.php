@extends('layouts.app')
@section('title', 'Data Referensi PPDB')
@section('heading', 'Data Referensi PPDB')
@section('sub', 'Sebaran sekolah asal dan wilayah berdasarkan formulir PPDB yang sudah dikirim.')

@section('content')
<div class="space-y-4">
    <section class="card">
        <h2 class="sec-title">Sekolah Asal</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[420px]">
                <thead><tr class="text-left text-xs text-mute border-b-2 border-line"><th class="p-2">Sekolah</th><th class="p-2 text-right">Jumlah pendaftar</th></tr></thead>
                <tbody>
                @forelse ($sekolahAsal as $baris)
                    <tr class="border-b border-line"><td class="p-2.5 font-medium">{{ $baris->sekolah_asal }}</td><td class="p-2.5 text-right">{{ $baris->jumlah }}</td></tr>
                @empty
                    <tr><td colspan="2" class="p-5 text-center text-mute">Belum ada data sekolah asal yang dikirim.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="card">
            <h2 class="sec-title">Provinsi Asal</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[320px]">
                    <thead><tr class="text-left text-xs text-mute border-b-2 border-line"><th class="p-2">Provinsi</th><th class="p-2 text-right">Jumlah</th></tr></thead>
                    <tbody>
                    @forelse ($provinsiAsal as $baris)
                        <tr class="border-b border-line"><td class="p-2.5">{{ $baris->provinsi }}</td><td class="p-2.5 text-right">{{ $baris->jumlah }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="p-5 text-center text-mute">Belum ada data provinsi yang dikirim.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2 class="sec-title">Kabupaten/Kota Asal</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[360px]">
                    <thead><tr class="text-left text-xs text-mute border-b-2 border-line"><th class="p-2">Kabupaten/Kota</th><th class="p-2">Provinsi</th><th class="p-2 text-right">Jumlah</th></tr></thead>
                    <tbody>
                    @forelse ($kotaAsal as $baris)
                        <tr class="border-b border-line"><td class="p-2.5">{{ $baris->kota }}</td><td class="p-2.5">{{ $baris->provinsi ?: '-' }}</td><td class="p-2.5 text-right">{{ $baris->jumlah }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-5 text-center text-mute">Belum ada data kabupaten/kota yang dikirim.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection
