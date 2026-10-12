@props([
    'title',
    'count' => 0,
    'stage' => null,
    'description' => null,
    'droppable' => false,
])

@php
    $stageKey = str($stage ?: $title)->lower()->toString();
    $dotClass = match ($stageKey) {
        'tatap muka' => 'bg-stage-face-to-face',
        'cek lokasi' => 'bg-stage-site-visit',
        'cek slik' => 'bg-stage-slik',
        'utj' => 'bg-stage-utj',
        'akad' => 'bg-stage-akad',
        'jadi freelance' => 'bg-stage-freelance',
        default => 'bg-muted',
    };
@endphp

<section {{ $attributes->merge(['class' => 'flex min-h-72 min-w-[260px] flex-1 flex-col rounded-2xl bg-canvas/70 p-3']) }} data-kanban-column="{{ $stage ?: $title }}">
    <header class="flex items-start justify-between gap-3 px-2 pb-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="size-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                <h2 class="truncate text-sm font-semibold text-ink">{{ $title }}</h2>
                <span class="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-muted">{{ $count }}</span>
            </div>
            @if ($description)
                <p class="mt-1 text-xs text-muted">{{ $description }}</p>
            @endif
        </div>
    </header>

    <div class="flex min-h-0 flex-1 flex-col gap-3" data-kanban-items>
        {{ $slot }}
        @if ($droppable)
            <div data-drop-zone class="grid min-h-20 place-items-center rounded-2xl border border-dashed border-lime bg-lime-soft/50 px-4 py-5 text-center text-xs font-medium text-lime-dark">
                Lepas di sini
            </div>
        @endif
    </div>
</section>
