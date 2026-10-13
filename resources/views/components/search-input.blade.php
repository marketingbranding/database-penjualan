@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Cari...',
])

<label {{ $attributes->merge(['class' => 'module-search-input']) }}>
    <span aria-hidden="true">⌕</span>
    <input type="search" name="{{ $name }}" value="{{ $value ?? request($name) }}" placeholder="{{ $placeholder }}">
</label>
