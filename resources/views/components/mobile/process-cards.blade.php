@props([
    'type',
    'records',
])

@forelse ($records as $record)
    @if ($type === 'psjb')
        <x-mobile.data-card :title="$record->psjb_id" :subtitle="$record->salesCase->consumer->nama_konsumen.' · '.$record->salesCase->lot->project->name.'-'.$record->salesCase->lot->code" :chip="$record->salesCase->consumer_status">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>Tanggal</small><strong>{{ $record->psjb_date->translatedFormat('d M Y') }}</strong></div><div class="mobile-data-card-detail"><small>Pembayaran</small><strong>{{ $record->salesCase->payment_method->label() }}</strong></div><div class="mobile-data-card-detail"><small>Sales</small><strong>{{ $record->sales?->name ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Pemberkasan</small><strong>{{ $record->filing_status ?? '-' }}</strong></div></x-slot>
            <x-slot name="actions">@can('psjb-utj.update')<a href="{{ route('sales-process.psjb-utj.edit', $record) }}">Edit PSJB</a>@endcan @if ($record->salesCase->consumer_status !== 'MUNDUR') @can('sales-process.update')<button type="button" onclick="document.getElementById('withdraw-psjb-{{ $record->id }}').showModal()">Mundur</button>@endcan @endif</x-slot>
        </x-mobile.data-card>
    @elseif ($type === 'bank')
        <x-mobile.data-card :title="$record->salesCase->consumer->nama_konsumen" :subtitle="$record->salesCase->lot->code.' · '.($record->bank?->name ?? '-')" :chip="str($record->status)->title()" :value="'#'.$record->attempt_number" value-label="Attempt">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>Unit</small><strong>{{ $record->bankUnit?->name ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Hasil</small><strong>{{ $record->result ?? 'PROSES' }}</strong></div><div class="mobile-data-card-detail"><small>SP3K</small><strong>{{ $record->sp3k_number ?? '-' }}</strong></div></x-slot>
        </x-mobile.data-card>
    @elseif ($type === 'filing')
        <x-mobile.data-card :title="$record->filing_id" :subtitle="$record->salesCase->consumer->nama_konsumen.' · '.$record->salesCase->lot->code" :chip="str($record->status)->replace('_', ' ')->title()">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>Bank</small><strong>{{ $record->bank?->name ?? '-' }} · {{ $record->bankUnit?->name ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>SLA</small><strong>{{ $record->deadline_at?->format('d M Y') ?? 'Non-SLA' }}</strong></div></x-slot>
            <x-slot name="actions">@can('filing.update')<form method="POST" action="{{ route('sales-process.pemberkasan.update', $record) }}">@csrf @method('PATCH')<input type="hidden" name="sales_case_id" value="{{ $record->sales_case_id }}"><input type="hidden" name="started_at" value="{{ $record->started_at?->format('Y-m-d') }}"><select name="status" class="module-form-control"><option value="input" @selected($record->status === 'input')>Input</option><option value="incomplete" @selected($record->status === 'incomplete')>Belum lengkap</option><option value="submitted" @selected($record->status === 'submitted')>Diajukan</option><option value="revision" @selected($record->status === 'revision')>Revisi</option><option value="completed" @selected($record->status === 'completed')>Lengkap</option></select><button type="submit">Simpan status</button></form>@endcan</x-slot>
        </x-mobile.data-card>
    @elseif ($type === 'bi')
        <x-mobile.data-card :title="$record->salesCase->consumer->nama_konsumen" :subtitle="$record->salesCase->lot->project->name.'-'.$record->salesCase->lot->code" :chip="$record->salesCase->consumer_status">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>Bank</small><strong>{{ $record->bank->name }} · {{ $record->bankUnit->name }}</strong></div><div class="mobile-data-card-detail"><small>SLIK</small><strong>{{ $record->slik_result }}</strong></div><div class="mobile-data-card-detail"><small>Putusan</small><strong>{{ $record->bank_decision }}</strong></div><div class="mobile-data-card-detail"><small>Lead time</small><strong>{{ $record->lead_time_days }} hari</strong></div></x-slot>
            <x-slot name="actions">@can('bi-checking.update')<a href="{{ route('sales-process.bi-checking.edit', $record) }}">Edit pemeriksaan</a>@endcan @if ($record->salesCase->consumer_status !== 'MUNDUR')<button type="button" onclick="document.getElementById('withdraw-{{ $record->id }}').showModal()">Mundur</button>@endif</x-slot>
        </x-mobile.data-card>
    @elseif ($type === 'akad' || $type === 'bast')
        @php($isAkad = $type === 'akad')
        <x-mobile.data-card :title="$isAkad ? $record->akad_number : $record->bast_number" :subtitle="$record->salesCase->consumer->nama_konsumen.' · '.$record->salesCase->lot->code" :chip="str($record->status)->title()">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>Tanggal</small><strong>{{ ($isAkad ? $record->akad_date : $record->bast_date)?->format('d M Y') ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Progress</small><strong>{{ $record->building_progress ?? 0 }}%</strong></div></x-slot>
        </x-mobile.data-card>
    @elseif ($type === 'ppjb')
        <x-mobile.data-card :title="$record->ppjb_number" :subtitle="$record->salesCase->consumer->nama_konsumen.' · '.$record->salesCase->lot->code" :chip="str($record->status)->replace('_', ' ')->title()">
            <x-slot name="details"><div class="mobile-data-card-detail"><small>SP3K</small><strong>{{ $record->sp3k_date?->format('d M Y') ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Tanda tangan</small><strong>{{ $record->signed_at?->format('d M Y') ?? '-' }}</strong></div><div class="mobile-data-card-detail"><small>Deadline</small><strong>{{ $record->deadline_at?->format('d M Y') ?? '-' }}</strong></div></x-slot>
        </x-mobile.data-card>
    @endif
@empty
    <x-empty-state title="Belum ada data." />
@endforelse
