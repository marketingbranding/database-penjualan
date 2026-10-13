@props([
    'title' => null,
    'description' => null,
    'count' => null,
])

<section {{ $attributes->merge(['class' => 'module-table-card']) }}>
    @if ($title || $description || $count !== null || isset($toolbar))
        <header class="module-table-card-header">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-xs text-muted">{{ $description }}</p>
                @endif
            </div>
            @if ($count !== null)
                <span class="text-xs text-muted">{{ $count }}</span>
            @endif
        </header>
    @endif

    @isset($toolbar)
        <div class="module-table-toolbar">{{ $toolbar }}</div>
    @endisset

    <div class="module-table-card-body">{{ $slot }}</div>

    @isset($footer)
        <footer class="module-table-card-footer">{{ $footer }}</footer>
    @endisset
</section>
