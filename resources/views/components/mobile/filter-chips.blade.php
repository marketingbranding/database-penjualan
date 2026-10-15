@props([
    'chips' => [],
])

<div {{ $attributes->merge(['class' => 'mobile-filter-chips']) }} role="list" aria-label="Filter cepat">
    @foreach ($chips as $chip)
        @php($chip = is_array($chip) ? $chip : ['label' => $chip])
        @if (! empty($chip['href']))
            <a href="{{ $chip['href'] }}" class="mobile-filter-chip {{ ! empty($chip['active']) ? 'is-active' : '' }}" role="listitem">{{ $chip['label'] }}</a>
        @else
            <button type="button" class="mobile-filter-chip {{ ! empty($chip['active']) ? 'is-active' : '' }}" role="listitem" @if (! empty($chip['action'])){!! $chip['action'] !!}@endif>{{ $chip['label'] }}</button>
        @endif
    @endforeach
</div>
