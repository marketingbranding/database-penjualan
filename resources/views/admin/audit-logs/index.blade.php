<x-app-layout>
    <x-slot name="header"><x-page-header title="Audit log" description="Telusuri perubahan data, aktor, dan detail aktivitas operasional." /></x-slot>

    @php($hasActiveFilters = filled($branchId) || filled($event) || filled($search))
    <div class="module-page">
        <x-table-card title="Aktivitas tercatat" :count="number_format($logs->total()).' aktivitas'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('admin.audit-logs.index') }}" :result-count="$logs->total()" result-label="aktivitas" :has-active-filters="$hasActiveFilters" reset-href="{{ route('admin.audit-logs.index') }}">
                    <x-slot name="search"><x-search-input name="search" :value="$search" placeholder="Cari deskripsi atau user" /></x-slot>
                    <x-slot name="filters">
                        @if (Auth::user()->canAccessAllBranches())<x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$branchId" :active="filled($branchId)" />@endif
                        <x-filter-pill label="Event" name="event" :options="['' => 'Semua event'] + collect($events)->mapWithKeys(fn ($option): array => [$option => $option])->all()" :selected="$event" :active="filled($event)" />
                    </x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Audit log" class="is-audit">
                <x-slot name="header"><tr><th>Waktu</th><th>User</th><th>Cabang</th><th>Event</th><th>Perubahan</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap text-muted">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td><div class="font-semibold">{{ $log->user?->name ?? 'Sistem' }}</div><div class="text-[11px] text-muted">{{ $log->ip_address }}</div></td>
                            <td class="max-w-[150px] truncate text-muted" title="{{ $log->branch?->name ?? '-' }}">{{ $log->branch?->name ?? '-' }}</td>
                            <td><x-status-badge :status="$log->event" tone="neutral" /><p class="mt-1 max-w-xs truncate text-[11px] text-muted" title="{{ $log->description }}">{{ $log->description }}</p></td>
                            <td><details class="module-audit-detail"><summary>Lihat detail</summary><div class="module-audit-detail-content"><div><p>Sebelum</p><pre>{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div><div><p>Sesudah</p><pre>{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div></details></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Belum ada aktivitas tercatat" :reset-href="$hasActiveFilters ? route('admin.audit-logs.index') : null" /></td></tr>
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$logs" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
