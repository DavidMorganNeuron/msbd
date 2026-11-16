<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Beranda') · {{ config('sekolah.nama') }}</title>
    <link rel="icon" href="{{ asset(config('sekolah.logo', 'images/logo.png')) }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper">
@php
    // Tambah menu baru cukup dengan menambah satu baris di sini.
    $menu = [
        ['label' => 'Beranda',      'route' => 'home',        'cocok' => 'home'],
        ['label' => 'Daftar PPDB',  'route' => 'ppdb',        'cocok' => 'ppdb'],
        ['label' => 'Cek Status',   'route' => 'ppdb.status', 'cocok' => 'ppdb.status'],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 lg:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <x-logo size="w-9 h-9" />
            <div class="min-w-0 leading-tight">
                <div class="truncate font-serif text-sm font-semibold text-navy">{{ config('sekolah.nama') }}</div>
                <div class="truncate text-[11px] text-mute">{{ config('sekolah.sistem') }}</div>
            </div>
        </a>

        <nav class="ml-auto hidden items-center gap-1 text-sm font-medium md:flex" aria-label="Menu utama">
            @foreach ($menu as $m)
                <a href="{{ route($m['route']) }}"
                   @if (request()->routeIs($m['cocok'])) aria-current="page" @endif
                   class="rounded-lg px-3 py-2 transition {{ request()->routeIs($m['cocok']) ? 'bg-tint text-flame' : 'text-ink hover:bg-tint/70' }}">
                    {{ $m['label'] }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('login') }}"
           class="ml-auto inline-flex items-center gap-2 rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-soft md:ml-2">
            Masuk
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <time data-waktu-real-time datetime="{{ now()->toIso8601String() }}" class="hidden text-right text-xs text-mute xl:block" aria-label="Hari, tanggal, dan waktu terkini">
            {{ now()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s') }}
        </time>

        <button type="button" id="burger" class="grid size-10 place-items-center rounded-lg border border-line md:hidden"
                aria-label="Buka menu" aria-expanded="false" aria-controls="menu-mobile">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>

    <nav id="menu-mobile" class="hidden border-t border-line bg-white px-4 py-3 md:hidden" aria-label="Menu seluler">
        @foreach ($menu as $m)
            <a href="{{ route($m['route']) }}"
               class="block rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs($m['cocok']) ? 'bg-tint text-flame' : 'text-ink' }}">
                {{ $m['label'] }}
            </a>
        @endforeach
    </nav>
</header>

<main>
    @yield('content')
</main>

<footer class="border-t border-line bg-white">
    <div class="mx-auto max-w-7xl px-4 py-6 text-center text-xs text-mute lg:px-8">
        &copy; {{ date('Y') }} {{ config('sekolah.nama') }}. Semua hak dilindungi.
    </div>
</footer>

<script>
    (() => {
        const tombol = document.getElementById('burger');
        const menu = document.getElementById('menu-mobile');
        if (!tombol || !menu) return;
        tombol.addEventListener('click', () => {
            const buka = menu.classList.toggle('hidden') === false;
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    })();
</script>
@stack('scripts')
</body>
</html>