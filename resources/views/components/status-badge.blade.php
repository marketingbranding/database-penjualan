@props([
    'status',
    'tone' => null,
])

@php
    $statusTone = $tone ?: match (strtolower((string) $status)) {
        'aktif', 'active', 'lanjut', 'selesai', 'completed' => 'success',
        'akan datang', 'upcoming', 'proses', 'pending' => 'warning',
        'berakhir', 'expired', 'nonaktif', 'inactive' => 'neutral',
        'batal', 'dibatalkan', 'gagal', 'reject', 'rejected' => 'danger',
        default => 'neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'module-status-badge tone-'.$statusTone]) }}>{{ $status }}</span>
