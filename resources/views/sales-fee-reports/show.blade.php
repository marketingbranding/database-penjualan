<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ $salesUser->name }} · {{ $project?->name ?? 'Semua proyek' }}" description="{{ $filters['date_from'] }} sampai {{ $filters['date_to'] }}">
            <x-slot name="actions"><a href="{{ route('sales-fee-reports.index') }}" class="module-button module-button-secondary">Kembali</a>@can('sales-fee-reports.export')<a href="{{ $project ? route('sales-fee-reports.detail-pdf.project', ['salesUser' => $salesUser, 'project' => $project] + array_filter($filters, fn ($value) => $value !== null && $value !== '')) : route('sales-fee-reports.detail-pdf', ['salesUser' => $salesUser] + array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="module-button module-button-secondary">↓ Unduh PDF</a>@endcan @can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" target="_blank" class="module-button module-button-primary">Cetak</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        <section class="module-summary-grid module-summary-grid-six">
            @foreach (['Total Agenda' => 'total_agenda', 'Agenda Selesai' => 'agenda_selesai', 'Total Lead' => 'total_lead', 'Tatap Muka' => 'tatap_muka', 'Cek Lokasi' => 'cek_lokasi', 'UTJ' => 'utj'] as $label => $key)
                <article class="data-stripes rounded-3xl border border-line bg-surface p-5"><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ number_format($report['totals'][$key]) }}</p></article>
            @endforeach
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-table-card title="Detail Lead" :count="count($report['leads']).' Lead'" class="min-h-0">
                <x-data-table caption="Detail Lead" compact><x-slot name="header"><tr><th>Tanggal</th><th>Konsumen</th><th>Sumber</th><th>Status</th></tr></x-slot><x-slot name="body">
                            @forelse ($report['leads'] as $lead)
                                <tr><td class="whitespace-nowrap text-muted">{{ $lead->tanggal_lead->format('d/m/Y') }}</td><td class="font-semibold">{{ $lead->nama_konsumen }}</td><td class="text-muted">{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</td><td><x-status-badge :status="$lead->status_lead" tone="lime" /></td></tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state title="Tidak ada Lead." /></td></tr>
                            @endforelse
                </x-slot></x-data-table>
            </x-table-card>

            <x-table-card title="Detail agenda" :count="count($report['agendas']).' agenda'" class="min-h-0">
                <x-data-table caption="Detail agenda" compact><x-slot name="header"><tr><th>Tanggal</th><th>Kategori</th><th>Agenda / hasil</th><th>Status</th></tr></x-slot><x-slot name="body">
                            @forelse ($report['agendas'] as $agenda)
                                <tr><td class="whitespace-nowrap text-muted">{{ $agenda->agenda_date->format('d/m/Y') }}</td><td class="text-muted">{{ $agenda->activity_category }}</td><td><div class="font-semibold">{{ $agenda->title }}</div><div class="text-[11px] text-muted">{{ $agenda->activity_result ?: '-' }}</div></td><td><x-status-badge :status="str($agenda->status)->replace('_', ' ')->title()" :tone="$agenda->status === 'done' ? 'success' : ($agenda->status === 'cancelled' ? 'danger' : 'warning')" /></td></tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state title="Tidak ada agenda." /></td></tr>
                            @endforelse
                </x-slot></x-data-table>
            </x-table-card>
        </div>
    </div>
</x-app-layout>
