@props([
    'variant' => 'mark',
    'size' => 'md',
])

@php
    $variant = in_array($variant, ['lockup', 'mark'], true) ? $variant : 'mark';
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $source = resource_path('brand/oasis-next-'.($variant === 'lockup' ? 'logo' : 'mark').'.svg');
    $svg = file_get_contents($source);
    preg_match('/viewBox="([^"]+)"/', $svg, $viewBoxMatch);
    $viewBox = $viewBoxMatch[1] ?? ($variant === 'lockup' ? '0 0 271 60' : '0 0 120 120');
    $svg = preg_replace('/^<svg\b[^>]*>/i', '', $svg, 1);
    $svg = preg_replace('/<\/svg>\s*$/i', '', $svg, 1);
    $svg = str_replace('fill="#1d2219"', 'fill="rgb(var(--c-brand-oasis))"', $svg);
    $nextFill = 'fill="#5f9a2a"';
    $nextPosition = strrpos($svg, $nextFill);
    if ($nextPosition !== false) {
        $svg = substr_replace($svg, 'fill="rgb(var(--c-brand-next))"', $nextPosition, strlen($nextFill));
    }
    $isDecorative = $attributes->get('aria-hidden') === 'true';
    $titleId = 'brand-logo-title-'.str()->uuid();
    $sizeClasses = [
        'mark' => ['sm' => 'h-8 w-8', 'md' => 'h-10 w-10', 'lg' => 'h-12 w-12'],
        'lockup' => ['sm' => 'h-8 w-auto', 'md' => 'h-10 w-auto', 'lg' => 'h-12 w-auto'],
    ];
    $logoAttributes = $attributes->class(['brand-logo', "brand-logo-{$variant}", $sizeClasses[$variant][$size]]);
    if (! $isDecorative) {
        $logoAttributes = $logoAttributes->merge(['role' => 'img', 'aria-labelledby' => $titleId]);
    }
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="{{ $viewBox }}" {!! $logoAttributes !!}>
    @unless ($isDecorative)
        <title id="{{ $titleId }}">Oasis Next</title>
    @endunless
    {!! $svg !!}
</svg>
