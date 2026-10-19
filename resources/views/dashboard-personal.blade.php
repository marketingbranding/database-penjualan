<x-app-layout>
    @php
        $stageColors = [
            'No Respon' => 'stage-neutral',
            'Diskusi' => 'stage-discussion',
            'Tatap Muka' => 'stage-face-to-face',
            'Cek Lokasi' => 'stage-site-visit',
            'Jadi Freelance' => 'stage-freelance',
            'UTJ' => 'stage-utj',
            'Cek SLIK' => 'stage-slik',
            'Tidak Lolos BI Checking' => 'stage-side-outcome',
            'Akad' => 'stage-akad',
        ];
        $nextAgenda = $nextAgenda ?? null;
    @endphp

    <x-slot name="header">
        <div class="personal-dashboard-heading">
            <p class="personal-dashboard-eyebrow">{{ $header['date'] }} · {{ $header['branch'] }}</p>
            <h1>{{ $header['greeting'] }}</h1>
            <p class="personal-dashboard-subtitle">Fokus pada hal yang perlu kamu lakukan hari ini.</p>
        </div>
        <div class="personal-dashboard-header-actions">
            @can('leads.create')<a href="{{ route('leads.create') }}" class="module-button module-button-primary">＋ Lead baru</a>@endcan
            @can('sales.create')<a href="{{ route('sales-agendas.create') }}" class="module-button module-button-secondary">＋ Agenda baru</a>@endcan
        </div>
    </x-slot>

    <div class="personal-dashboard">
        <div class="personal-mobile-dashboard">
        <section class="personal-dashboard-section personal-next-section" aria-labelledby="next-agenda-title">
            <div class="personal-section-heading"><div><p class="personal-section-kicker">Prioritas</p><h2 id="next-agenda-title">Agenda berikutnya</h2></div>@can('sales.create')<a href="{{ route('sales-agendas.create') }}" class="personal-inline-action">Agenda baru</a>@endcan</div>
            @if ($nextAgenda)
                <article class="personal-next-card">
                    <div class="personal-next-card-main">
                        <div class="personal-next-card-date"><a href="{{ $nextAgenda['dateUrl'] }}">{{ $nextAgenda['dateLabel'] }}</a>@if ($nextAgenda['position'])<span>{{ $nextAgenda['position'] }} dari {{ $nextAgenda['todayCount'] }} agenda hari ini</span>@endif</div>
                        <a href="{{ $nextAgenda['detailUrl'] }}" class="personal-next-title">{{ $nextAgenda['model']->title }}</a>
                        <p class="personal-next-meta">{{ $nextAgenda['model']->activity_category }}@if ($nextAgenda['consumer']) · {{ $nextAgenda['consumer'] }}@endif</p>
                        @if ($nextAgenda['project'] || $nextAgenda['lot'] || $nextAgenda['stage'])<p class="personal-next-meta">{{ $nextAgenda['project'] ?? 'Proyek belum dipilih' }}@if ($nextAgenda['lot']) · Kavling {{ $nextAgenda['lot'] }}@endif @if ($nextAgenda['stage']) · <span class="personal-stage-text {{ $stageColors[$nextAgenda['stage']] ?? 'stage-neutral' }}">{{ $nextAgenda['stage'] }}</span>@endif</p>@endif
                    </div>
                    <div class="personal-next-actions">
                        @if ($nextAgenda['whatsappUrl'])<a href="{{ $nextAgenda['whatsappUrl'] }}" target="_blank" rel="noopener" class="personal-next-action">WhatsApp</a>@endif
                        @if ($nextAgenda['telUrl'])<a href="{{ $nextAgenda['telUrl'] }}" class="personal-next-action">Telepon</a>@endif
                        @can('update', $nextAgenda['model'])
                            <button type="button" class="personal-next-action personal-next-action-primary" onclick="document.getElementById('complete-agenda-{{ $nextAgenda['model']->id }}').showModal()">Selesai</button>
                        @endcan
                    </div>
                </article>
                <dialog id="complete-agenda-{{ $nextAgenda['model']->id }}" class="personal-action-dialog">
                    <form method="POST" action="{{ route('sales-agendas.complete', $nextAgenda['model']) }}">
                        @csrf @method('PATCH')
                        <h2>Selesaikan agenda</h2>
                        <p class="personal-dialog-copy">Tulis hasil aktivitas sebelum menandai agenda ini selesai.</p>
                        <label for="activity-result-{{ $nextAgenda['model']->id }}">Hasil aktivitas</label>
                        <textarea id="activity-result-{{ $nextAgenda['model']->id }}" name="activity_result" rows="4" required></textarea>
                        <a href="{{ $nextAgenda['detailUrl'] }}" class="personal-dialog-evidence">Tambah atau lihat bukti foto</a>
                        <div class="personal-dialog-actions"><button type="button" class="module-button module-button-secondary" onclick="this.closest('dialog').close()">Batal</button><button type="submit" class="module-button module-button-primary">Simpan &amp; selesai</button></div>
                    </form>
                </dialog>
            @else
                <div class="personal-empty-card"><strong>Tidak ada agenda terjadwal</strong><p>Jadwalkan agenda agar follow-up berikutnya lebih jelas.</p>@can('create', App\Models\SalesAgenda::class)<a href="{{ route('sales-agendas.create') }}" class="module-button module-button-primary">Buat agenda baru</a>@endcan</div>
            @endif
        </section>

        <section class="personal-dashboard-section" aria-labelledby="action-title">
            <div class="personal-section-heading"><div><p class="personal-section-kicker">Hari ini</p><h2 id="action-title">Perlu tindakan</h2></div></div>
            <div class="personal-action-list">
                @foreach ([['key' => 'overdue', 'label' => 'Follow-up terlambat', 'tone' => 'danger'], ['key' => 'today', 'label' => 'Follow-up hari ini', 'tone' => 'neutral'], ['key' => 'new', 'label' => 'Lead baru belum direspons', 'tone' => 'neutral']] as $action)
                    <a href="{{ $actions['urls'][$action['key']] }}" class="personal-action-row">
                        <span class="personal-action-copy"><strong>{{ $action['label'] }}</strong><small>{{ $action['key'] === 'new' ? 'Status No Respon, aktivitas hanya Lead Baru, 7 hari terakhir.' : 'Buka daftar Lead yang perlu kamu tindak.' }}</small></span>
                        <span class="personal-action-count tone-{{ $action['tone'] }}">{{ number_format($actions[$action['key']], 0, ',', '.') }}</span><span class="personal-action-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
            <div class="personal-desktop-urgent-list">
                @foreach ($actions['items'] as $items)
                    @foreach ($items as $item)
                        <a href="{{ route('leads.show', $item['lead']) }}" class="personal-urgent-item"><span><strong>{{ $item['lead']->nama_konsumen }}</strong><small>{{ $item['reason'] }} · {{ $item['lead']->project?->name ?? 'Proyek belum dipilih' }}</small></span><span class="personal-urgent-button">Buka</span></a>
                    @endforeach
                @endforeach
            </div>
        </section>

        <section class="personal-dashboard-section" aria-labelledby="month-title">
            <div class="personal-section-heading"><div><p class="personal-section-kicker">Ringkasan</p><h2 id="month-title">Bulan ini</h2></div><span class="personal-month-label">{{ $month['label'] }}</span></div>
            <div class="personal-month-summary"><div><strong>{{ number_format($month['activeLeads'], 0, ',', '.') }}</strong><span>Lead aktif</span></div><div><strong>{{ number_format($month['utjMovement'], 0, ',', '.') }}</strong><span>Masuk UTJ</span></div><div><strong>{{ number_format($month['akadMovement'], 0, ',', '.') }}</strong><span>Masuk Akad</span></div></div>
            <div class="personal-stage-card"><div class="personal-stage-bar">@foreach ($month['stages'] as $stage => $count)<span class="{{ $stageColors[$stage] ?? 'stage-neutral' }}" style="--stage-share: {{ $count }}" title="{{ $stage }}: {{ number_format($count, 0, ',', '.') }}"></span>@endforeach</div><div class="personal-stage-legend">@foreach ($month['stages'] as $stage => $count)<span><i class="{{ $stageColors[$stage] ?? 'stage-neutral' }}"></i>{{ $stage }} <b>{{ number_format($count, 0, ',', '.') }}</b></span>@endforeach</div></div>
        </section>

        <section class="personal-desktop-only personal-dashboard-section" aria-labelledby="today-agenda-title">
            <div class="personal-section-heading"><div><p class="personal-section-kicker">Agenda</p><h2 id="today-agenda-title">Agenda hari ini</h2></div></div>
            <div class="personal-timeline">
                @forelse ($todayAgendas as $agenda)
                    <a href="{{ $agenda['detailUrl'] }}" class="personal-timeline-item {{ $agenda['model']->status === 'done' ? 'is-done' : ($agenda['model']->id === ($nextAgenda['model']->id ?? null) ? 'is-next' : '') }}"><span class="personal-timeline-dot"></span><span><strong>{{ $agenda['model']->title }}</strong><small>{{ $agenda['consumer'] ?? 'Tanpa konsumen' }} · {{ $agenda['model']->activity_category }}</small></span></a>
                @empty
                    <p class="personal-muted-copy">Tidak ada agenda hari ini.</p>
                @endforelse
            </div>
        </section>

        @if ($stock)
            <section class="personal-desktop-only personal-dashboard-section" aria-labelledby="stock-title"><div class="personal-section-heading"><div><p class="personal-section-kicker">Kavling</p><h2 id="stock-title">Stok kavling proyek saya</h2></div></div><div class="personal-stock-grid">@foreach ($stock as $projectStock)<div class="personal-stock-card"><strong>{{ $projectStock['project']->name }}</strong><div><span><i class="stock-available"></i>Tersedia <b>{{ number_format($projectStock['available'], 0, ',', '.') }}</b></span><span><i class="stock-reserved"></i>Dipesan <b>{{ number_format($projectStock['reserved'], 0, ',', '.') }}</b></span><span><i class="stock-sold"></i>Terjual <b>{{ number_format($projectStock['sold'], 0, ',', '.') }}</b></span></div></div>@endforeach</div></section>
        @endif
        </div>

        @php
            $desktopActionItems = collect($actions['items'])->flatten(1)->take(3);
        @endphp

        <div class="personal-desktop-dashboard">
            <section class="personal-kpi-grid" aria-label="Ringkasan penjualan">
                <article class="personal-kpi-card"><span>Lead aktif saya</span><strong>{{ number_format($month['activeLeads'], 0, ',', '.') }}</strong><small>{{ number_format($actions['newLeads'], 0, ',', '.') }} lead baru minggu ini</small></article>
                <article class="personal-kpi-card"><span>Agenda hari ini</span><strong>{{ number_format(count($todayAgendas), 0, ',', '.') }}</strong><small>{{ number_format(collect($todayAgendas)->where('model.status', 'done')->count(), 0, ',', '.') }} selesai</small></article>
                <article class="personal-kpi-card is-danger"><span>Follow-up terlambat</span><strong>{{ number_format($actions['overdue'], 0, ',', '.') }}</strong><a href="{{ $actions['urls']['overdue'] }}">Kerjakan sekarang</a></article>
                <article class="personal-kpi-card"><span>Akad bulan ini</span><strong>{{ number_format($month['akadMovement'], 0, ',', '.') }} <small>/ target</small></strong><i><b style="width: {{ min(100, $month['akadMovement'] * 10) }}%"></b></i></article>
            </section>

            <div class="personal-desktop-main-grid">
                <section class="personal-desktop-card" aria-labelledby="desktop-action-title">
                    <div class="personal-section-heading"><div><h2 id="desktop-action-title">Perlu tindakan</h2></div><span class="personal-card-badge">{{ number_format($desktopActionItems->count(), 0, ',', '.') }} item</span></div>
                    <div class="personal-desktop-task-list">
                        @forelse ($desktopActionItems as $item)
                            <a href="{{ route('leads.show', $item['lead']) }}" class="personal-desktop-task"><span class="personal-desktop-task-icon {{ $item['reason'] === 'Follow-up terlambat' ? 'is-danger' : '' }}">{{ $item['reason'] === 'Follow-up terlambat' ? '◷' : '✓' }}</span><span><strong>{{ $item['reason'] }} · {{ $item['lead']->nama_konsumen }}</strong><small>{{ $item['reason'] }} · {{ $item['lead']->project?->name ?? 'Proyek belum dipilih' }}</small></span><span class="personal-desktop-task-action">Buka</span></a>
                        @empty
                            @foreach ([['key' => 'overdue', 'label' => 'Follow-up terlambat'], ['key' => 'today', 'label' => 'Follow-up hari ini'], ['key' => 'new', 'label' => 'Lead baru belum direspons']] as $action)
                                <a href="{{ $actions['urls'][$action['key']] }}" class="personal-desktop-task"><span class="personal-desktop-task-icon {{ $action['key'] === 'overdue' ? 'is-danger' : '' }}">{{ $action['key'] === 'overdue' ? '◷' : '✓' }}</span><span><strong>{{ $action['label'] }}</strong><small>{{ number_format($actions[$action['key']], 0, ',', '.') }} item yang perlu kamu tindak.</small></span><span class="personal-desktop-task-action">Buka</span></a>
                            @endforeach
                        @endforelse
                    </div>
                </section>

                <section class="personal-desktop-card" aria-labelledby="desktop-today-title">
                    <div class="personal-section-heading"><div><h2 id="desktop-today-title">Agenda hari ini</h2></div><a href="{{ route('sales-agendas.index') }}" class="personal-inline-action">Semua agenda</a></div>
                    <div class="personal-desktop-agenda-list">
                        @forelse ($todayAgendas as $agenda)
                            <a href="{{ $agenda['detailUrl'] }}" class="personal-desktop-agenda {{ $agenda['model']->status === 'done' ? 'is-done' : ($agenda['model']->id === ($nextAgenda['model']->id ?? null) ? 'is-next' : '') }}"><time>{{ $agenda['model']->agenda_date->format('H.i') }}</time><span><strong>{{ $agenda['model']->title }}</strong><small>{{ $agenda['consumer'] ?? 'Tanpa konsumen' }}@if ($agenda['project']) · {{ $agenda['project'] }}@endif</small></span></a>
                        @empty
                            <p class="personal-muted-copy">Tidak ada agenda hari ini.</p>
                        @endforelse
                    </div>
                </section>

                <section class="personal-desktop-card" aria-labelledby="desktop-pipeline-title">
                    <div class="personal-section-heading"><div><h2 id="desktop-pipeline-title">Pipeline saya</h2></div><a href="{{ route('leads.index') }}" class="personal-inline-action">Buka daftar lead</a></div>
                    <div class="personal-stage-card"><div class="personal-stage-bar">@foreach ($month['stages'] as $stage => $count)<span class="{{ $stageColors[$stage] ?? 'stage-neutral' }}" style="--stage-share: {{ $count }}"></span>@endforeach</div><div class="personal-stage-legend">@foreach ($month['stages'] as $stage => $count)<span><i class="{{ $stageColors[$stage] ?? 'stage-neutral' }}"></i>{{ $stage }} <b>{{ number_format($count, 0, ',', '.') }}</b></span>@endforeach</div></div>
                </section>

                @if ($stock)
                    <section class="personal-desktop-card" aria-labelledby="desktop-stock-title"><div class="personal-section-heading"><div><h2 id="desktop-stock-title">Stok kavling proyek saya</h2></div><a href="{{ route('admin.spatial-workspace') }}" class="personal-inline-action">Lihat peta kavling</a></div><div class="personal-stock-grid">@foreach ($stock as $projectStock)<div class="personal-stock-card"><strong>{{ $projectStock['project']->name }}</strong><div><span><i class="stock-available"></i>Tersedia <b>{{ number_format($projectStock['available'], 0, ',', '.') }}</b></span><span><i class="stock-reserved"></i>Dipesan <b>{{ number_format($projectStock['reserved'], 0, ',', '.') }}</b></span><span><i class="stock-sold"></i>Terjual <b>{{ number_format($projectStock['sold'], 0, ',', '.') }}</b></span></div></div>@endforeach</div></section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
