@props([
    'message',
    'actionLabel' => null,
    'actionHref' => null,
    'variant' => 'success',
    'show' => true,
])

@php
    $variantClass = match ($variant) {
        'warning' => 'border-yellow/60 bg-yellow/20 text-ink',
        'error' => 'border-danger/40 bg-danger/10 text-danger',
        default => 'border-lime/60 bg-lime-soft text-ink',
    };
@endphp

@if ($show)
    <div x-data="{ visible: true }" x-show="visible" x-transition.opacity role="status" {{ $attributes->merge(['class' => 'flex max-w-md items-center gap-3 rounded-2xl border px-4 py-3 text-sm shadow-lg '.$variantClass]) }}>
        <span class="min-w-0 flex-1">{{ $message }}</span>
        @if ($actionLabel && $actionHref)
            <a href="{{ $actionHref }}" class="shrink-0 font-semibold underline underline-offset-2">{{ $actionLabel }}</a>
        @endif
        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-xl transition hover:bg-black/5" @click="visible = false" aria-label="Tutup pemberitahuan">&times;</button>
    </div>
@endif
