@php
    $sideLeadStatuses = ['Jadi Freelance', 'Tidak Lolos BI Checking'];
    $funnelLeadStatuses = array_values(array_diff($statuses, $sideLeadStatuses));
    $stageDotClasses = [
        'No Respon' => 'stage-neutral',
        'Diskusi' => 'stage-discussion',
        'Tatap Muka' => 'stage-face-to-face',
        'Cek Lokasi' => 'stage-site-visit',
        'UTJ' => 'stage-utj',
        'Cek SLIK' => 'stage-slik',
        'Akad' => 'stage-akad',
        'Jadi Freelance' => 'stage-freelance',
        'Tidak Lolos BI Checking' => 'stage-side-outcome',
    ];
@endphp
<div class="mobile-filter-main" data-mobile-filter-main data-business-timezone="{{ config('app.business_timezone') }}">
    <section class="mobile-filter-section" data-filter-section="date">
        <h3>Tanggal lead</h3>
        <div class="mobile-filter-chip-grid">
            <button type="button" class="mobile-sheet-chip" data-mobile-date-preset="all" aria-pressed="false">Semua waktu</button>
            <button type="button" class="mobile-sheet-chip" data-mobile-date-preset="last-7-days" aria-pressed="false">7 hari terakhir</button>
            <button type="button" class="mobile-sheet-chip" data-mobile-date-preset="this-month" aria-pressed="false">Bulan ini</button>
            <button type="button" class="mobile-sheet-chip" data-mobile-date-preset="last-month" aria-pressed="false">Bulan lalu</button>
            <button type="button" class="mobile-sheet-chip" data-mobile-date-preset="custom" aria-pressed="false">▣ Pilih tanggal</button>
        </div>
        <div class="mobile-filter-date-fields" data-mobile-date-fields hidden>
            <label><span>Dari</span><input type="date" name="date_from" value="{{ request('date_from') }}" data-filter-label="Tanggal lead"></label>
            <label><span>Sampai</span><input type="date" name="date_to" value="{{ request('date_to') }}" data-filter-label="Tanggal lead"></label>
        </div>
        <p class="mobile-filter-date-summary" data-mobile-date-summary hidden></p>
    </section>

    <section class="mobile-filter-section" data-filter-section="status">
        <h3>Tahap</h3>
        <input type="hidden" name="status_lead" value="{{ $status }}" data-filter-label="Tahap">
        <div class="mobile-filter-chip-grid" data-status-chips>
            <button type="button" class="mobile-sheet-chip" data-mobile-filter-chip="status_lead" data-value="" aria-pressed="{{ blank($status) ? 'true' : 'false' }}">Semua</button>
            @foreach ($funnelLeadStatuses as $leadStatus)
                <button type="button" class="mobile-sheet-chip" data-mobile-filter-chip="status_lead" data-value="{{ $leadStatus }}" aria-pressed="{{ $status === $leadStatus ? 'true' : 'false' }}"><span class="mobile-stage-dot {{ $stageDotClasses[$leadStatus] ?? 'stage-neutral' }}"></span>{{ $leadStatus }}</button>
            @endforeach
            <button type="button" class="mobile-sheet-chip mobile-sheet-chip-more" data-expand-statuses aria-expanded="false">+{{ count($sideLeadStatuses) }} lainnya</button>
            <div class="mobile-filter-secondary-statuses" data-secondary-statuses hidden>
                @foreach ($sideLeadStatuses as $leadStatus)
                    <button type="button" class="mobile-sheet-chip" data-mobile-filter-chip="status_lead" data-value="{{ $leadStatus }}" aria-pressed="{{ $status === $leadStatus ? 'true' : 'false' }}"><span class="mobile-stage-dot {{ $stageDotClasses[$leadStatus] ?? 'stage-side-outcome' }}"></span>{{ $leadStatus }}</button>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mobile-filter-switch-card">
        <div><h3>Hanya lead aktif</h3><p>Sembunyikan Akad dan lead yang gugur</p></div>
        <input type="checkbox" name="active" value="1" @checked($active) data-switch-input data-filter-label="Hanya lead aktif">
        <button type="button" class="mobile-filter-switch" role="switch" aria-checked="{{ $active ? 'true' : 'false' }}" data-mobile-switch="active" aria-label="Hanya lead aktif"><span></span></button>
    </section>

    <section class="mobile-filter-section">
        <h3>Follow-up</h3>
        <input type="hidden" name="follow_up" value="{{ $followUp }}" data-filter-label="Follow-up">
        <div class="mobile-filter-chip-grid">
            @foreach (['' => 'Semua jadwal', 'today' => 'Hari ini', 'overdue' => 'Terlambat', 'upcoming' => 'Akan datang'] as $followUpValue => $followUpLabel)
                <button type="button" class="mobile-sheet-chip" data-mobile-filter-chip="follow_up" data-value="{{ $followUpValue }}" aria-pressed="{{ $followUp === $followUpValue ? 'true' : 'false' }}">{{ $followUpLabel }}</button>
            @endforeach
        </div>
    </section>

    <section class="mobile-filter-section mobile-filter-row-section">
        <h3>Tim &amp; lokasi</h3>
        @if ($projects->isNotEmpty())
            <button type="button" class="mobile-filter-row" data-open-picker="project_id"><span>Proyek</span><strong data-picker-value="project_id">{{ $projects->firstWhere('id', request('project_id'))?->name ?? 'Semua proyek' }}</strong><span aria-hidden="true">›</span></button>
        @endif
        @if ($salesUsers->isNotEmpty())
            <button type="button" class="mobile-filter-row" data-open-picker="sales_pic_id"><span>Sales</span><strong data-picker-value="sales_pic_id">{{ $salesUsers->firstWhere('id', $salesPicId)?->name ?? 'Semua sales' }}</strong><span aria-hidden="true">›</span></button>
        @endif
        <button type="button" class="mobile-filter-row" data-open-picker="sumber_lead"><span>Sumber</span><strong data-picker-value="sumber_lead">{{ $source ?: 'Semua sumber' }}</strong><span aria-hidden="true">›</span></button>
        <button type="button" class="mobile-filter-row" data-open-picker="kanal_masuk"><span>Kanal</span><strong data-picker-value="kanal_masuk">{{ $channel ?: 'Semua kanal' }}</strong><span aria-hidden="true">›</span></button>
        @if (Auth::user()->canAccessAllBranches() && $branches->count() > 1)
            <button type="button" class="mobile-filter-row" data-open-picker="branch_id"><span>Cabang</span><strong data-picker-value="branch_id">{{ $branches->firstWhere('id', $branchId)?->name ?? 'Semua cabang' }}</strong><span aria-hidden="true">›</span></button>
        @endif
        <input type="hidden" name="project_id" value="{{ request('project_id') }}" data-picker-input data-filter-label="Proyek">
        <input type="hidden" name="sales_pic_id" value="{{ $salesPicId }}" data-picker-input data-filter-label="Sales">
        <input type="hidden" name="sumber_lead" value="{{ $source }}" data-picker-input data-filter-label="Sumber">
        <input type="hidden" name="kanal_masuk" value="{{ $channel }}" data-picker-input data-filter-label="Kanal">
        @if (Auth::user()->canAccessAllBranches() && $branches->count() > 1)
            <input type="hidden" name="branch_id" value="{{ $branchId }}" data-picker-input data-filter-label="Cabang">
        @endif
    </section>
</div>

@foreach ([
    'project_id' => ['title' => 'Pilih proyek', 'empty' => 'Semua proyek', 'options' => $projects->map(fn ($item): array => ['value' => $item->id, 'label' => $item->name])->all()],
    'sales_pic_id' => ['title' => 'Pilih sales', 'empty' => 'Semua sales', 'options' => $salesUsers->map(fn ($item): array => ['value' => $item->id, 'label' => $item->name, 'group' => $item->teams->first()?->coordinator?->name])->all()],
    'sumber_lead' => ['title' => 'Pilih sumber', 'empty' => 'Semua sumber', 'options' => collect($sources)->map(fn ($item): array => ['value' => $item, 'label' => $item])->all()],
    'kanal_masuk' => ['title' => 'Pilih kanal', 'empty' => 'Semua kanal', 'options' => collect($channels)->map(fn ($item): array => ['value' => $item, 'label' => $item])->all()],
    'branch_id' => ['title' => 'Pilih cabang', 'empty' => 'Semua cabang', 'options' => $branches->map(fn ($item): array => ['value' => $item->id, 'label' => $item->name])->all()],
] as $pickerName => $picker)
    @if ($picker['options'])
        <section class="mobile-filter-picker" data-picker-panel="{{ $pickerName }}" hidden>
            <div class="mobile-filter-picker-header"><button type="button" data-picker-back aria-label="Kembali">‹</button><h3>{{ $picker['title'] }}</h3></div>
            <label class="mobile-filter-picker-search"><span aria-hidden="true">⌕</span><input type="search" data-picker-search placeholder="Cari {{ strtolower(str_replace('Pilih ', '', $picker['title'])) }}" aria-label="Cari {{ strtolower(str_replace('Pilih ', '', $picker['title'])) }}"></label>
            <div class="mobile-filter-picker-list" role="radiogroup" aria-label="{{ $picker['title'] }}">
                <button type="button" role="radio" class="mobile-filter-picker-option" data-picker-option data-value="" data-label="{{ $picker['empty'] }}"><span class="mobile-picker-radio"></span><strong>{{ $picker['empty'] }}</strong></button>
                @php($pickerGroups = collect($picker['options'])->groupBy('group'))
                @foreach ($pickerGroups as $group => $options)
                    @if ($group)<h4 class="mobile-filter-picker-group">Tim {{ $group }}</h4>@endif
                    @foreach ($options as $option)
                        <button type="button" role="radio" class="mobile-filter-picker-option" data-picker-option data-value="{{ $option['value'] }}" data-label="{{ $option['label'] }}" data-search-text="{{ $option['label'].' '.$group }}"><span class="mobile-picker-radio"></span><span><strong>{{ $option['label'] }}</strong>@if (! empty($option['group']))<small>{{ $option['group'] }}</small>@endif</span></button>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif
@endforeach
