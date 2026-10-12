@props([
    'name' => '',
    'initials' => null,
    'size' => 'default',
    'tone' => 'lime',
])

@php
    $computedInitials = $initials ?: collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => str($part)->substr(0, 1)->upper()->toString())
        ->implode('');
    $toneClass = match ($tone) {
        'ink' => 'bg-ink text-lime',
        'orange' => 'bg-orange text-ink',
        'yellow' => 'bg-yellow text-ink',
        default => 'bg-lime-soft text-lime-dark',
    };
    $sizeClass = match ($size) {
        'sm' => 'size-8 text-[10px]',
        'lg' => 'size-12 text-sm',
        default => 'size-10 text-xs',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-semibold '.$sizeClass.' '.$toneClass]) }} aria-label="{{ $name ?: 'Avatar' }}">
    {{ $computedInitials ?: '?' }}
</span>
