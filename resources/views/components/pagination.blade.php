@props([
    'paginator',
])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = range(max(1, $current - 1), min($last, $current + 1));
        $pages = array_unique(array_merge([1], $window, [$last]));
        sort($pages);
        $previous = null;
    @endphp
    <nav {{ $attributes->merge(['class' => 'module-pagination']) }} aria-label="Pagination">
        <p>Menampilkan {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }}</p>
        <div class="module-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-hidden="true">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" aria-label="Halaman sebelumnya">‹</a>
            @endif

            @foreach ($pages as $page)
                @if ($previous !== null && $page > $previous + 1)
                    <span class="is-ellipsis" aria-hidden="true">…</span>
                @endif
                @if ($page === $current)
                    <span class="is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
                @php($previous = $page)
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" aria-label="Halaman berikutnya">›</a>
            @else
                <span class="is-disabled" aria-hidden="true">›</span>
            @endif
        </div>
    </nav>
@endif
