<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Monitoring Buku Saku" description="Pantau rencana kerja Sales dalam Team {{ $team->name }}.">
            <x-slot name="actions"><a href="{{ route('dashboard') }}" class="module-button module-button-secondary">Kembali ke dashboard</a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        <x-table-card title="Rencana kerja {{ \Carbon\CarbonImmutable::parse($date)->locale('id')->translatedFormat('d F Y') }}" :count="number_format($plans->count(), 0, ',', '.').' Sales'" class="min-h-0">
            <x-data-table caption="Monitoring rencana kerja Team" compact>
                <x-slot name="header"><tr><th>Sales</th><th>Jumlah Agenda</th><th>Durasi</th><th>Status</th><th>Dikirim</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($plans as $plan)
                        @php($minutes = $planner->effectiveMinutes($plan))
                        <tr>
                            <td class="font-semibold">{{ $plan->sales->name }}</td>
                            <td>{{ $plan->agendas->where('status', '!=', 'cancelled')->count() }}</td>
                            <td>{{ $minutes }} / {{ $requiredMinutes }} menit</td>
                            <td><x-status-badge :status="str($plan->status)->title()" :tone="$plan->status === 'submitted' ? 'success' : 'neutral'" /></td>
                            <td class="text-muted">{{ $plan->submitted_at?->setTimezone(config('app.business_timezone'))->format('H:i') ?? '—' }}</td>
                            <td class="text-right"><a href="{{ route('daily-plans.show', $plan) }}" class="module-row-primary">Lihat rencana</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada rencana kerja." description="Sales dalam Team ini belum membuat rencana pada tanggal tersebut." /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($plans as $plan)
                        @php($minutes = $planner->effectiveMinutes($plan))
                        <x-mobile.data-card :title="$plan->sales->name" :subtitle="$plan->agendas->where('status', '!=', 'cancelled')->count().' agenda · '.$minutes.' / '.$requiredMinutes.' menit'" :chip="str($plan->status)->title()" :href="route('daily-plans.show', $plan)">
                            <x-slot name="details"><div class="mobile-data-card-detail"><small>Dikirim</small><strong>{{ $plan->submitted_at?->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') ?? 'Belum dikirim' }}</strong></div></x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada rencana kerja." description="Sales dalam Team ini belum membuat rencana pada tanggal tersebut." />
                    @endforelse
                </x-slot>
            </x-data-table>
        </x-table-card>
    </div>
</x-app-layout>
