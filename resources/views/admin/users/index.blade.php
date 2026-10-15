<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Pengguna" description="Kelola cabang, tim, role, dan akses pengguna dari satu tempat.">
            <x-slot name="actions">
                @can('users.create')
                    <a href="{{ route('admin.users.create') }}" class="module-button module-button-primary">＋ Tambah pengguna</a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $branchOptions = ['' => 'Semua'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all();
        $roleOptions = ['' => 'Semua'] + $globalRoles->mapWithKeys(fn ($role): array => [$role->id => $role->name])->all();
        $hasActiveFilters = filled($search) || filled($branchId) || filled($globalRoleId);
    @endphp

    <div class="module-page" x-data="{ selectedIds: [], action: 'assign-branch', allIds: @js($users->pluck('id')->values()->all()) }" @module-bulk-clear.window="selectedIds = []">
        @if (session('status') === 'users-branch-assigned')
            <x-ui.toast message="{{ session('count') }} pengguna dipindahkan ke cabang terpilih." />
        @endif
        @if (session('status') === 'users-deleted')
            <x-ui.toast message="{{ session('count') }} pengguna dihapus{{ session('skipped') ? ', '.session('skipped').' dilewati (diri sendiri / super admin terakhir)' : '' }}." />
        @endif

        <x-table-card class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar
                    action="{{ route('admin.users.index') }}"
                    :result-count="$users->total()"
                    result-label="pengguna"
                    :has-active-filters="$hasActiveFilters"
                    reset-href="{{ route('admin.users.index') }}"
                >
                    <x-slot name="search"><x-search-input name="search" :value="$search" placeholder="Cari nama atau email" /></x-slot>
                    <x-slot name="filters">
                        <x-filter-pill label="Cabang" name="branch_id" :options="$branchOptions" :selected="$branchId" :active="filled($branchId)" />
                        <x-filter-pill label="Role global" name="global_role_id" :options="$roleOptions" :selected="$globalRoleId" :active="filled($globalRoleId)" />
                    </x-slot>
                </x-toolbar>
            </x-slot>

            <x-data-table caption="Daftar pengguna">
                <x-slot name="header">
                    <tr>
                        <th class="w-10"><input type="checkbox" class="module-checkbox" @change="selectedIds = $event.target.checked ? allIds : []" aria-label="Pilih semua pengguna"></th>
                        <th>Pengguna</th>
                        <th>Role global</th>
                        <th>Cabang</th>
                        <th>Tim</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </x-slot>
                <x-slot name="body">
                    @forelse ($users as $user)
                        <tr :class="selectedIds.includes({{ $user->id }}) && 'is-selected'">
                            <td><input type="checkbox" name="ids[]" value="{{ $user->id }}" form="bulk-users-form" data-user-select class="module-checkbox" x-model="selectedIds" aria-label="Pilih {{ $user->name }}"></td>
                            <td>
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <x-ui.avatar :name="$user->name" size="sm" :tone="['lime', 'orange', 'yellow'][$user->id % 3] ?? 'lime'" />
                                    <div class="min-w-0"><div class="truncate font-semibold text-ink" title="{{ $user->name }}">{{ $user->name }}</div><div class="truncate text-[11px] text-muted">{{ $user->email }}</div></div>
                                </div>
                            </td>
                            <td>
                                <div class="flex max-w-[180px] flex-wrap gap-1">
                                    @forelse ($user->globalRoles as $role)
                                        <x-role-chip :role="$role->name" />
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="max-w-[150px] truncate text-ink" title="{{ $user->branch?->name ?? '-' }}"><span class="module-branch-cell"><span class="module-branch-dot" aria-hidden="true"></span>{{ $user->branch?->name ?? '-' }}</span></td>
                            <td class="max-w-[180px] truncate text-ink" title="{{ $user->teams->pluck('name')->join(', ') ?: '-' }}">{{ $user->teams->pluck('name')->join(', ') ?: '-' }}</td>
                            <td>
                                <x-row-actions>
                                    <x-slot name="primary"><a href="{{ route('admin.users.edit', $user) }}" class="module-row-primary"><svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4L14.5 12l-2.5-2.5 2.7-3.2Z" /></svg>Kelola akses</a></x-slot>
                                    <x-slot name="menu">
                                        @if (Auth::user()->hasGlobalRole('super_admin') && ! $user->hasGlobalRole('super_admin') && ! Auth::user()->is($user))
                                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">@csrf<button type="submit" class="module-menu-link">Login sebagai</button></form>
                                        @endif
                                        @can('users.delete')
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?')">@csrf @method('DELETE')<button type="submit" class="module-menu-link is-danger">Hapus pengguna</button></form>
                                        @endcan
                                    </x-slot>
                                </x-row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada pengguna sesuai filter" :reset-href="$hasActiveFilters ? route('admin.users.index') : null" action-label="Tambah pengguna" action-href="{{ ! $hasActiveFilters && Auth::user()->can('users.create') ? route('admin.users.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($users as $user)
                        <x-mobile.data-card class="users-mobile-card" :title="$user->name" :subtitle="($user->branch?->name ?? '-').' · '.($user->teams->pluck('name')->join(', ') ?: 'Tanpa tim')" :chip="$user->globalRoles->pluck('name')->join(', ') ?: 'Tanpa role'" selected-expression="selectedIds.includes({{ $user->id }})">
                            <x-slot name="leading"><input type="checkbox" name="ids[]" value="{{ $user->id }}" form="bulk-users-form" data-user-select class="module-checkbox" x-model="selectedIds" aria-label="Pilih {{ $user->name }}"></x-slot>
                            <x-slot name="actions">
                                <a href="{{ route('admin.users.edit', $user) }}">Kelola akses</a>
                                @if (Auth::user()->hasGlobalRole('super_admin') && ! $user->hasGlobalRole('super_admin') && ! Auth::user()->is($user))
                                    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">@csrf<button type="submit">Login sebagai</button></form>
                                @endif
                                @can('users.delete')<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?')">@csrf @method('DELETE')<button type="submit">Hapus pengguna</button></form>@endcan
                            </x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada pengguna sesuai filter" :reset-href="$hasActiveFilters ? route('admin.users.index') : null" action-label="Tambah pengguna" action-href="{{ ! $hasActiveFilters && Auth::user()->can('users.create') ? route('admin.users.create') : null }}" />
                    @endforelse
                </x-slot>
            </x-data-table>

            <x-slot name="footer">
                <x-pagination :paginator="$users" />
            </x-slot>
        </x-table-card>

        <form id="bulk-users-form" method="POST" action="{{ route('admin.users.bulk') }}" onsubmit="return confirm('Terapkan aksi massal pada pengguna terpilih?')">
            @csrf
            <x-bulk-bar dynamic count-expression="selectedIds.length" select-all-expression="allIds">
                <x-slot name="action">
                    <select name="action" x-model="action" class="module-bulk-select" aria-label="Aksi massal">
                        <option value="assign-branch">Pindahkan ke cabang</option>
                        <option value="delete">Hapus</option>
                    </select>
                    <select name="branch_id" x-cloak x-show="action === 'assign-branch'" class="module-bulk-select" aria-label="Cabang tujuan">
                        <option value="">Pilih cabang tujuan</option>
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                    </select>
                </x-slot>
                <x-slot name="submit"><button type="submit" class="module-bulk-submit">Terapkan</button></x-slot>
            </x-bulk-bar>
        </form>
    </div>
</x-app-layout>
