@props([
    'method' => 'GET',
    'action' => null,
    'resultCount' => null,
    'resultLabel' => 'hasil',
    'hasActiveFilters' => false,
    'resetHref' => null,
])

<form method="{{ strtoupper($method) }}" action="{{ $action }}" x-data="{ filtersOpen: false }" {{ $attributes->merge(['class' => 'module-toolbar']) }}>
    <div class="module-toolbar-controls">
        @isset($search)
            {{ $search }}
        @endisset
        @if (isset($filters))
            <button type="button" class="module-toolbar-filter-toggle" @click="filtersOpen = ! filtersOpen">Filter</button>
            <div class="module-toolbar-filter-sheet" :class="filtersOpen && 'is-open'">
                {{ $filters }}
            </div>
        @endif
        @if (! isset($search) && ! isset($filters))
            {{ $slot }}
        @endif
    </div>
    <div class="module-toolbar-result">
        @if ($resultCount !== null)
            <span>{{ $resultCount }} {{ $resultLabel }}</span>
        @endif
        @if ($hasActiveFilters && $resetHref)
            <a href="{{ $resetHref }}" class="font-semibold text-lime-dark">Reset</a>
        @endif
    </div>
</form>
