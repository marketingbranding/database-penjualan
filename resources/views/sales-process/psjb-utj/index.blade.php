<x-app-layout>
    <x-slot name="header"><x-page-header title="PSJB / UTJ" description="Kelola transaksi PSJB, UTJ, konsumen, dan status pemberkasan."><x-slot name="actions">@can('psjb-utj.create')<a href="{{ route('sales-process.psjb-utj.create') }}" class="module-button module-button-primary">＋ Tambah PSJB</a>@endcan</x-slot></x-page-header></x-slot>

    @php($hasActiveFilters = filled($search) || filled($consumerStatus) || filled($filingStatus))
    <div class="module-page">
        @if (session('status') === 'psjb-created')<x-ui.toast message="Data PSJB berhasil dibuat." />@elseif (session('status') === 'psjb-updated')<x-ui.toast message="Data PSJB berhasil diperbarui." />@endif
        <x-table-card title="Daftar PSJB / UTJ" :count="number_format($psjbCases->total()).' transaksi'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('sales-process.psjb-utj') }}" :result-count="$psjbCases->total()" result-label="transaksi" :has-active-filters="$hasActiveFilters" reset-href="{{ route('sales-process.psjb-utj') }}">
                    <x-slot name="search"><x-search-input name="search" :value="$search" placeholder="Cari PSJB, konsumen, NIK, kavling" /></x-slot>
                    <x-slot name="filters"><x-filter-pill label="Status konsumen" name="consumer_status" :options="['' => 'Semua status', 'LANJUT' => 'Lanjut', 'MUNDUR' => 'Mundur']" :selected="$consumerStatus" :active="filled($consumerStatus)" /><x-filter-pill label="Pemberkasan" name="filing_status" :options="['' => 'Semua status', 'INTERNAL' => 'Internal', 'Done' => 'Done']" :selected="$filingStatus" :active="filled($filingStatus)" /></x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Daftar PSJB / UTJ">
                <x-slot name="header"><tr><th>PSJB / Konsumen</th><th>Kavling</th><th>Tanggal PSJB</th><th>Pembayaran</th><th>Sales / Koordinator</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($psjbCases as $psjb)
                        <tr class="align-top"><td><div class="font-semibold text-ink">{{ $psjb->psjb_id }}</div><div>{{ $psjb->salesCase->consumer->nama_konsumen }}</div><div class="text-[11px] text-muted">{{ $psjb->salesCase->consumer->no_ktp }}</div></td><td><div class="font-semibold">{{ $psjb->salesCase->lot->project->name }}-{{ $psjb->salesCase->lot->code }}</div><div class="text-[11px] text-muted">{{ $psjb->salesCase->lot->project->branch->name }}</div></td><td class="whitespace-nowrap text-muted">{{ $psjb->psjb_date->translatedFormat('d M Y') }}</td><td><div class="font-semibold">{{ $psjb->salesCase->payment_method->label() }}</div><div class="text-[11px] text-muted">UTJ Rp{{ number_format((float) $psjb->utj_amount, 0, ',', '.') }}</div></td><td class="text-muted"><div>{{ $psjb->sales?->name ?? '-' }}</div><div class="text-[11px]">{{ $psjb->coordinator?->name ?? '-' }}</div></td><td><x-status-badge :status="$psjb->salesCase->consumer_status" :tone="$psjb->salesCase->consumer_status === 'LANJUT' ? 'success' : 'danger'" /><div class="mt-1 text-[11px] text-muted">Pemberkasan: {{ $psjb->filing_status ?? '-' }}</div></td><td><x-row-actions><x-slot name="primary">@can('psjb-utj.update')<a href="{{ route('sales-process.psjb-utj.edit', $psjb) }}" class="module-row-primary">Edit</a>@endcan</x-slot><x-slot name="menu">@if ($psjb->salesCase->consumer_status !== 'MUNDUR') @can('sales-process.update')<button type="button" class="module-menu-link is-danger" onclick="document.getElementById('withdraw-psjb-{{ $psjb->id }}').showModal()">Mundur</button>@endcan @endif</x-slot></x-row-actions></td></tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state title="Belum ada data PSJB/UTJ." :reset-href="$hasActiveFilters ? route('sales-process.psjb-utj') : null" action-label="Tambah PSJB" action-href="{{ ! $hasActiveFilters && Auth::user()->can('psjb-utj.create') ? route('sales-process.psjb-utj.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$psjbCases" /></x-slot>
        </x-table-card>
        @foreach ($psjbCases as $psjb)
            @if ($psjb->salesCase->consumer_status !== 'MUNDUR')<dialog id="withdraw-psjb-{{ $psjb->id }}" class="module-dialog"><form method="POST" action="{{ route('sales-process.sales-cases.withdraw', $psjb->salesCase) }}" class="module-dialog-form">@csrf @method('PATCH')<h2>Tandai konsumen mundur</h2><p>Kavling akan kembali tersedia jika belum Akad/BAST.</p><textarea name="reason" rows="3" class="workspace-control" placeholder="Alasan mundur" required></textarea><div class="module-dialog-actions"><button type="button" onclick="this.closest('dialog').close()" class="module-button module-button-secondary">Batal</button><x-danger-button>Konfirmasi mundur</x-danger-button></div></form></dialog>@endif
        @endforeach
    </div>
</x-app-layout>
