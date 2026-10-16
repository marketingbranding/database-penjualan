<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Performa Tim" description="Bandingkan kinerja Koordinator dan Sales dalam scope organisasi Anda.">
            <x-slot name="actions"><a href="{{ route('team-performance.export', $filters) }}" class="module-button module-button-primary">↓ Export Excel</a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        <x-table-card title="Filter performa" description="Periode memakai kalender {{ $report['timezone'] }}.">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('team-performance.index') }}" :has-active-filters="collect($filters)->contains(fn ($value): bool => filled($value))" reset-href="{{ route('team-performance.index') }}">
                    <x-slot name="filters">
                        @if (Auth::user()->canAccessAllBranches())<x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$filters['branch_id']" :active="filled($filters['branch_id'])" />@endif
                        <x-filter-pill label="Proyek" name="project_id" :options="['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all()" :selected="$filters['project_id']" :active="filled($filters['project_id'])" />
                        <x-filter-pill label="Koordinator" name="coordinator_id" :options="['' => 'Semua koordinator'] + $coordinators->mapWithKeys(fn ($user): array => [$user->id => $user->name])->all()" :selected="$filters['coordinator_id']" :active="filled($filters['coordinator_id'])" />
                        <label class="module-filter-extra" data-filter-label="Periode">Dari <input type="date" name="date_from" value="{{ $filters['date_from'] }}" data-auto-submit></label>
                        <label class="module-filter-extra" data-filter-label="Periode">Sampai <input type="date" name="date_to" value="{{ $filters['date_to'] }}" data-auto-submit></label>
                    </x-slot>
                </x-toolbar>
            </x-slot>
        </x-table-card>

        <section class="module-summary-grid module-summary-grid-six">
            @foreach ([['Lead', 'lead_total'], ['UTJ', 'statuses.UTJ'], ['Akad', 'statuses.Akad'], ['Agenda selesai', 'agendas_done'], ['Overdue follow-up', 'overdue_followups'], ['Pengalihan agenda', 'redirected_in']] as [$label, $key])
                @php($value = str($key)->contains('.') ? data_get($report['totals'], $key, 0) : ($report['totals'][$key] ?? 0))
                <article class="module-summary-card tone-neutral"><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong></article>
            @endforeach
        </section>

        <div class="mb-4 grid gap-2 text-xs text-muted">
            <p>Konversi lead periode ini (hingga hari ini): UTJ {{ $report['totals']['cohort_total'] ? number_format($report['totals']['cohort_reached_utj'] / $report['totals']['cohort_total'] * 100, 1).'%' : '0%' }}, Akad {{ $report['totals']['cohort_total'] ? number_format($report['totals']['cohort_reached_akad'] / $report['totals']['cohort_total'] * 100, 1).'%' : '0%' }}. Periode terbaru masih dapat bertumbuh.</p>
            <p>{{ $report['conversionNote'] }}</p>
            <p>{{ $report['overdueNote'] }}</p>
        </div>

        @forelse ($report['projects'] as $project)
            <section class="module-table-card mb-4 min-h-0">
                <header class="module-table-card-header"><div><h2 class="text-sm font-semibold text-ink">{{ $project['project_name'] }}</h2><p class="mt-1 text-xs text-muted">{{ number_format($project['totals']['lead_total']) }} Lead · {{ number_format($project['totals']['agendas_done']) }} agenda selesai · {{ number_format($project['totals']['redirected_in'] + $project['totals']['redirected_out']) }} pengalihan agenda</p></div></header>
                <div class="module-table-card-body">
                    @foreach ($project['teams'] as $team)
                        <details class="border-b border-line p-4 last:border-0" open>
                            <summary class="cursor-pointer list-none"><div class="flex flex-wrap items-center justify-between gap-3"><div><span class="font-semibold text-ink">{{ $team['team_name'] }}</span><span class="ml-2 text-xs text-muted">Koordinator: {{ $team['coordinator_name'] }}</span></div><span class="text-xs text-muted">{{ number_format($team['totals']['lead_total']) }} Lead · {{ number_format($team['totals']['agendas_done']) }} agenda selesai</span></div></summary>
                            <div class="mt-3 overflow-x-auto"><table class="module-data-table"><thead><tr><th>Sales</th><th>Lead</th><th>Status saat ini</th><th>Side outcome</th><th>Movement UTJ/Akad</th><th>Konversi UTJ/Akad</th><th>Overdue FU</th><th>Agenda P/D/C</th><th>Overdue Agenda</th><th>Completion</th><th>Pengalihan In/Out</th></tr></thead><tbody>
                                @forelse ($team['sales'] as $sales)
                                    <tr><td class="font-semibold"><div>{{ $sales['sales_name'] }}</div><div class="mt-1 flex gap-2 text-[11px]"><a class="text-lime-dark hover:underline" href="{{ route('leads.index', ['sales_pic_id' => $sales['sales_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']]) }}">Buka Lead</a><a class="text-lime-dark hover:underline" href="{{ route('sales-agendas.index', ['owner_id' => $sales['sales_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']]) }}">Buka Agenda</a></div></td><td>{{ number_format($sales['lead_total']) }}</td><td class="text-[11px] leading-5">@foreach($sales['statuses'] as $status => $count){{ $status }}: {{ $count }}@if(!$loop->last) · @endif @endforeach</td><td class="text-[11px] leading-5">@foreach($sales['side_outcomes'] as $status => $count){{ $status }}: {{ $count }}@if(!$loop->last) · @endif @endforeach</td><td>{{ $sales['movement_utj'].' / '.$sales['movement_akad'] }}</td><td>{{ $sales['cohort_total'] ? number_format($sales['cohort_reached_utj'] / $sales['cohort_total'] * 100, 1).'%' : '0%' }} / {{ $sales['cohort_total'] ? number_format($sales['cohort_reached_akad'] / $sales['cohort_total'] * 100, 1).'%' : '0%' }}</td><td>{{ number_format($sales['overdue_followups']) }}</td><td>{{ $sales['agendas_planned'].' / '.$sales['agendas_done'].' / '.$sales['agendas_cancelled'] }}</td><td>{{ number_format($sales['agendas_overdue']) }}</td><td>{{ number_format($sales['completion_rate'], 1) }}%</td><td>{{ $sales['redirected_in'].' / '.$sales['redirected_out'] }}</td></tr>
                                @empty
                                    <tr><td colspan="11"><x-empty-state title="Belum ada Sales dalam tim ini." /></td></tr>
                                @endforelse
                            </tbody></table></div>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <x-empty-state title="Belum ada struktur tim pada filter ini." description="Periksa periode, proyek, atau koordinator yang dipilih." />
        @endforelse
    </div>
</x-app-layout>
