@props([
    'lead',
    'href' => null,
    'draggable' => false,
])

@php
    $tag = $href ? 'a' : 'article';
    $lastActivity = $lead->relationLoaded('latestActivity')
        ? ($lead->latestActivity?->happened_at ?? $lead->updated_at)
        : $lead->updated_at;
    $salesName = $lead->relationLoaded('salesPic') ? $lead->salesPic?->name : null;
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($draggable) draggable="true" @endif
    data-lead-card
    data-lead-id="{{ $lead->getKey() }}"
    {{ $attributes->merge(['class' => 'group block rounded-2xl border border-line bg-surface p-4 transition duration-150 hover:-translate-y-0.5 hover:border-lime hover:bg-lime-soft/30']) }}
>
    <div class="flex items-start gap-3">
        <x-ui.avatar :name="$lead->nama_konsumen" size="sm" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-ink">{{ $lead->nama_konsumen }}</p>
            <p class="mt-0.5 truncate text-xs text-muted">{{ $lead->id_lead }}</p>
        </div>
        <x-ui.stage-chip :stage="$lead->status_lead" size="sm" />
    </div>

    <div class="mt-4 flex items-center justify-between gap-3 text-xs text-muted">
        <span class="truncate">{{ $salesName ?? 'Sales belum ditentukan' }}</span>
        <time datetime="{{ $lastActivity?->toIso8601String() }}">{{ $lastActivity?->diffForHumans() ?? 'Belum diperbarui' }}</time>
    </div>
</{{ $tag }}>
