<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-medium text-orange">Pencarian workspace</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Hasil pencarian</h1>
            <p class="workspace-page-description mt-1 text-sm text-muted">{{ mb_strlen($query) >= 2 ? 'Menampilkan hasil untuk “'.$query.'”.' : 'Masukkan minimal 2 karakter untuk mencari.' }}</p>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('search') }}" class="workspace-card flex flex-col gap-3 p-4 sm:flex-row sm:p-5">
            <label for="workspace-search" class="sr-only">Cari di workspace</label>
            <input id="workspace-search" name="q" value="{{ $query }}" autofocus placeholder="Cari lead, agenda, konsumen..." class="workspace-control min-w-0 flex-1 bg-canvas px-4 py-2.5 text-sm text-ink placeholder:text-muted">
            <button class="inline-flex min-h-10 w-full items-center justify-center rounded-2xl bg-ink px-5 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark sm:w-auto">Cari</button>
        </form>

        @if ($query)
            <div class="grid gap-5 lg:grid-cols-2">
                <section class="workspace-card overflow-hidden">
                    <div class="border-b border-line px-6 py-5">
                        <p class="text-xs font-medium text-orange">Buku saku</p>
                        <h2 class="mt-1 text-lg font-semibold tracking-tight text-ink">Leads ({{ $leads->count() }})</h2>
                    </div>
                    <div class="divide-y divide-line">
                        @forelse ($leads as $lead)
                            <a href="{{ route('leads.show', $lead) }}" class="block px-6 py-4 transition hover:bg-lime-soft/30">
                                <p class="font-semibold text-ink">{{ $lead->nama_konsumen }}</p>
                                <p class="mt-1 text-xs leading-5 text-muted">{{ $lead->id_lead }} · {{ $lead->salesPic->name }} · {{ $lead->status_lead }}</p>
                            </a>
                        @empty
                            <p class="px-6 py-8 text-sm text-muted">Tidak ada Lead.</p>
                        @endforelse
                    </div>
                </section>

                <section class="workspace-card overflow-hidden">
                    <div class="border-b border-line px-6 py-5">
                        <p class="text-xs font-medium text-orange">Buku saku</p>
                        <h2 class="mt-1 text-lg font-semibold tracking-tight text-ink">Agenda ({{ $agendas->count() }})</h2>
                    </div>
                    <div class="divide-y divide-line">
                        @forelse ($agendas as $agenda)
                            <a href="{{ route('sales-agendas.edit', $agenda) }}" class="block px-6 py-4 transition hover:bg-lime-soft/30">
                                <p class="font-semibold text-ink">{{ $agenda->title }}</p>
                                <p class="mt-1 text-xs leading-5 text-muted">{{ $agenda->agenda_date->format('d/m/Y') }} · {{ $agenda->activity_category }}</p>
                            </a>
                        @empty
                            <p class="px-6 py-8 text-sm text-muted">Tidak ada Agenda.</p>
                        @endforelse
                    </div>
                </section>

                <section class="workspace-card overflow-hidden">
                    <div class="border-b border-line px-6 py-5">
                        <p class="text-xs font-medium text-orange">Penjualan</p>
                        <h2 class="mt-1 text-lg font-semibold tracking-tight text-ink">Konsumen ({{ $consumers->count() }})</h2>
                    </div>
                    <div class="divide-y divide-line">
                        @forelse ($salesCases as $salesCase)
                            <a href="{{ route('consumers.show', $salesCase) }}" class="block px-6 py-4 transition hover:bg-lime-soft/30">
                                <p class="font-semibold text-ink">{{ $salesCase->consumer->nama_konsumen }}</p>
                                <p class="mt-1 text-xs leading-5 text-muted">{{ $salesCase->consumer->no_hp }} · {{ $salesCase->lot->project->name }}</p>
                            </a>
                        @empty
                            <p class="px-6 py-8 text-sm text-muted">Tidak ada Konsumen.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
