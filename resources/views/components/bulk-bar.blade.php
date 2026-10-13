@props([
    'selectedCount' => 0,
    'dynamic' => false,
    'countExpression' => null,
])

<div {{ $attributes->merge(['class' => 'module-bulk-bar']) }} @if (! $dynamic && $selectedCount < 1) hidden @endif data-selected-count="{{ $selectedCount }}" @if ($dynamic) x-show="{{ $countExpression }} > 0" @endif>
    <span><strong @if ($dynamic && $countExpression) x-text="{{ $countExpression }}" @endif>{{ $selectedCount }}</strong> dipilih</span>
    @isset($action)
        <div class="module-bulk-action">{{ $action }}</div>
    @endisset
    @isset($submit)
        {{ $submit }}
    @endisset
    <button type="button" class="module-bulk-dismiss" aria-label="Batalkan pilihan" @click="$dispatch('module-bulk-clear')">×</button>
</div>
