@props([
    'primaryLabel' => null,
    'primaryHref' => null,
])

<div {{ $attributes->merge(['class' => 'module-row-actions']) }}>
    @if ($primaryLabel && $primaryHref)
        <a href="{{ $primaryHref }}" class="module-row-primary">{{ $primaryLabel }}</a>
    @elseif (isset($primary))
        {{ $primary }}
    @endif

    @if (isset($menu))
        <details class="module-row-menu">
            <summary aria-label="Aksi lainnya">⋯</summary>
            <div class="module-row-menu-panel">{{ $menu }}</div>
        </details>
    @endif
</div>
