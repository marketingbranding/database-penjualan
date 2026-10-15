@props([
    'open' => 'false',
    'title' => null,
    'description' => null,
    'close' => 'closeMobileSheet()',
])

<section x-cloak x-show="{{ $open }}" x-transition:enter="mobile-sheet-enter" x-transition:enter-start="mobile-sheet-enter-start" x-transition:enter-end="mobile-sheet-enter-end" x-transition:leave="mobile-sheet-leave" x-transition:leave-start="mobile-sheet-leave-start" x-transition:leave-end="mobile-sheet-leave-end" class="mobile-sheet mobile-shared-sheet" role="dialog" aria-modal="true" @click.stop>
    <div class="mobile-sheet-grabber" aria-hidden="true"></div>
    <div class="mobile-sheet-content">
        @if ($title || $description || isset($header))
            <header class="mobile-sheet-header">
                <div class="min-w-0">
                    @if (isset($header))
                        {{ $header }}
                    @else
                        @if ($description)<p class="mobile-sheet-eyebrow">{{ $description }}</p>@endif
                        @if ($title)<h2 class="truncate">{{ $title }}</h2>@endif
                    @endif
                </div>
                <button type="button" class="mobile-sheet-close" @click="{{ $close }}" aria-label="Tutup panel">×</button>
            </header>
        @endif
        <div class="mobile-shared-sheet-body">{{ $slot }}</div>
        @isset($actions)
            <footer class="mobile-sheet-actions">{{ $actions }}</footer>
        @endisset
    </div>
</section>
