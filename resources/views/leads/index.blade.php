<x-app-layout>
    @php($qualificationLabels = ['not_qualified' => 'Belum memenuhi syarat', 'qualified' => 'Memenuhi syarat', 'not_fit' => 'Tidak sesuai', 'deferred' => 'Ditunda'])

    <x-slot name="header">
        <x-page-header title="Leads" description="Kelola Lead akuisisi dengan filter dan tabel operasional.">
            <x-slot name="actions">@can('create', App\Models\Lead::class)<a href="{{ route('leads.create') }}" class="module-button module-button-primary">＋ Lead baru</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    @php($hasActiveFilters = filled($salesPicId) || filled($branchId) || $active || filled($status) || filled($source) || filled($channel) || filled($followUp) || request()->filled('project_id') || request()->filled('date_from') || request()->filled('date_to') || request()->filled('search'))
    <div class="module-page">
        <x-table-card title="Tabel Lead" description="Gunakan Detail untuk memperbarui aktivitas, kualifikasi, dan follow-up." :count="number_format($leads->total()).' Lead'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('leads.index') }}" :result-count="$leads->total()" result-label="Lead" :has-active-filters="$hasActiveFilters" reset-href="{{ route('leads.index') }}">
                    <x-slot name="search"><x-search-input name="search" :value="request('search')" placeholder="Cari ID, nama, no. HP" /></x-slot>
                    <x-slot name="filters">
                        <x-filter-pill label="Sales" name="sales_pic_id" :options="['' => 'Semua sales'] + $salesUsers->mapWithKeys(fn ($salesUser): array => [$salesUser->id => $salesUser->name])->all()" :selected="$salesPicId" :active="filled($salesPicId)" />
                        <x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$branchId" :active="filled($branchId)" />
                        <x-filter-pill label="Tahap" name="status_lead" :options="['' => 'Semua tahap'] + collect($statuses)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$status" :active="filled($status)" />
                        <x-filter-pill label="Sumber" name="sumber_lead" :options="['' => 'Semua sumber'] + collect($sources)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$source" :active="filled($source)" />
                        <x-filter-pill label="Kanal" name="kanal_masuk" :options="['' => 'Semua kanal'] + collect($channels)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$channel" :active="filled($channel)" />
                        <x-filter-pill label="Proyek" name="project_id" :options="['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all()" :selected="request('project_id')" :active="request()->filled('project_id')" />
                        <label class="module-filter-pill {{ $active ? 'is-active' : '' }}"><input type="checkbox" name="active" value="1" @checked($active) onchange="this.form.submit()"> Lead aktif saja</label>
                        <label class="module-filter-extra">Dari <input type="date" name="date_from" value="{{ request('date_from') }}" onchange="this.form.submit()"></label>
                        <label class="module-filter-extra">Sampai <input type="date" name="date_to" value="{{ request('date_to') }}" onchange="this.form.submit()"></label>
                        <x-filter-pill label="Follow-up" name="follow_up" :options="['' => 'Semua jadwal', 'today' => 'Hari ini', 'overdue' => 'Terlambat', 'upcoming' => 'Akan datang']" :selected="$followUp" :active="filled($followUp)" />
                    </x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Daftar Lead">
                <x-slot name="header"><tr><th>Tanggal</th><th>Konsumen</th><th>Sales</th><th>Koordinator</th><th>Proyek</th><th>Sumber</th><th>Tahap & kualifikasi</th><th>Follow-up</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($leads as $lead)
                        <tr>
                            <td class="whitespace-nowrap"><div class="font-semibold text-ink">{{ $lead->tanggal_lead->translatedFormat('d M Y') }}</div><div class="text-[11px] text-muted">{{ $lead->id_lead }}</div></td>
                            <td><div class="font-semibold text-ink">{{ $lead->nama_konsumen }}</div><div class="text-[11px] text-muted">{{ $lead->no_hp }}</div></td>
                            <td class="text-muted">{{ $lead->salesPic->name }}</td>
                            <td class="text-muted">{{ $lead->team?->coordinator?->name ?? '-' }}</td>
                            <td class="max-w-[180px] truncate text-muted" title="{{ $lead->project?->name }}">{{ $lead->project?->name ?? '-' }}</td>
                            <td class="text-muted">{{ $lead->sumber_lead }}</td>
                            <td><x-status-badge :status="$lead->status_lead" tone="lime" /><div class="mt-1 text-[11px] text-muted">{{ $qualificationLabels[$lead->qualification_status] ?? $lead->qualification_status }}</div></td>
                            <td class="whitespace-nowrap text-muted">{{ $lead->next_follow_up_at?->format('d/m/Y H:i') ?? 'Belum dijadwalkan' }}</td>
                            <td><x-row-actions primary-label="Detail" primary-href="{{ route('leads.show', $lead) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state title="Belum ada Lead sesuai filter" :reset-href="$hasActiveFilters ? route('leads.index') : null" action-label="Lead baru" action-href="{{ ! $hasActiveFilters && Auth::user()->can('create', App\Models\Lead::class) ? route('leads.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$leads" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
