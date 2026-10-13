@props(['disabled' => false])

@php
    $isDateInput = $attributes->get('type') === 'date';
    $inputId = $attributes->get('id');
    $inputName = $attributes->get('name');
    $inputValue = (string) ($attributes->get('value') ?? '');
    $displayValue = preg_match('/^\d{4}-\d{2}-\d{2}$/', $inputValue)
        ? implode('/', array_reverse(explode('-', $inputValue)))
        : $inputValue;
    $visibleAttributes = $attributes->except(['type', 'id', 'name', 'value', 'lang', 'x-model']);
@endphp

@if ($isDateInput)
    <div
        x-data="{
            iso: @js($inputValue),
            display: @js($displayValue),
            parse(value) {
                const match = value.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (!match) return '';

                const [, day, month, year] = match;
                const date = new Date(Number(year), Number(month) - 1, Number(day));
                if (date.getFullYear() !== Number(year) || date.getMonth() !== Number(month) - 1 || date.getDate() !== Number(day)) return '';

                return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            },
            sync(value) {
                const parsed = this.parse(value);
                if (!parsed) return;

                this.iso = parsed;
                this.display = parsed.split('-').reverse().join('/');
                this.$refs.display.value = this.display;
                this.$refs.picker.value = parsed;
                this.$refs.value.value = parsed;
                this.$refs.value.dispatchEvent(new Event('input', { bubbles: true }));
            },
            select(value) {
                this.iso = value;
                this.display = value ? value.split('-').reverse().join('/') : '';
                this.$refs.display.value = this.display;
                this.$refs.value.value = value;
                this.$refs.value.dispatchEvent(new Event('input', { bubbles: true }));
            },
            openPicker() {
                if (this.$refs.picker.showPicker) {
                    this.$refs.picker.showPicker();
                } else {
                    this.$refs.picker.focus();
                }
            }
        }"
        class="relative flex items-center gap-2"
    >
        <input
            id="{{ $inputId }}"
            x-ref="display"
            type="text"
            value="{{ $displayValue }}"
            placeholder="dd/mm/yyyy"
            inputmode="numeric"
            autocomplete="off"
            @input="display = $event.target.value; sync($event.target.value)"
            @blur="sync($event.target.value)"
            @disabled($disabled)
            {{ $visibleAttributes->merge(['class' => 'workspace-input w-full']) }}
        >
        <input
            x-ref="value"
            type="hidden"
            name="{{ $inputName }}"
            value="{{ $inputValue }}"
            @if ($attributes->has('x-model')) x-model="{{ $attributes->get('x-model') }}" @endif
        >
        <input x-ref="picker" type="date" value="{{ $inputValue }}" class="sr-only" tabindex="-1" aria-hidden="true" @change="select($event.target.value)">
        <button type="button" class="absolute right-2 rounded p-1 text-muted transition hover:bg-canvas hover:text-ink" aria-label="Pilih tanggal" @click="openPicker()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M8 3v3M16 3v3M3 9h18M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    </div>
@else
    <input @disabled($disabled) {{ $attributes->merge(['class' => 'workspace-input']) }}>
@endif
