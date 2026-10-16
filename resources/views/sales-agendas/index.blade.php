<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Agenda sales" description="Jaga follow-up lapangan tetap terlihat dan terjadwal.">
            <x-slot name="actions">@can('create', App\Models\SalesAgenda::class)<a href="{{ route('sales-agendas.create') }}" class="module-button module-button-primary">＋ Agenda baru</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    @php($hasActiveFilters = filled($branchId) || filled($category) || filled($status) || request()->filled('date_from') || request()->filled('date_to'))
    <div class="module-page">
        @if (Auth::user()->can('sales.view-own'))<div class="mb-4"><a href="{{ route('sales-agendas.index', ['view' => 'redirected']) }}" class="module-button module-button-secondary">Agenda dialihkan</a></div>@endif
        @if (session('status'))<x-ui.toast message="Agenda berhasil diproses." />@endif
            <x-table-card title="Queue agenda" :count="number_format($agendas->total(), 0, ',', '.').' agenda'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('sales-agendas.index') }}" :result-count="$agendas->total()" result-label="agenda" :has-active-filters="$hasActiveFilters" reset-href="{{ route('sales-agendas.index') }}">
                    <x-slot name="filters">
                        @if (Auth::user()->canAccessAllBranches())<x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$branchId" :active="filled($branchId)" />@endif
                        <x-filter-pill label="Kategori" name="activity_category" :options="['' => 'Semua kategori'] + collect($categories)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$category" :active="filled($category)" />
                        <x-filter-pill label="Status" name="status" :options="['' => 'Semua status'] + collect($statuses)->mapWithKeys(fn ($item): array => [$item => str($item)->replace('_', ' ')->title()])->all()" :selected="$status" :active="filled($status)" />
                        <label class="module-filter-extra" data-filter-label="Tanggal agenda">Dari <input type="date" name="date_from" value="{{ request('date_from') }}" data-auto-submit></label>
                        <label class="module-filter-extra" data-filter-label="Tanggal agenda">Sampai <input type="date" name="date_to" value="{{ request('date_to') }}" data-auto-submit></label>
                    </x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Queue agenda">
                <x-slot name="header"><tr><th>Tanggal</th><th>Aktivitas</th><th>Lokasi</th><th>Pemilik</th><th>Dibuat oleh</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($agendas as $agenda)
                        <tr>
                            <td class="whitespace-nowrap"><div class="font-semibold">{{ $agenda->agenda_date->translatedFormat('d M Y') }}</div><div class="text-[11px] text-muted">{{ $agenda->agenda_date->format('H:i') }}</div></td>
                            <td><div class="font-semibold">{{ $agenda->title }}</div><div class="mt-1 text-[11px] text-orange">{{ $agenda->activity_category }}</div>@if ($agenda->salesCase)<div class="text-[11px] text-muted">{{ $agenda->salesCase->consumer->nama_konsumen }}</div>@elseif ($agenda->lead)<div class="text-[11px] text-muted">{{ $agenda->lead->id_lead }} · {{ $agenda->lead->nama_konsumen }}</div>@endif</td>
                            <td class="text-muted">{{ $agenda->location ?: 'Lokasi belum diisi' }}@if ($agenda->evidence->isNotEmpty())<div class="text-[11px]">{{ $agenda->evidence->count() }} foto</div>@endif</td>
                            <td class="text-muted">{{ $agenda->owner?->name ?? '-' }}</td>
                            <td class="text-muted">{{ $agenda->creator?->name ?? '-' }}@if ($agenda->creator?->isNot($agenda->owner))<div class="text-[11px]">@php($redirect = $agenda->assignmentHistories->firstWhere('action', 'redirected'))@if ($redirect)Dialihkan ke {{ $redirect->toUser?->name ?? '-' }} oleh {{ $redirect->byUser?->name ?? '-' }}@if ($redirect->reason) · {{ $redirect->reason }}@endif @endif</div>@endif</td>
                            <td><x-status-badge :status="str($agenda->status)->replace('_', ' ')->title()" :tone="$agenda->status === 'done' ? 'success' : ($agenda->status === 'cancelled' ? 'danger' : 'warning')" /></td>
                            <td><x-row-actions><x-slot name="primary"><a href="{{ route('sales-agendas.show', $agenda) }}" class="module-row-primary">Detail</a></x-slot><x-slot name="menu">@can('update', $agenda)<a href="{{ route('sales-agendas.edit', $agenda) }}" class="module-menu-link">Edit</a>@endcan @can('create', App\Models\Lead::class)<a href="{{ route('leads.create', ['from_agenda' => $agenda->id]) }}" class="module-menu-link">Jadikan Lead</a>@endcan @if ($agenda->status === 'planned') @can('redirect', $agenda)<a href="{{ route('sales-agendas.redirect-form', $agenda) }}" class="module-menu-link">Alihkan</a><form method="POST" action="{{ route('sales-agendas.cancel-by-coordinator', $agenda) }}" class="module-inline-form">@csrf @method('PATCH')<input name="reason" required placeholder="Alasan pembatalan" class="module-form-control w-40"><button class="module-menu-link is-danger">Batalkan</button></form>@endcan @can('update', $agenda)<form method="POST" action="{{ route('sales-agendas.complete', $agenda) }}" class="module-inline-form">@csrf @method('PATCH')<input name="activity_result" required placeholder="Hasil" class="module-form-control w-28"><button class="module-menu-link">Selesai</button></form><form method="POST" action="{{ route('sales-agendas.cancel', $agenda) }}" onsubmit="return confirm('Yakin membatalkan agenda ini?')">@csrf @method('PATCH')<button class="module-menu-link is-danger">Batal</button></form>@endcan @endif @can('delete', $agenda)<form method="POST" action="{{ route('sales-agendas.destroy', $agenda) }}" onsubmit="return confirm('Hapus agenda ini?')">@csrf @method('DELETE')<button class="module-menu-link is-danger">Hapus</button></form>@endcan</x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state title="Belum ada agenda sesuai filter." :reset-href="$hasActiveFilters ? route('sales-agendas.index') : null" action-label="Agenda baru" action-href="{{ ! $hasActiveFilters && Auth::user()->can('create', App\Models\SalesAgenda::class) ? route('sales-agendas.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($agendas as $agenda)
                        <x-mobile.data-card :title="$agenda->title" :subtitle="$agenda->agenda_date->translatedFormat('d M Y H:i').' · '.($agenda->owner?->name ?? 'Tanpa pemilik')" :chip="str($agenda->status)->replace('_', ' ')->title()" :href="route('sales-agendas.show', $agenda)">
                            <x-slot name="leading"><span class="mobile-data-card-date">{{ $agenda->agenda_date->format('d') }}<small>{{ $agenda->agenda_date->translatedFormat('M') }}</small></span></x-slot>
                            <x-slot name="details">
                                <div class="mobile-data-card-detail"><small>Lokasi</small><strong>{{ $agenda->location ?: 'Belum diisi' }}</strong></div>
                                <div class="mobile-data-card-detail"><small>Kategori</small><strong>{{ $agenda->activity_category }}</strong></div>
                                <div class="mobile-data-card-detail"><small>Konsumen/Lead</small><strong>{{ $agenda->salesCase?->consumer?->nama_konsumen ?? ($agenda->lead?->id_lead ?? '-') }}</strong></div>
                                <div class="mobile-data-card-detail"><small>Bukti</small><strong>{{ $agenda->evidence->count() }} foto</strong></div>
                            </x-slot>
                            <x-slot name="actions">
                                <a href="{{ route('sales-agendas.show', $agenda) }}">Detail</a>
                                @can('update', $agenda)<a href="{{ route('sales-agendas.edit', $agenda) }}">Edit</a>@endcan
                                @if ($agenda->status === 'planned')
                                    @can('redirect', $agenda)<a href="{{ route('sales-agendas.redirect-form', $agenda) }}">Alihkan</a>@endcan
                                    @can('update', $agenda)<form method="POST" action="{{ route('sales-agendas.complete', $agenda) }}">@csrf @method('PATCH')<input name="activity_result" required placeholder="Hasil" class="module-form-control"><button type="submit">Selesai</button></form>@endcan
                                @endif
                            </x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada agenda sesuai filter." :reset-href="$hasActiveFilters ? route('sales-agendas.index') : null" action-label="Agenda baru" action-href="{{ ! $hasActiveFilters && Auth::user()->can('create', App\Models\SalesAgenda::class) ? route('sales-agendas.create') : null }}" />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$agendas" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
