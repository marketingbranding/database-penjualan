<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-marison-terracotta-600">Lead desk</p>
                <h1 class="mt-1 text-xl font-bold">Leads</h1>
            </div>
            @can('create', App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="rounded-lg bg-gray-950 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Lead Baru</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">Lead berhasil diproses.</div>
            @endif
            <form method="GET" class="grid gap-4 rounded-2xl border border-marison-line bg-marison-paper p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                @if (Auth::user()->canAccessAllBranches())
                    <div><label for="branch_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Cabang</label><select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                @endif
                <div><label for="status_lead" class="text-xs font-bold uppercase tracking-wider text-gray-500">Status Lead</label><select id="status_lead" name="status_lead" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua status</option>@foreach($statuses as $item)<option value="{{ $item }}" @selected($status === $item)>{{ $item }}</option>@endforeach</select></div>
                <div><label for="sumber_lead" class="text-xs font-bold uppercase tracking-wider text-gray-500">Sumber</label><select id="sumber_lead" name="sumber_lead" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua sumber</option>@foreach($sources as $item)<option value="{{ $item }}" @selected($source === $item)>{{ $item }}</option>@endforeach</select></div>
                <div><label for="kanal_masuk" class="text-xs font-bold uppercase tracking-wider text-gray-500">Kanal</label><select id="kanal_masuk" name="kanal_masuk" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua kanal</option>@foreach($channels as $item)<option value="{{ $item }}" @selected($channel === $item)>{{ $item }}</option>@endforeach</select></div>
                <div><label for="date_from" class="text-xs font-bold uppercase tracking-wider text-gray-500">Dari</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="mt-2 block w-full rounded-md border-gray-300"></div>
                <div><label for="date_to" class="text-xs font-bold uppercase tracking-wider text-gray-500">Sampai</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="mt-2 block w-full rounded-md border-gray-300"></div>
                <div><label for="search" class="text-xs font-bold uppercase tracking-wider text-gray-500">Cari</label><input id="search" name="search" value="{{ request('search') }}" placeholder="ID, nama, no. HP" class="mt-2 block w-full rounded-md border-gray-300"></div>
                <div class="flex items-end gap-2"><button class="rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('leads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Reset</a></div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-marison-line bg-marison-paper shadow-sm">
                <div class="flex items-center justify-between border-b border-marison-line px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-widest text-gray-500">Pipeline</p><p class="mt-1 text-sm text-gray-500">{{ number_format($leads->total()) }} Lead ditemukan</p></div><span class="rounded-full bg-marison-cream-100 px-3 py-1 text-xs font-bold text-marison-forest-950">{{ $leads->currentPage() }} / {{ $leads->lastPage() }}</span></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-marison-line text-sm"><thead class="bg-marison-cream-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Konsumen</th><th class="px-6 py-3">Sales PIC</th><th class="px-6 py-3">Proyek</th><th class="px-6 py-3">Sumber</th><th class="px-6 py-3">Kanal / Aktivitas</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-marison-line">
                    @forelse($leads as $lead)
                        <tr class="transition hover:bg-marison-cream-50"><td class="whitespace-nowrap px-6 py-4 font-semibold">{{ $lead->tanggal_lead->translatedFormat('d M Y') }}<div class="text-xs font-normal text-gray-500">{{ $lead->id_lead }}</div></td><td class="px-6 py-4"><div class="font-semibold text-marison-ink-950">{{ $lead->nama_konsumen }}</div><div class="text-xs text-gray-500">{{ $lead->no_hp }}</div></td><td class="px-6 py-4 text-gray-600">{{ $lead->salesPic->name }}</td><td class="px-6 py-4 text-gray-600">{{ $lead->project?->name ?? '-' }}</td><td class="px-6 py-4 text-gray-600">{{ $lead->sumber_lead }}</td><td class="px-6 py-4 text-gray-600">{{ $lead->kanal_masuk }} / {{ $lead->aktivitas_lead }}</td><td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ match($lead->status_lead) { 'UTJ' => 'bg-emerald-100 text-emerald-800', 'Tatap Muka', 'Cek Lokasi' => 'bg-cyan-100 text-cyan-800', 'Diskusi' => 'bg-amber-100 text-amber-900', default => 'bg-gray-100 text-gray-700' } }}">{{ $lead->status_lead }}</span></td><td class="px-6 py-4 text-right"><a href="{{ route('leads.show', $lead) }}" class="font-semibold text-marison-terracotta-600 hover:text-marison-terracotta-800">Detail</a></td></tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-14 text-center text-sm text-gray-500">Belum ada lead sesuai filter.</td></tr>
                    @endforelse
                </tbody></table></div>
                @if($leads->hasPages())<div class="border-t border-marison-line px-6 py-4">{{ $leads->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
