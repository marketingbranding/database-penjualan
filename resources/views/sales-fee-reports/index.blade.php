<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Laporan performa sales" description="Rekap aktivitas Sales berdasarkan periode, Lead, dan Agenda.">
            <x-slot name="actions">@can('sales-fee-reports.export')<a href="{{ route('sales-fee-reports.pdf', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="module-button module-button-secondary">↓ Unduh PDF</a><a href="{{ route('sales-fee-reports.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="module-button module-button-primary">↓ Export Excel</a>@endcan</x-slot>
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
                <article class="module-summary-card tone-neutral"><p>{{ $label }}</p><strong>{{ number_format($report['totals'][$key], 0, ',', '.') }}</strong></article>
            @endforeach
        </section>

        <x-table-card title="Rekap per Sales" description="Buka detail untuk melihat Lead dan Agenda pada baris terkait." :count="count($report['rows']).' sales'" class="min-h-0" x-data="{ mobileTableView: localStorage.getItem('mobile-view-sales-fee-reports') || 'cards' }" x-init="$watch('mobileTableView', value => localStorage.setItem('mobile-view-sales-fee-reports', value))" ::class="{ 'mobile-table-mode-table': mobileTableView === 'table' }">
            <x-slot name="mobileView"><x-mobile.table-switch storage-key="sales-fee-reports" /></x-slot>
            <x-data-table caption="Rekap per Sales">
                <x-slot name="header"><tr><th>Sales</th><th>Koordinator</th><th>Proyek</th><th>Total agenda</th><th>Agenda selesai</th><th>Total Lead</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($report['rows'] as $row)
                        <tr><td class="font-semibold">{{ $row['sales_name'] }}</td><td class="text-muted">{{ $row['coordinator_name'] }}</td><td class="text-muted">{{ $row['project_name'] }}</td><td class="font-semibold">{{ number_format($row['total_agenda'], 0, ',', '.') }}</td><td><x-status-badge :status="number_format($row['agenda_selesai'], 0, ',', '.').' selesai'" tone="success" /></td><td><x-status-badge :status="number_format($row['total_lead'], 0, ',', '.').' Lead'" tone="warning" /></td><td><x-row-actions><x-slot name="primary"><a href="{{ route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']])) }}" class="module-row-primary">Detail</a></x-slot><x-slot name="menu">@can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter([...$filters, 'sales_pic_id' => $row['sales_id'], 'project_id' => $row['project_id']])) }}" target="_blank" class="module-menu-link">Cetak</a>@endcan</x-slot></x-row-actions></td></tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state title="Belum ada data pada periode atau filter ini." :reset-href="$hasActiveFilters ? route('sales-fee-reports.index') : null" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($report['rows'] as $row)
                        <x-mobile.data-card class="sales-report-mobile-card" :title="$row['sales_name']" :subtitle="$row['coordinator_name'].' · '.$row['project_name']" :href="route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']]))" :value="number_format($row['total_agenda'], 0, ',', '.')" value-label="Agenda">
                            <x-slot name="leading"><span class="mobile-report-rank rank-{{ min($loop->iteration, 4) }}">{{ $loop->iteration }}</span><x-ui.avatar :name="$row['sales_name']" size="sm" tone="lime" /></x-slot>
                            <x-slot name="metrics">
                                <div class="mobile-data-card-metric"><small>Lead</small><strong>{{ number_format($row['total_lead'], 0, ',', '.') }}</strong></div>
                                <div class="mobile-data-card-metric"><small>Tatap muka</small><strong>{{ number_format($row['tatap_muka'], 0, ',', '.') }}</strong></div>
                                <div class="mobile-data-card-metric"><small>UTJ</small><strong>{{ number_format($row['utj'], 0, ',', '.') }}</strong></div>
                            </x-slot>
                            <x-slot name="actions">
                                <a href="{{ route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']])) }}">Detail lead & agenda</a>
                                @can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter([...$filters, 'sales_pic_id' => $row['sales_id'], 'project_id' => $row['project_id']])) }}" target="_blank">Cetak</a>@endcan
                            </x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada data pada periode atau filter ini." :reset-href="$hasActiveFilters ? route('sales-fee-reports.index') : null" />
                    @endforelse
                </x-slot>
            </x-data-table>
        </x-table-card>
        <p class="text-xs text-muted">Laporan ini saat ini menghitung performa aktivitas. Nominal fee belum dihitung.</p>
    </div>
</x-app-layout>
