@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-muted']) }}>
    {{ $value ?? $slot }}
</label>
