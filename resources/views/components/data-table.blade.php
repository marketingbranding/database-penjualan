@props([
    'compact' => false,
    'caption' => null,
])

<div class="module-data-table-wrap">
    <table {{ $attributes->merge(['class' => 'module-data-table '.($compact ? 'is-compact' : '')]) }}>
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        <thead>
            {{ $header ?? '' }}
        </thead>
        <tbody>
            {{ $body ?? $slot }}
        </tbody>
    </table>
</div>
