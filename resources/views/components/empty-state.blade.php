@props([
    'title' => 'Belum ada data',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'resetHref' => null,
])

<div {{ $attributes->merge(['class' => 'module-empty-state']) }}>
    <div class="module-empty-icon" aria-hidden="true">⌁</div>
    <h3>{{ $title }}</h3>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="module-button module-button-primary">{{ $actionLabel }}</a>
    @elseif ($resetHref)
        <a href="{{ $resetHref }}" class="module-button module-button-secondary">Reset filter</a>
    @endif
</div>
