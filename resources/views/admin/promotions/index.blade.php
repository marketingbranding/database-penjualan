<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-amber-700">Pengaturan</p><h1 class="mt-1 text-xl font-bold">Promo</h1></div>@can('promotions.create')<a href="{{ route('admin.promotions.create') }}" class="rounded-lg bg-gray-950 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Tambah promo</a>@endcan</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('status') === 'promotion-created')<div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">Promo berhasil dibuat.</div>@endif
        @if (session('status') === 'promotion-updated')<div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">Promo berhasil diperbarui.</div>@endif
        @if (session('status') === 'promotion-deleted')<div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">Promo berhasil dihapus.</div>@endif
        @if (session('status') === 'promotion-toggled')<div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">Status promo berhasil diperbarui.</div>@endif

        <form method="GET" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-3">
            <div><label class="text-xs font-bold uppercase tracking-wider text-gray-500">Pencarian</label><input name="search" value="{{ $search }}" class="mt-2 block w-full rounded-md border-gray-300" placeholder="ID atau nama promo"></div>
            <div><label class="text-xs font-bold uppercase tracking-wider text-gray-500">Status</label><select name="status" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="inactive" @selected($status === 'inactive')>Nonaktif</option><option value="upcoming" @selected($status === 'upcoming')>Akan datang</option><option value="ended" @selected($status === 'ended')>Berakhir</option></select></div>
            <div class="flex items-end gap-2"><button class="rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.promotions.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">Reset</a></div>
        </form>

        <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-xl"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500"><tr><th class="px-6 py-3">ID Promo</th><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Periode</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Target</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100">
                @forelse($promotions as $promotion)
                    <tr class="align-top"><td class="px-6 py-4 font-semibold text-gray-950">{{ $promotion->promo_id }}</td><td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $promotion->name }}</div>@if($promotion->description)<div class="text-xs text-gray-500">{{ $promotion->description }}</div>@endif</td><td class="px-6 py-4 text-gray-600">{{ $promotion->start_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }} — {{ $promotion->end_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }}</td><td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match($promotion->status()) { 'active' => 'bg-emerald-100 text-emerald-800', 'upcoming' => 'bg-blue-100 text-blue-800', 'ended' => 'bg-gray-100 text-gray-600', 'inactive' => 'bg-gray-100 text-gray-600' } }}">{{ $promotion->statusLabel() }}</span></td><td class="px-6 py-4 text-gray-600"><ul class="grid gap-1">@if($promotion->is_global)<li>Lintas cabang</li>@else @foreach($promotion->targets as $target)<li>{{ $target->targetLabel() }}</li>@endforeach @endif</ul></td><td class="px-6 py-4 text-right"><div class="grid justify-items-end gap-2">@can('promotions.update')<a href="{{ route('admin.promotions.edit', $promotion) }}" class="font-semibold text-amber-700 hover:text-amber-900">Edit</a><form method="POST" action="{{ route('admin.promotions.toggle-active', $promotion) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-gray-600 hover:text-gray-900">{{ $promotion->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>@endcan</div></td></tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Belum ada promo.</td></tr>
                @endforelse
            </tbody></table>
        </div></div><div class="mt-6">{{ $promotions->links() }}</div>
    </div></div>
</x-app-layout>
