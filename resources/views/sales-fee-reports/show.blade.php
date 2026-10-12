<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-lime-dark">Performa sales</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">{{ $salesUser->name }} · {{ $project?->name ?? 'Semua proyek' }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $filters['date_from'] }} sampai {{ $filters['date_to'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sales-fee-reports.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Kembali</a>
                @can('sales-fee-reports.print')
                    <a href="{{ route('sales-fee-reports.print', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" target="_blank" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Cetak</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach (['Total Agenda' => 'total_agenda', 'Agenda Selesai' => 'agenda_selesai', 'Total Lead' => 'total_lead', 'Tatap Muka' => 'tatap_muka', 'Cek Lokasi' => 'cek_lokasi', 'UTJ' => 'utj'] as $label => $key)
                <article class="data-stripes rounded-3xl border border-line bg-surface p-5"><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ number_format($report['totals'][$key]) }}</p></article>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Detail Lead" padding="p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-line bg-canvas text-left text-xs font-medium text-muted"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Konsumen</th><th class="px-6 py-3">Sumber</th><th class="px-6 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($report['leads'] as $lead)
                                <tr class="transition hover:bg-lime-soft/30"><td class="px-6 py-3 text-muted">{{ $lead->tanggal_lead->format('d/m/Y') }}</td><td class="px-6 py-3 font-semibold text-ink">{{ $lead->nama_konsumen }}</td><td class="px-6 py-3 text-muted">{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</td><td class="px-6 py-3"><x-ui.stage-chip :stage="$lead->status_lead" size="sm" /></td></tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-muted">Tidak ada Lead.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Detail agenda" padding="p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-line bg-canvas text-left text-xs font-medium text-muted"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Kategori</th><th class="px-6 py-3">Agenda / hasil</th><th class="px-6 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($report['agendas'] as $agenda)
                                <tr class="transition hover:bg-lime-soft/30"><td class="px-6 py-3 text-muted">{{ $agenda->agenda_date->format('d/m/Y') }}</td><td class="px-6 py-3 text-muted">{{ $agenda->activity_category }}</td><td class="px-6 py-3"><div class="font-semibold text-ink">{{ $agenda->title }}</div><div class="text-xs text-muted">{{ $agenda->activity_result ?: '-' }}</div></td><td class="px-6 py-3"><x-ui.pill :tone="$agenda->status === 'done' ? 'success' : ($agenda->status === 'cancelled' ? 'error' : 'orange')">{{ str($agenda->status)->replace('_', ' ')->title() }}</x-ui.pill></td></tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-muted">Tidak ada agenda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
