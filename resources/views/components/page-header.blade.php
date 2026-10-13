@props([
    'title',
    'description' => null,
    'backHref' => null,
    'backLabel' => 'Kembali',
])

<header {{ $attributes->merge(['class' => 'module-page-header']) }}>
    <div class="min-w-0">
        <h1 class="module-page-title">{{ $title }}</h1>
        @if ($description)
            <p class="module-page-description">{{ $description }}</p>
        @endif
    </div>

    @if ($backHref || isset($actions))
        <div class="module-page-actions">
            @if ($backHref)
                <a href="{{ $backHref }}" class="module-button module-button-secondary">{{ $backLabel }}</a>
            @endif
            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    @endif
</header>
