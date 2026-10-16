@props([
    'label',
    'value' => null,
    'active' => false,
    'clearHref' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
])

<label data-filter-label="{{ $label }}" {{ $attributes->merge(['class' => 'module-filter-pill '.($active ? 'is-active' : '')]) }}>
    <span>{{ $label }}:</span>
    @if ($name && $options)
        <select name="{{ $name }}" data-auto-submit aria-label="{{ $label }}" data-filter-label="{{ $label }}">
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($value)
        <strong>{{ $value }}</strong>
    @endif
    @if ($active && $clearHref)
        <a href="{{ $clearHref }}" class="module-filter-clear" aria-label="Hapus filter {{ $label }}">×</a>
    @else
        <span class="module-filter-chevron" aria-hidden="true">⌄</span>
    @endif
</label>
