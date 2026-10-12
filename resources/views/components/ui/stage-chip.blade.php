@props([
    'stage' => null,
    'label' => null,
    'size' => 'default',
])

@php
    $stageKey = str($stage)->lower()->toString();
    $stageClass = match ($stageKey) {
        'tatap muka' => 'bg-stage-face-to-face text-ink',
        'cek lokasi' => 'bg-stage-site-visit text-ink',
        'cek slik' => 'bg-stage-slik text-ink',
        'utj' => 'bg-stage-utj text-ink',
        'akad' => 'bg-stage-akad text-white',
        'jadi freelance' => 'bg-stage-freelance text-ink',
        default => 'bg-canvas text-muted',
    };
    $sizeClass = $size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs';
@endphp

<span {{ $attributes->merge(['class' => 'data-stripes inline-flex max-w-full items-center rounded-[9px] font-medium '.$sizeClass.' '.$stageClass]) }}>
    <span class="truncate">{{ $label ?? $stage ?? 'Belum ada tahap' }}</span>
</span>
