@props([
    'role',
    'tone' => null,
])

@php
    $roleTone = $tone ?: match (strtolower((string) $role)) {
        'super_admin', 'super admin', 'admin' => 'ink',
        'sas' => 'yellow',
        'sales' => 'lime',
        'spv', 'supervisor' => 'lime',
        'koordinator_sales', 'koordinator', 'coordinator' => 'peach',
        'manager', 'manajer' => 'lime',
        default => 'neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'module-role-chip tone-'.$roleTone]) }}>{{ $role }}</span>
