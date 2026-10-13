<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Laporan performa sales" description="Rekap aktivitas Sales berdasarkan periode, Lead, dan Agenda.">
            <x-slot name="actions">@can('sales-fee-reports.export')<a href="{{ route('sales-fee-reports.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="module-button module-button-primary">↓ Export Excel</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    @php($hasActiveFilters = collect($filters)->contains(fn ($value): bool => filled($value)))
    <div class="module-page">
        <x-table-card title="Filter laporan" description="Pilih periode dan cakupan untuk membaca performa.">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('sales-fee-reports.index') }}" :has-active-filters="$hasActiveFilters" reset-href="{{ route('sales-fee-reports.index') }}">
                    <x-slot name="filters">
                        <label class="module-filter-extra">Mulai <input type="date" name="date_from" value="{{ $filters['date_from'] }}" onchange="this.form.submit()"></label>
                        <label class="module-filter-extra">Selesai <input type="date" name="date_to" value="{{ $filters['date_to'] }}" onchange="this.form.submit()"></label>
                        @if (Auth::user()->canAccessAllBranches())<x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$filters['branch_id']" :active="filled($filters['branch_id'])" />@endif
                        <x-filter-pill label="Proyek" name="project_id" :options="['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all()" :selected="$filters['project_id']" :active="filled($filters['project_id'])" />
                        <x-filter-pill label="Koordinator" name="coordinator_id" :options="['' => 'Semua koordinator'] + $coordinators->mapWithKeys(fn ($item): array => [$item->id => $item->name])->all()" :selected="$filters['coordinator_id']" :active="filled($filters['coordinator_id'])" />
                        <x-filter-pill label="Sales" name="sales_pic_id" :options="['' => 'Semua sales'] + $salesUsers->mapWithKeys(fn ($item): array => [$item->id => $item->name])->all()" :selected="$filters['sales_pic_id']" :active="filled($filters['sales_pic_id'])" />
                    </x-slot>
                </x-toolbar>
            </x-slot>
        </x-table-card>

        <section class="module-summary-grid module-summary-grid-six">
            @foreach (['Total Agenda' => 'total_agenda', 'Agenda Selesai' => 'agenda_selesai', 'Total Lead' => 'total_lead', 'Tatap Muka' => 'tatap_muka', 'Cek Lokasi' => 'cek_lokasi', 'UTJ' => 'utj'] as $label => $key)
                <article class="module-summary-card tone-neutral"><p>{{ $label }}</p><strong>{{ number_format($report['totals'][$key]) }}</strong></article>
            @endforeach
        </section>

        <x-table-card title="Rekap per Sales" description="Buka detail untuk melihat Lead dan Agenda pada baris terkait." :count="count($report['rows']).' sales'" class="min-h-0">
            <x-data-table caption="Rekap per Sales">
                <x-slot name="header"><tr><th>Sales</th><th>Koordinator</th><th>Proyek</th><th>Total agenda</th><th>Agenda selesai</th><th>Total Lead</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($report['rows'] as $row)
                        <tr><td class="font-semibold">{{ $row['sales_name'] }}</td><td class="text-muted">{{ $row['coordinator_name'] }}</td><td class="text-muted">{{ $row['project_name'] }}</td><td class="font-semibold">{{ $row['total_agenda'] }}</td><td><x-status-badge :status="$row['agenda_selesai'].' selesai'" tone="success" /></td><td><x-status-badge :status="$row['total_lead'].' Lead'" tone="warning" /></td><td><x-row-actions><x-slot name="primary"><a href="{{ route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']])) }}" class="module-row-primary">Detail</a></x-slot><x-slot name="menu">@can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter([...$filters, 'sales_pic_id' => $row['sales_id'], 'project_id' => $row['project_id']])) }}" target="_blank" class="module-menu-link">Cetak</a>@endcan</x-slot></x-row-actions></td></tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state title="Belum ada data pada periode atau filter ini." :reset-href="$hasActiveFilters ? route('sales-fee-reports.index') : null" /></td></tr>
                    @endforelse
                </x-slot>
            </x-data-table>
        </x-table-card>
        <p class="text-xs text-muted">Laporan ini saat ini menghitung performa aktivitas. Nominal fee belum dihitung.</p>
    </div>
</x-app-layout>
