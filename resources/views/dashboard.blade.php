<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-medium text-lime-dark">Workspace operasional</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Selamat datang kembali, {{ str(Auth::user()->name)->before(' ') }}</h1>
            <p class="mt-1 text-sm text-muted">Pantau Lead, agenda, dan ritme kerja tim dalam satu ruang.</p>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-ui.stat-card label="Lead aktif" :value="number_format($metrics['leads'])" tone="lime">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M5 19V9m7 10V5m7 14v-7" /></svg></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Agenda mendatang" :value="number_format($metrics['plannedAgendas'])" description="Agenda terjadwal mulai hari ini." tone="orange">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M8 3v4m8-4v4M4 10h16m-9 4h.01M15 14h.01M9 17h.01M15 17h.01" /></svg></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Agenda selesai" :value="number_format($metrics['completedAgendas'])" description="Selesai sejak awal bulan ini." tone="surface">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="m5 12 4 4L19 6" /></svg></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Konsumen" :value="number_format($metrics['consumers'])" description="Konsumen dengan proses aktif." tone="ink">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m6-9a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm6-7.5a3.5 3.5 0 0 1 0 6.8M17 15a3.5 3.5 0 0 1 3 3.5V20" /></svg></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Kavling tersedia" :value="number_format($metrics['availableLots'])" description="Kavling yang masih tersedia." tone="surface">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19h16M6 16V8l6-4 6 4v8M9 19v-5h6v5" /></svg></x-slot:icon>
            </x-ui.stat-card>
            <x-ui.stat-card label="Kavling terjual" :value="number_format($metrics['soldLots'])" description="Kavling dengan status akad." tone="yellow">
                <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m5 12 4 4L19 6" /></svg></x-slot:icon>
            </x-ui.stat-card>
        </section>

        <section>
            <x-ui.card title="Aksi cepat" description="Buka pekerjaan yang paling sering digunakan." padding="p-6">
                <div class="grid gap-2">
                    @can('create', App\Models\Lead::class)
                        <a href="{{ route('leads.create') }}" class="flex min-h-11 items-center gap-3 rounded-2xl bg-lime px-4 py-3 text-sm font-semibold text-on-accent transition hover:bg-lime-dark hover:text-white"><span class="grid size-7 place-items-center rounded-xl bg-white/40">+</span>Lead baru</a>
                    @endcan
                    @can('create', App\Models\SalesAgenda::class)
                        <a href="{{ route('sales-agendas.create') }}" class="flex min-h-11 items-center gap-3 rounded-2xl border border-line px-4 py-3 text-sm font-medium text-ink transition hover:bg-lime-soft"><span class="grid size-7 place-items-center rounded-xl bg-orange/20 text-orange">+</span>Agenda baru</a>
                    @endcan
                    @can('bi-checking.create')
                        <a href="{{ route('sales-process.bi-checking.create') }}" class="flex min-h-11 items-center gap-3 rounded-2xl border border-line px-4 py-3 text-sm font-medium text-ink transition hover:bg-lime-soft"><span class="grid size-7 place-items-center rounded-xl bg-yellow/40 text-ink">↗</span>Mulai SLIK</a>
                    @endcan
                    @can('psjb-utj.create')
                        <a href="{{ route('sales-process.psjb-utj.create') }}" class="flex min-h-11 items-center gap-3 rounded-2xl border border-line px-4 py-3 text-sm font-medium text-ink transition hover:bg-lime-soft"><span class="grid size-7 place-items-center rounded-xl bg-orange/20 text-orange">↗</span>Buat UTJ</a>
                    @endcan
                </div>
            </x-ui.card>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <x-ui.card title="Agenda mendatang" description="Pekerjaan lapangan yang perlu dijaga ritmenya." padding="p-0">
                <x-slot:actions>
                    @can('create', App\Models\SalesAgenda::class)
                        <a href="{{ route('sales-agendas.create') }}" class="rounded-xl bg-lime px-3 py-2 text-xs font-semibold text-on-accent transition hover:bg-lime-dark hover:text-white">Agenda baru</a>
                    @endcan
                </x-slot:actions>
                @forelse ($upcomingAgendas as $agenda)
                    <a href="{{ route('sales-agendas.edit', $agenda) }}" class="flex items-center gap-4 border-t border-line px-6 py-4 transition first:border-t-0 hover:bg-lime-soft/30">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-orange/20 text-xs font-semibold text-ink">{{ $agenda->agenda_date->format('d/m') }}</span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-ink">{{ $agenda->title }}</span><span class="mt-1 block truncate text-xs text-muted">{{ $agenda->activity_category }} · {{ $agenda->location ?: 'Lokasi belum diisi' }}</span>@if($agenda->lead)<span class="mt-1 block truncate text-xs font-medium text-lime-dark">{{ $agenda->lead->id_lead }} · {{ $agenda->lead->nama_konsumen }}</span>@elseif($agenda->salesCase)<span class="mt-1 block truncate text-xs font-medium text-lime-dark">{{ $agenda->salesCase->consumer->nama_konsumen }}</span>@endif</span>
                        <svg class="size-4 shrink-0 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6" /></svg>
                    </a>
                @empty
                    <div class="px-6 py-10">
                        <p class="text-sm font-semibold text-ink">Belum ada agenda mendatang.</p>
                        <p class="mt-1 text-sm leading-6 text-muted">Buat agenda agar follow-up Lead tidak terlewat.</p>
                        @can('create', App\Models\SalesAgenda::class)
                            <a href="{{ route('sales-agendas.create') }}" class="mt-4 inline-flex rounded-xl bg-lime px-4 py-2.5 text-sm font-semibold text-on-accent transition hover:bg-lime-dark hover:text-white">Buat agenda baru</a>
                        @endcan
                    </div>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Lead terbaru" description="Lead yang baru masuk ke ruang kerja." padding="p-0">
                <x-slot:actions>
                    <a href="{{ route('leads.index') }}" class="text-xs font-semibold text-lime-dark hover:underline">Lihat semua</a>
                </x-slot:actions>
                @forelse ($recentLeads as $lead)
                    <a href="{{ route('leads.show', $lead) }}" class="flex items-center gap-3 border-t border-line px-6 py-4 transition first:border-t-0 hover:bg-lime-soft/30">
                        <x-ui.avatar :name="$lead->nama_konsumen" size="sm" />
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-ink">{{ $lead->nama_konsumen }}</span><span class="mt-1 block truncate text-xs text-muted">{{ $lead->id_lead }} · {{ $lead->salesPic?->name ?? 'Sales belum ditentukan' }}</span></span>
                        <x-ui.stage-chip :stage="$lead->status_lead" size="sm" />
                    </a>
                @empty
                    <p class="px-6 py-10 text-sm text-muted">Belum ada Lead terbaru.</p>
                @endforelse
            </x-ui.card>
        </section>

        <x-ui.card title="Aktivitas terbaru" description="Perubahan terakhir pada Lead dan Agenda." padding="p-6">
            @if ($activityFeed->isNotEmpty())
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($activityFeed as $activity)
                        <a href="{{ $activity['url'] }}" class="flex gap-3 rounded-2xl border border-line p-4 transition hover:border-lime hover:bg-lime-soft/30">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $activity['accent'] === 'terracotta' ? 'bg-orange' : 'bg-lime' }}"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-ink">{{ $activity['label'] }}</span><span class="mt-1 block text-xs leading-5 text-muted">{{ $activity['description'] }}</span><time class="mt-2 block text-[11px] text-muted" datetime="{{ $activity['date']->toIso8601String() }}">{{ $activity['date']->diffForHumans() }}</time></span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">Belum ada aktivitas terbaru.</p>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
