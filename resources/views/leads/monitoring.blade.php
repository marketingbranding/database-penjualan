<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Monitoring Lead" description="Pantau pergerakan pipeline tanpa kehilangan konteks Sales dan tim.">
            <x-slot name="actions"><a href="{{ route('leads.index') }}" class="module-button module-button-secondary">Daftar Lead</a>@can('create', App\Models\Lead::class)<a href="{{ route('leads.create') }}" class="module-button module-button-primary">＋ Lead baru</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    @php($hasActiveFilters = collect($filters)->contains(fn ($value): bool => filled($value)))
    <div class="module-page">
        <x-table-card title="Filter monitoring" description="Gunakan periode dan cakupan tim untuk membaca performa.">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('leads.monitoring') }}" :result-count="$summary['total']" result-label="Lead" :has-active-filters="$hasActiveFilters" reset-href="{{ route('leads.monitoring') }}">
                    <x-slot name="filters">
                        @if (Auth::user()->canAccessAllBranches())<x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$filters['branch_id']" :active="filled($filters['branch_id'])" />@endif
                        <x-filter-pill label="Proyek" name="project_id" :options="['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all()" :selected="$filters['project_id']" :active="filled($filters['project_id'])" />
                        <x-filter-pill label="Koordinator" name="coordinator_id" :options="['' => 'Semua koordinator'] + $coordinators->mapWithKeys(fn ($item): array => [$item->id => $item->name])->all()" :selected="$filters['coordinator_id']" :active="filled($filters['coordinator_id'])" />
                        <x-filter-pill label="Sales" name="sales_pic_id" :options="['' => 'Semua sales'] + $salesUsers->mapWithKeys(fn ($item): array => [$item->id => $item->name])->all()" :selected="$filters['sales_pic_id']" :active="filled($filters['sales_pic_id'])" />
                        <x-filter-pill label="Sumber" name="sumber_lead" :options="['' => 'Semua sumber'] + collect($sources)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$filters['sumber_lead']" :active="filled($filters['sumber_lead'])" />
                        <x-filter-pill label="Kanal" name="kanal_masuk" :options="['' => 'Semua kanal'] + collect($channels)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$filters['kanal_masuk']" :active="filled($filters['kanal_masuk'])" />
                        <x-filter-pill label="Status" name="status_lead" :options="['' => 'Semua status'] + collect($statuses)->mapWithKeys(fn ($item): array => [$item => $item])->all()" :selected="$filters['status_lead']" :active="filled($filters['status_lead'])" />
                        <label class="module-filter-extra" data-filter-label="Tanggal lead">Dari <input type="date" name="date_from" value="{{ $filters['date_from'] }}" data-auto-submit></label>
                        <label class="module-filter-extra" data-filter-label="Tanggal lead">Sampai <input type="date" name="date_to" value="{{ $filters['date_to'] }}" data-auto-submit></label>
                    </x-slot>
                </x-toolbar>
            </x-slot>
        </x-table-card>

        <section class="module-summary-grid">
            @foreach ([['Total Lead', 'total', 'tone-ink'], ['No Respon', 'new', 'tone-neutral'], ['Diskusi', 'discussion', 'tone-orange'], ['Cek Lokasi', 'siteVisit', 'tone-lime'], ['UTJ', 'utj', 'tone-yellow']] as [$label, $key, $tone])
                <article class="module-summary-card {{ $tone }}"><p>{{ $label }}</p><strong>{{ number_format($summary[$key]) }}</strong></article>
            @endforeach
        </section>

        <x-table-card title="Rincian Lead" description="Data terfilter berdasarkan periode dan cakupan yang dipilih." :count="number_format($leads->total()).' Lead'" class="min-h-0">
            <x-data-table caption="Rincian Lead">
                <x-slot name="header"><tr><th>Tanggal Lead</th><th>Konsumen</th><th>Sales</th><th>Koordinator</th><th>Proyek</th><th>Sumber</th><th>Kanal</th><th>Status</th><th>Aktivitas terakhir</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($leads as $lead)
                        <tr>
                            <td class="whitespace-nowrap"><div class="font-semibold">{{ $lead->tanggal_lead->format('d/m/Y') }}</div><div class="text-[11px] text-muted">{{ $lead->id_lead }}</div></td>
                            <td><div class="font-semibold">{{ $lead->nama_konsumen }}</div><div class="text-[11px] text-muted">{{ $lead->no_hp }}</div></td>
                            <td class="text-muted">{{ $lead->salesPic->name }}</td><td class="text-muted">{{ $lead->team?->coordinator?->name ?? '-' }}</td><td class="text-muted">{{ $lead->project?->name ?? '-' }}</td><td class="text-muted">{{ $lead->sumber_lead }}</td><td class="text-muted">{{ $lead->kanal_masuk }} / {{ $lead->aktivitas_lead }}</td>
                            <td><x-status-badge :status="$lead->status_lead" tone="lime" /></td>
                            <td class="text-[11px] text-muted">@if ($lead->latestActivity)<div class="font-semibold text-ink">{{ $lead->latestActivity->activity_type }}</div><div>{{ \App\Support\BusinessTime::format($lead->latestActivity->happened_at) }}</div>@else-@endif</td>
                            <td><x-row-actions primary-label="Detail" primary-href="{{ route('leads.show', $lead) }}" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-empty-state title="Tidak ada Lead pada periode atau filter ini." :reset-href="$hasActiveFilters ? route('leads.monitoring') : null" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($leads as $lead)
                        <x-mobile.data-card :title="$lead->nama_konsumen" :subtitle="$lead->id_lead.' · '.$lead->salesPic->name" :chip="$lead->status_lead" :href="route('leads.show', $lead)">
                            <x-slot name="details"><div class="mobile-data-card-detail"><small>Tanggal</small><strong>{{ $lead->tanggal_lead->format('d/m/Y') }}</strong></div><div class="mobile-data-card-detail"><small>Proyek</small><strong>{{ $lead->project?->name ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Sumber/Kanal</small><strong>{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</strong></div><div class="mobile-data-card-detail"><small>Aktivitas terakhir</small><strong>{{ $lead->latestActivity?->activity_type ?? '-' }}</strong></div></x-slot>
                            <x-slot name="actions"><a href="{{ route('leads.show', $lead) }}">Detail lead</a></x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Tidak ada Lead pada periode atau filter ini." :reset-href="$hasActiveFilters ? route('leads.monitoring') : null" />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$leads" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
