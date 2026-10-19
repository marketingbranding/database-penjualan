@props([
    'method' => 'GET',
    'action' => null,
    'resultCount' => null,
    'resultLabel' => 'hasil',
    'hasActiveFilters' => false,
    'resetHref' => null,
    'filterTitle' => 'Filter',
])

<form method="{{ strtoupper($method) }}" action="{{ $action }}" data-mobile-filter-form data-filter-title="{{ $filterTitle }}" data-reset-href="{{ $resetHref }}" {{ $attributes->merge(['class' => 'module-toolbar']) }}>
    <div class="module-toolbar-controls">
        @isset($search)
            {{ $search }}
        @endisset
        @if (isset($filters))
            <button type="button" class="module-toolbar-desktop-filter-toggle" data-desktop-filter-toggle aria-expanded="false" aria-controls="desktop-filters-{{ substr(md5((string) $action), 0, 8) }}" hidden>
                <span aria-hidden="true">☷</span>
                Filter
                <span class="module-toolbar-filter-count" data-desktop-filter-count hidden>0</span>
            </button>
            <button type="button" class="module-toolbar-filter-toggle" data-mobile-filter-open aria-expanded="false">
                <span aria-hidden="true">☷</span>
                Filter
                <span class="module-toolbar-filter-count" data-filter-count @if (! $hasActiveFilters) hidden @endif>0</span>
            </button>
        @endif
    </div>

    @if (isset($filters))
        <div id="desktop-filters-{{ substr(md5((string) $action), 0, 8) }}" class="module-toolbar-desktop-filters" data-toolbar-desktop-filters>
            {{ $filters }}
        </div>

        <div class="module-toolbar-active-filters" data-mobile-active-filters aria-label="Filter aktif"></div>

        <div class="module-toolbar-filter-backdrop" data-mobile-filter-backdrop hidden></div>
        <section class="module-toolbar-filter-sheet" data-mobile-filter-sheet role="dialog" aria-modal="true" aria-labelledby="mobile-filter-title" tabindex="-1" hidden>
            <div class="module-toolbar-filter-grabber" aria-hidden="true"></div>
            <header class="module-toolbar-filter-sheet-header" data-mobile-filter-header>
                <div>
                    <h2 id="mobile-filter-title">{{ $filterTitle }}</h2>
                    <p data-filter-selected-label>0 filter dipilih</p>
                </div>
                <button type="button" class="module-toolbar-filter-close" data-mobile-filter-close aria-label="Tutup filter">×</button>
            </header>
            <div class="module-toolbar-filter-sheet-body" data-mobile-filter-body>
                <div class="module-toolbar-mobile-filters" data-toolbar-mobile-filters>
                    @isset($mobileFilters)
                        {{ $mobileFilters }}
                    @else
                        {{ $filters }}
                    @endisset
                </div>
            </div>
            <footer class="module-toolbar-filter-sheet-footer" data-mobile-filter-sheet-footer>
                <button type="button" class="module-toolbar-filter-reset" data-mobile-filter-reset>Reset</button>
                <button type="submit" class="module-toolbar-filter-apply" data-mobile-filter-apply>Terapkan</button>
            </footer>
        </section>
    @endif

    @if (! isset($search) && ! isset($filters))
        <div class="module-toolbar-controls-extra">
            {{ $slot }}
        </div>
    @endif

    @if ($hasActiveFilters && $resetHref)
        <div class="module-toolbar-result">
            <a href="{{ $resetHref }}" class="font-semibold text-lime-dark">Reset</a>
        </div>
    @endif
</form>
