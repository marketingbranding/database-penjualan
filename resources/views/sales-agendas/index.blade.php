<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-marison-terracotta-600">Field loop</p>
                <h1 class="mt-1 text-xl font-bold">Agenda Sales</h1>
            </div>
            @can('create', App\Models\SalesAgenda::class)
                <a href="{{ route('sales-agendas.create') }}" class="rounded-lg bg-gray-950 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Agenda Baru</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">Agenda berhasil diproses.</div>
            @endif

            <form method="GET" class="grid gap-4 rounded-2xl border border-marison-line bg-marison-paper p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                @if (Auth::user()->canAccessAllBranches())
                    <div>
                        <label for="branch_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Cabang</label>
                        <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border-gray-300">
                            <option value="">Semua cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="activity_category" class="text-xs font-bold uppercase tracking-wider text-gray-500">Kategori</label>
                    <select id="activity_category" name="activity_category" class="mt-2 block w-full rounded-md border-gray-300">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $item)
                            <option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="text-xs font-bold uppercase tracking-wider text-gray-500">Status</label>
                    <select id="status" name="status" class="mt-2 block w-full rounded-md border-gray-300">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $item)
                            <option value="{{ $item }}" @selected($status === $item)>{{ str_replace('_', ' ', ucfirst($item)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="text-xs font-bold uppercase tracking-wider text-gray-500">Dari tanggal</label>
                    <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="mt-2 block w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="date_to" class="text-xs font-bold uppercase tracking-wider text-gray-500">Sampai tanggal</label>
                    <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="mt-2 block w-full rounded-md border-gray-300">
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <button class="rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
                    <a href="{{ route('sales-agendas.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Reset</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-marison-line bg-marison-paper shadow-sm">
                <div class="flex items-center justify-between border-b border-marison-line px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-widest text-gray-500">Activity queue</p><p class="mt-1 text-sm text-gray-500">{{ number_format($agendas->total()) }} agenda ditemukan</p></div><span class="rounded-full bg-marison-cream-50 px-3 py-1 text-xs font-bold text-marison-forest-950">{{ $agendas->currentPage() }} / {{ $agendas->lastPage() }}</span></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-marison-line text-sm">
                        <thead class="bg-marison-cream-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500">
                            <tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Aktivitas</th><th class="px-6 py-3">Lokasi</th><th class="px-6 py-3">Sales</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-marison-line">
                            @forelse ($agendas as $agenda)
                                <tr class="transition hover:bg-marison-cream-50">
                                    <td class="whitespace-nowrap px-6 py-4 font-semibold text-gray-900">{{ $agenda->agenda_date->translatedFormat('d M Y') }}</td>
                                    <td class="px-6 py-4"><div class="font-semibold text-gray-950">{{ $agenda->title }}</div><div class="text-xs text-amber-700">{{ $agenda->activity_category }}</div>@if($agenda->salesCase)<div class="mt-1 text-xs text-gray-500">{{ $agenda->salesCase->consumer->nama_konsumen }}</div>@elseif($agenda->lead)<div class="mt-1 text-xs text-gray-500">{{ $agenda->lead->id_lead }} · {{ $agenda->lead->nama_konsumen }}</div>@endif</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $agenda->location ?: '-' }}@if($agenda->evidence->isNotEmpty())<div class="text-xs text-gray-500">{{ $agenda->evidence->count() }} foto</div>@endif</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $agenda->creator->name }}</td>
                                    <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $agenda->status === 'done' ? 'bg-emerald-100 text-emerald-800' : ($agenda->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900') }}">{{ str_replace('_', ' ', ucfirst($agenda->status)) }}</span></td>
                                     <td class="px-6 py-4 text-right"><div class="flex justify-end gap-3"><a href="{{ route('sales-agendas.edit', $agenda) }}" class="font-semibold text-gray-700">Edit</a>@can('create', App\Models\Lead::class)<a href="{{ route('leads.create', ['from_agenda' => $agenda->id]) }}" class="whitespace-nowrap font-semibold text-marison-teal-600">Jadikan Lead</a>@endcan @can('update', $agenda) @if($agenda->status === 'planned')<form method="POST" action="{{ route('sales-agendas.complete', $agenda) }}" class="flex items-center gap-2">@csrf @method('PATCH')<input name="activity_result" required placeholder="Hasil" class="w-28 rounded-md border-gray-300 px-2 py-1 text-xs"><button class="font-semibold text-emerald-700">Selesai</button></form><form method="POST" action="{{ route('sales-agendas.cancel', $agenda) }}" class="ml-2" onsubmit="return confirm('Yakin membatalkan agenda ini?')">@csrf @method('PATCH')<button class="rounded-md border border-red-200 px-2 py-1 font-semibold text-red-700">Batal</button></form>@endif @endcan</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">Belum ada agenda sesuai filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($agendas->hasPages())
                    <div class="border-t border-marison-line px-6 py-4">{{ $agendas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
