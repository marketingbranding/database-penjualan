<x-app-layout>
    @php
        $stageColors = ['No Respon' => 'stage-neutral', 'Diskusi' => 'stage-discussion', 'Tatap Muka' => 'stage-face-to-face', 'Cek Lokasi' => 'stage-site-visit', 'Jadi Freelance' => 'stage-freelance', 'UTJ' => 'stage-utj', 'Cek SLIK' => 'stage-slik', 'Tidak Lolos BI Checking' => 'stage-side-outcome', 'Akad' => 'stage-akad'];
        $salesWithIssues = collect($salesRows)->filter(fn (array $row): bool => $row['overdue'] > 0 || $row['agenda_unfinished'] > 0)->take(3);
    @endphp

    <x-slot name="header">
        <div class="personal-dashboard-heading">
            <p class="personal-dashboard-eyebrow">{{ $header['date'] }} · Tim {{ $header['team'] }} · {{ $header['salesCount'] }} sales</p>
            <h1>{{ $header['greeting'] }}</h1>
            <p class="personal-dashboard-subtitle">Bantu Sales yang paling membutuhkan perhatian hari ini.</p>
        </div>
        <div class="personal-dashboard-header-actions">
            @can('leads.create')<a href="{{ route('leads.create') }}" class="module-button module-button-secondary">Lead untuk sales</a>@endcan
            @can('sales.create')<a href="{{ route('sales-agendas.create') }}" class="module-button module-button-primary">＋ Agenda untuk sales</a>@endcan
        </div>
    </x-slot>

    <div class="personal-dashboard coordinator-dashboard">
        <section class="coordinator-metric-grid" aria-label="Ringkasan tim">
            <article class="coordinator-metric-card is-danger"><span>Follow-up terlambat tim</span><strong>{{ number_format($metrics['overdue'], 0, ',', '.') }}</strong><small>di {{ number_format(collect($salesRows)->where('overdue', '>', 0)->count(), 0, ',', '.') }} sales</small></article>
            <article class="coordinator-metric-card"><span>Agenda tim hari ini</span><strong>{{ number_format($metrics['agendaDone'], 0, ',', '.') }}<small> / {{ number_format($metrics['agendaTotal'], 0, ',', '.') }} selesai</small></strong><i><b style="width: {{ $metrics['agendaTotal'] ? ($metrics['agendaDone'] / $metrics['agendaTotal'] * 100) : 0 }}%"></b></i></article>
            <article class="coordinator-metric-card"><span>Lead baru belum direspons</span><strong>{{ number_format($metrics['newUnresponded'], 0, ',', '.') }}</strong><small>7 hari terakhir</small></article>
            <article class="coordinator-metric-card coordinator-desktop-only"><span>UTJ · Akad bulan ini</span><strong>{{ number_format($metrics['movementUtj'], 0, ',', '.') }}<small> · {{ number_format($metrics['movementAkad'], 0, ',', '.') }}</small></strong><small>Pergerakan tahap tim</small></article>
        </section>

        <section class="coordinator-dashboard-section" aria-labelledby="sales-help-title">
            <div class="personal-section-heading"><div><p class="personal-section-kicker coordinator-desktop-only">Perhatian tim</p><h2 id="sales-help-title"><span class="coordinator-desktop-only">Sales di tim saya</span><span class="coordinator-mobile-only">Sales perlu dibantu</span></h2></div><a href="{{ route('team-performance.index') }}" class="personal-inline-action">Semua sales</a></div>
            <div class="coordinator-sales-table coordinator-desktop-only"><div class="coordinator-sales-row coordinator-sales-head"><span>Sales</span><span>Lead aktif</span><span>Target Lead</span><span>Rencana hari ini</span><span>UTJ · Akad</span><span></span></div>@foreach ($salesRows as $row)<div class="coordinator-sales-row"><span class="coordinator-sales-name"><i class="{{ $row['overdue'] ? 'is-danger' : '' }}">{{ str($row['user']->name)->substr(0, 2)->upper() }}</i><b>{{ $row['user']->name }}</b></span><span>{{ number_format($row['active_leads'], 0, ',', '.') }}</span><span>{{ $row['lead_target']['achieved'] }} / {{ $row['lead_target']['target'] }}</span><span class="{{ $row['daily_plan_minutes'] === 420 ? '' : 'is-danger' }}">{{ $row['daily_plan_minutes'] }} / 420 · {{ strtoupper($row['daily_plan_status']) }}</span><span>{{ number_format($row['movement_utj'], 0, ',', '.') }} · {{ number_format($row['movement_akad'], 0, ',', '.') }}</span><a href="{{ $row['daily_plan'] ? route('daily-plans.show', $row['daily_plan']) : route('daily-plans.index') }}" class="coordinator-view-link">Lihat</a></div>@endforeach</div>
            <div class="coordinator-sales-cards coordinator-mobile-only">
                @forelse ($salesRows as $row)
                    <a href="{{ $row['daily_plan'] ? route('daily-plans.show', $row['daily_plan']) : route('daily-plans.index') }}" class="coordinator-sales-card">
                        <i class="{{ $row['overdue'] ? 'is-danger' : '' }}">{{ str($row['user']->name)->substr(0, 2)->upper() }}</i>
                        <span>
                            <strong>{{ $row['user']->name }}</strong>
                            <small>Target Lead {{ $row['lead_target']['achieved'] }} / {{ $row['lead_target']['target'] }} · Rencana {{ $row['daily_plan_minutes'] }} / 420
                                @if ($row['overdue']) · {{ $row['overdue'] }} terlambat @endif
                            </small>
                        </span>
                        <em aria-hidden="true">›</em>
                    </a>
                @empty
                    <div class="coordinator-positive-empty"><strong>Belum ada Sales di tim</strong><span>Sales pada Team aktif akan muncul di sini.</span></div>
                @endforelse
            </div>
        </section>

        <section class="coordinator-dashboard-section" aria-labelledby="team-agenda-title"><div class="personal-section-heading"><div><p class="personal-section-kicker">Agenda</p><h2 id="team-agenda-title">Agenda tim hari ini</h2></div><a href="{{ route('sales-agendas.index', ['date_from' => $date, 'date_to' => $date]) }}" class="personal-inline-action">Agenda tim</a></div><div class="coordinator-agenda-list">@forelse ($teamAgendas as $item)<div class="coordinator-agenda-row {{ $item['agenda']->status === 'done' ? 'is-done' : '' }}"><span><strong>{{ $item['agenda']->title }} @if ($item['agenda']->lead) · {{ $item['agenda']->lead->nama_konsumen }}@elseif ($item['agenda']->salesCase) · {{ $item['agenda']->salesCase->consumer->nama_konsumen }}@endif</strong><small>{{ $item['agenda']->owner?->name ?? 'Sales' }} · {{ $item['agenda']->status === 'done' ? 'Selesai' : 'Belum selesai' }}</small></span>@if ($item['isRedirectable'] && Auth::user()->can('redirect', $item['agenda']))<a href="{{ route('sales-agendas.redirect-form', $item['agenda']) }}" class="coordinator-redirect">Alihkan</a>@endif</div>@empty<div class="coordinator-positive-empty"><strong>Tidak ada agenda tim hari ini</strong><span>Semua Sales bisa fokus pada pekerjaan berikutnya.</span></div>@endforelse</div></section>

        <section class="coordinator-desktop-only coordinator-dashboard-section coordinator-lower-grid"><div class="coordinator-dashboard-section"><div class="personal-section-heading"><h2>Pipeline tim</h2></div><div class="personal-stage-card"><div class="personal-stage-bar">@foreach ($pipeline as $stage => $count)<span class="{{ $stageColors[$stage] ?? 'stage-neutral' }}" style="--stage-share: {{ $count }}"></span>@endforeach</div><div class="personal-stage-legend">@foreach ($pipeline as $stage => $count)<span><i class="{{ $stageColors[$stage] ?? 'stage-neutral' }}"></i>{{ $stage }} <b>{{ number_format($count, 0, ',', '.') }}</b></span>@endforeach</div></div></div>@if ($stock)<div class="coordinator-dashboard-section"><div class="personal-section-heading"><h2>Stok kavling {{ $stock['project']->name }}</h2></div><div class="personal-stock-grid"><div class="personal-stock-card"><div><span><i class="stock-available"></i>Tersedia <b>{{ number_format($stock['available'], 0, ',', '.') }}</b></span><span><i class="stock-reserved"></i>Dipesan <b>{{ number_format($stock['reserved'], 0, ',', '.') }}</b></span><span><i class="stock-sold"></i>Terjual <b>{{ number_format($stock['sold'], 0, ',', '.') }}</b></span></div></div></div></div>@endif</section>
    </div>
</x-app-layout>
