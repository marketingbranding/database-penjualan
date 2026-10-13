@props([
    'label',
    'value',
    'description' => null,
    'tone' => 'surface',
    'href' => null,
])

@php
    $toneClass = match ($tone) {
        'lime' => 'data-stripes border-lime-dark/20 bg-lime text-on-accent',
        'ink' => 'border-inverse bg-inverse text-inverse-text',
        'orange' => 'data-stripes border-orange/30 bg-orange/20 text-ink',
        'yellow' => 'data-stripes border-yellow/40 bg-yellow/30 text-ink',
        default => 'border-line bg-surface text-ink',
    };
    $mutedClass = $tone === 'ink' ? 'text-inverse-text/60' : 'text-muted';
@endphp

<article {{ $attributes->merge(['class' => 'rounded-3xl border p-5 '.$toneClass.' '.($tone === 'ink' ? 'ui-stat-card-tone-ink' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium">{{ $label }}</p>
        @isset($icon)
            <span class="ui-stat-icon grid size-8 place-items-center rounded-xl {{ $tone === 'ink' ? 'bg-inverse-text/10 text-lime' : 'bg-surface-3 text-lime-dark' }}">
                {{ $icon }}
            </span>
        @endisset
    </div>
    <p class="mt-6 text-4xl font-semibold tracking-tight">{{ $value }}</p>
    @if ($description)
        <p class="ui-stat-muted mt-4 text-xs {{ $mutedClass }}">{{ $description }}</p>
    @endif
    @if ($href)
        <a href="{{ $href }}" class="mt-4 inline-flex text-xs font-semibold underline underline-offset-4">Buka detail</a>
    @endif
</article>
