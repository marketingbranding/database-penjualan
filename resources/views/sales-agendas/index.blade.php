<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-orange">Buku saku sales</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Agenda sales</h1>
                <p class="workspace-page-description mt-1 text-sm text-muted">Jaga follow-up lapangan tetap terlihat dan terjadwal.</p>
            </div>
            @can('create', App\Models\SalesAgenda::class)
                <a href="{{ route('sales-agendas.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Agenda baru</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-ui.toast message="Agenda berhasil diproses." />
        @endif

        <x-ui.card title="Filter agenda" description="Saring agenda berdasarkan cakupan dan waktu." padding="p-5">
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @if (Auth::user()->canAccessAllBranches())
                    <div><label for="branch_id" class="text-sm font-medium text-ink">Cabang</label><select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                @endif
                <div><label for="activity_category" class="text-sm font-medium text-ink">Kategori</label><select id="activity_category" name="activity_category" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua kategori</option>@foreach($categories as $item)<option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>@endforeach</select></div>
                <div><label for="status" class="text-sm font-medium text-ink">Status</label><select id="status" name="status" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua status</option>@foreach($statuses as $item)<option value="{{ $item }}" @selected($status === $item)>{{ str($item)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                <div><label for="date_from" class="text-sm font-medium text-ink">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                <div><label for="date_to" class="text-sm font-medium text-ink">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5"><a href="{{ route('sales-agendas.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Reset</a><button class="inline-flex min-h-10 items-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark">Terapkan filter</button></div>
            </form>
        </x-ui.card>

        <x-ui.card title="Queue agenda" description="{{ number_format($agendas->total()) }} agenda ditemukan." padding="p-0">
            <x-slot:actions><span class="rounded-xl bg-lime-soft px-3 py-2 text-xs font-semibold text-lime-dark">{{ $agendas->currentPage() }} / {{ $agendas->lastPage() }}</span></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Tanggal</th><th class="px-6 py-4">Aktivitas</th><th class="px-6 py-4">Lokasi</th><th class="px-6 py-4">Sales</th><th class="px-6 py-4">Status</th><th class="px-6 py-4"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($agendas as $agenda)
                            <tr class="transition hover:bg-lime-soft/30">
                                <td class="whitespace-nowrap px-6 py-4"><span class="font-semibold text-ink">{{ $agenda->agenda_date->translatedFormat('d M Y') }}</span><div class="mt-1 text-xs text-muted">{{ $agenda->agenda_date->format('H:i') }}</div></td>
                                <td class="px-6 py-4"><div class="font-semibold text-ink">{{ $agenda->title }}</div><div class="mt-1 text-xs text-orange">{{ $agenda->activity_category }}</div>@if($agenda->salesCase)<div class="mt-1 text-xs text-muted">{{ $agenda->salesCase->consumer->nama_konsumen }}</div>@elseif($agenda->lead)<div class="mt-1 text-xs text-muted">{{ $agenda->lead->id_lead }} · {{ $agenda->lead->nama_konsumen }}</div>@endif</td>
                                <td class="px-6 py-4 text-muted">{{ $agenda->location ?: 'Lokasi belum diisi' }}@if($agenda->evidence->isNotEmpty())<div class="mt-1 text-xs text-muted">{{ $agenda->evidence->count() }} foto</div>@endif</td>
                                <td class="px-6 py-4 text-muted">{{ $agenda->creator->name }}</td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-[9px] px-2.5 py-1 text-xs font-medium {{ $agenda->status === 'done' ? 'bg-lime-soft text-lime-dark' : ($agenda->status === 'cancelled' ? 'bg-red-50 text-red-800' : 'bg-orange/20 text-ink') }}">{{ str($agenda->status)->replace('_', ' ')->title() }}</span></td>
                                <td class="px-6 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('sales-agendas.edit', $agenda) }}" class="font-semibold text-lime-dark hover:underline">Edit</a>@can('create', App\Models\Lead::class)<a href="{{ route('leads.create', ['from_agenda' => $agenda->id]) }}" class="font-semibold text-orange hover:underline">Jadikan Lead</a>@endcan @can('update', $agenda) @if($agenda->status === 'planned')<form method="POST" action="{{ route('sales-agendas.complete', $agenda) }}" class="flex items-center gap-2">@csrf @method('PATCH')<input name="activity_result" required placeholder="Hasil" class="workspace-control w-28 px-2 py-1 text-xs"><button class="font-semibold text-lime-dark hover:underline">Selesai</button></form><form method="POST" action="{{ route('sales-agendas.cancel', $agenda) }}" onsubmit="return confirm('Yakin membatalkan agenda ini?')">@csrf @method('PATCH')<button class="font-semibold text-red-700 hover:underline">Batal</button></form>@endif @endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-muted">Belum ada agenda sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($agendas->hasPages())<div class="border-t border-line px-6 py-4">{{ $agendas->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
