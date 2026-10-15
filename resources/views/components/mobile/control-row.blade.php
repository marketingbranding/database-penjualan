@props([
    'filterCount' => 0,
    'period' => null,
])

<div {{ $attributes->merge(['class' => 'mobile-control-row']) }}>
    @isset($filter)
        {{ $filter }}
    @endisset
    @if ($period)
        <span class="mobile-period-chip">{{ $period }}</span>
    @endif
    @isset($actions)
        {{ $actions }}
    @endisset
</div>
