
@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('heading', 'Pengaturan Sistem')
@section('sub', 'Pengaturan umum aplikasi. Setiap perubahan tercatat di jejak audit.')

@section('content')
<form
    method="POST"
    action="{{ route('admin.pengaturan.simpan') }}"
    class="card max-w-2xl space-y-4"
    data-validasi
>
    @csrf
    @method('PUT')

    @foreach ($set as [$label, $kunci, $nilai, $ket])
        <div>
            <label class="label" for="{{ $kunci }}">{{ $label }}</label>

            @if ($kunci === 'tahun_ajaran_aktif')
                <select class="input" id="{{ $kunci }}" name="{{ $kunci }}" required>
                    @foreach ($tahunAjaran as $t)
                        <option
                            value="{{ $t }}"
                            @selected(old($kunci, $nilai) === $t)
                        >
                            {{ $t }}
                        </option>
                    @endforeach
                </select>
            @else
                <input
                    class="input"
                    id="{{ $kunci }}"
                    name="{{ $kunci }}"
                    type="{{ in_array($kunci, ['max_login_gagal', 'durasi_kunci', 'ukuran_berkas_mb'], true) ? 'number' : 'text' }}"
                    @if ($kunci === 'max_login_gagal') min="3" max="10" step="1" @endif
                    @if ($kunci === 'durasi_kunci') min="1" max="1440" step="1" @endif
                    @if ($kunci === 'ukuran_berkas_mb') min="1" max="20" step="1" @endif
                    value="{{ old($kunci, $nilai) }}"
                >
            @endif

            <p class="text-[11px] text-mute mt-1">{{ $ket }}</p>

            @error($kunci)
                <p class="text-xs text-bad mt-1">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <button class="btn btn-flame">Simpan pengaturan</button>
</form>
@endsection
