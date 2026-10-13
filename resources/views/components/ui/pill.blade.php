@props([
    'tone' => 'neutral',
])

@php
    $toneClass = match ($tone) {
        'lime' => 'bg-lime-soft text-lime-dark',
        'orange' => 'bg-orange/20 text-ink',
        'yellow' => 'bg-yellow/30 text-ink',
        'ink' => 'bg-ink text-white',
        'success' => 'bg-lime-soft text-lime-dark',
        'warning' => 'bg-orange/20 text-ink',
        'error' => 'bg-danger/10 text-danger',
        default => 'bg-canvas text-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-[9px] px-2.5 py-1 text-xs font-medium '.$toneClass]) }}>
    {{ $slot }}
</span>
