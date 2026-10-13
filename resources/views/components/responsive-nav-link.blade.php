@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-lime bg-lime-soft py-2 ps-3 pe-4 text-start text-base font-medium text-ink transition duration-150 ease-in-out focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-muted transition duration-150 ease-in-out hover:border-line hover:bg-canvas hover:text-ink focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
