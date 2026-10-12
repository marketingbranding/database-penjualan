@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'padding' => 'p-6',
])

@php($isFlush = $padding === 'p-0')

<section {{ $attributes->merge(['class' => 'workspace-card '.$padding]) }}>
    @if ($eyebrow || $title || $description || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-4 {{ $isFlush ? 'px-6 pt-6' : 'mb-5' }}">
            <div class="min-w-0">
                @if ($eyebrow)
                    <p class="text-xs font-medium text-muted">{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <h2 class="mt-1 text-lg font-semibold tracking-tight text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">{{ $description }}</p>
                @endif
            </div>

            @if (isset($actions))
                <div class="shrink-0">{{ $actions }}</div>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
