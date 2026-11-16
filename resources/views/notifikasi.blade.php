@extends('layouts.app')
@section('title', 'Notifikasi')
@section('heading', 'Notifikasi')
@section('sub', 'Pesan dan pengingat untuk akunmu. Tandai sudah dibaca setelah kamu membacanya.')
@section('content')
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <div class="text-sm text-mute">
            @if ($belumDibaca > 0)
                <b class="text-ink">{{ $belumDibaca }}</b> notifikasi belum dibaca.
            @else
                Semua notifikasi sudah dibaca.
            @endif
        </div>
        @if ($belumDibaca > 0)
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">@csrf
                <button class="btn btn-ghost !py-1.5">Tandai semua dibaca</button>
            </form>
        @endif
    </div>

    @if ($daftar->isEmpty())
        <div class="card text-center text-mute py-10">
            <x-icon name="bell" size="w-10 h-10 mx-auto mb-3 text-mute/60" />
            Belum ada notifikasi.
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($daftar as $n)
                <li class="card {{ $n->dibaca ? '' : 'border-flame/40 bg-tint/40' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="sec-title !mb-0">{{ $n->judul }}</h2>
                                @if (! $n->dibaca)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-flame/10 text-flame">Belum dibaca</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-line text-mute">Dibaca</span>
                                @endif
                            </div>
                            <p class="text-sm mt-2 whitespace-pre-line">{{ $n->pesan }}</p>
                            <p class="text-xs text-mute mt-2">
                                {{ $n->dibuat_pada ? \Carbon\Carbon::parse($n->dibuat_pada)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H:i') : '-' }}
                            </p>
                        </div>
                        @if (! $n->dibaca)
                            <form method="POST" action="{{ route('notifikasi.baca', $n->id_notifikasi) }}" class="shrink-0">@csrf
                                <button class="btn btn-ghost !py-1.5">Tandai dibaca</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $daftar->links() }}</div>
    @endif
@endsection
