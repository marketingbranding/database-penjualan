@props([
    'title',
    'subtitle' => null,
    'value' => null,
    'valueLabel' => null,
    'chip' => null,
    'href' => null,
    'selectedExpression' => null,
])

<article {{ $attributes->merge(['class' => 'module-mobile-data-card']) }} @if ($selectedExpression):class="{ 'is-selected': {{ $selectedExpression }} }"@endif>
    <div class="mobile-data-card-main">
        @isset($leading)
            <div class="mobile-data-card-leading">{{ $leading }}</div>
        @endisset
        <div class="mobile-data-card-identity">
            @if ($href)
                <a href="{{ $href }}" class="mobile-data-card-link">
                    <strong class="mobile-data-card-title" title="{{ $title }}">{{ $title }}</strong>
                    @if ($subtitle)<span class="mobile-data-card-subtitle" title="{{ $subtitle }}">{{ $subtitle }}</span>@endif
                </a>
            @else
                <strong class="mobile-data-card-title" title="{{ $title }}">{{ $title }}</strong>
                @if ($subtitle)<span class="mobile-data-card-subtitle" title="{{ $subtitle }}">{{ $subtitle }}</span>@endif
            @endif
            @if ($chip)<span class="mobile-data-card-chip">{{ $chip }}</span>@endif
        </div>
        @if ($value !== null)
            <div class="mobile-data-card-value"><small>{{ $valueLabel }}</small><strong>{{ $value }}</strong></div>
        @endif
        @isset($trailing)
            <div class="mobile-data-card-trailing">{{ $trailing }}</div>
        @endisset
    </div>
    @isset($metrics)
        <div class="mobile-data-card-metrics">{{ $metrics }}</div>
    @endisset
    @isset($details)
        <div class="mobile-data-card-details">{{ $details }}</div>
    @endisset
    @isset($actions)
        <div class="mobile-data-card-actions">{{ $actions }}</div>
    @endisset
</article>
