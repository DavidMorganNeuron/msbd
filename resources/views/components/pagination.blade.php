@props(['paginator', 'label' => 'kelas'])
@php
    $halamanSekarang = $paginator->currentPage();
    $halamanAwal = max(1, $halamanSekarang - 2);
    $halamanAkhir = min($paginator->lastPage(), $halamanSekarang + 2);
@endphp
@if ($paginator->total() > 0)
    <div class="mt-4 flex flex-col gap-2 border-t border-line pt-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-mute">Menampilkan {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $label }}</p>
        @if ($paginator->hasPages())
            <nav class="flex flex-wrap items-center gap-1 text-xs" aria-label="Navigasi halaman">
                @if ($paginator->onFirstPage())
                    <span class="rounded-full border border-line px-3 py-1.5 text-mute/50">&laquo; Sebelumnya</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-full border border-line bg-white px-3 py-1.5 text-flame hover:bg-tint">&laquo; Sebelumnya</a>
                @endif
                @foreach ($paginator->getUrlRange($halamanAwal, $halamanAkhir) as $halaman => $url)
                    @if ($halaman === $halamanSekarang)
                        <span aria-current="page" class="rounded-full bg-flame px-3 py-1.5 font-semibold text-white">{{ $halaman }}</span>
                    @else
                        <a href="{{ $url }}" class="rounded-full border border-line bg-white px-3 py-1.5 text-flame hover:bg-tint">{{ $halaman }}</a>
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-full border border-line bg-white px-3 py-1.5 text-flame hover:bg-tint">Berikutnya &raquo;</a>
                @else
                    <span class="rounded-full border border-line px-3 py-1.5 text-mute/50">Berikutnya &raquo;</span>
                @endif
            </nav>
        @endif
    </div>
@endif
