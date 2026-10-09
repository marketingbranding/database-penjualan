<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-bold uppercase tracking-widest text-marison-teal-600">Performance desk</p><h1 class="mt-1 text-xl font-bold">Laporan Performa Sales</h1><p class="mt-1 text-sm text-gray-500">Rekap aktivitas Sales berdasarkan periode, Lead, dan Agenda.</p></div>
            @can('sales-fee-reports.export')<a href="{{ route('sales-fee-reports.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="rounded-lg bg-marison-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-marison-teal-700">Export Excel</a>@endcan
        </div>
    </x-slot>

    <div class="py-8"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        <form method="GET" class="grid gap-4 rounded-2xl border border-marison-line bg-marison-paper p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <div><label for="date_from" class="text-xs font-bold uppercase tracking-wider text-gray-500">Tanggal Mulai</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"></div>
            <div><label for="date_to" class="text-xs font-bold uppercase tracking-wider text-gray-500">Tanggal Selesai</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"></div>
            @if(Auth::user()->canAccessAllBranches())<div><label for="branch_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Cabang</label><select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@endif
            <div><label for="project_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Proyek</label><select id="project_id" name="project_id" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"><option value="">Semua proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
            <div><label for="coordinator_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Koordinator</label><select id="coordinator_id" name="coordinator_id" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"><option value="">Semua koordinator</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected($filters['coordinator_id'] === $coordinator->id)>{{ $coordinator->name }}</option>@endforeach</select></div>
            <div><label for="sales_pic_id" class="text-xs font-bold uppercase tracking-wider text-gray-500">Sales</label><select id="sales_pic_id" name="sales_pic_id" class="mt-2 block w-full rounded-lg border-marison-line bg-marison-cream-50"><option value="">Semua Sales</option>@foreach($salesUsers as $salesUser)<option value="{{ $salesUser->id }}" @selected($filters['sales_pic_id'] === $salesUser->id)>{{ $salesUser->name }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5"><button class="rounded-lg bg-marison-forest-950 px-4 py-2 text-sm font-semibold text-white hover:bg-marison-forest-900">Terapkan Filter</button><a href="{{ route('sales-fee-reports.index') }}" class="rounded-lg border border-marison-line px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-marison-cream-50">Reset</a></div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach(['Total Agenda'=>'total_agenda','Agenda Selesai'=>'agenda_selesai','Total Lead'=>'total_lead','Tatap Muka'=>'tatap_muka','Cek Lokasi'=>'cek_lokasi','UTJ'=>'utj'] as $label => $key)
                <div class="rounded-2xl border border-marison-line bg-marison-paper p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-marison-ink-950">{{ $report['totals'][$key] }}</p></div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl border border-marison-line bg-marison-paper shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-marison-line text-sm"><thead class="bg-marison-cream-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500"><tr><th class="px-6 py-3">Sales</th><th class="px-6 py-3">Koordinator</th><th class="px-6 py-3">Proyek</th><th class="px-6 py-3">Total Agenda</th><th class="px-6 py-3">Agenda Selesai</th><th class="px-6 py-3">Total Lead</th><th class="px-6 py-3">Aksi</th></tr></thead><tbody class="divide-y divide-marison-line">
            @forelse($report['rows'] as $row)
                <tr class="transition hover:bg-marison-cream-50"><td class="px-6 py-4 font-semibold text-marison-ink-950">{{ $row['sales_name'] }}</td><td class="px-6 py-4">{{ $row['coordinator_name'] }}</td><td class="px-6 py-4">{{ $row['project_name'] }}</td><td class="px-6 py-4 font-semibold">{{ $row['total_agenda'] }}</td><td class="px-6 py-4 font-semibold text-emerald-700">{{ $row['agenda_selesai'] }}</td><td class="px-6 py-4 font-semibold text-marison-teal-700">{{ $row['total_lead'] }}</td><td class="whitespace-nowrap px-6 py-4"><a href="{{ route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']])) }}" class="font-semibold text-marison-teal-700 hover:text-marison-teal-900">Detail</a>@can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter([...$filters, 'sales_pic_id' => $row['sales_id'], 'project_id' => $row['project_id']])) }}" target="_blank" class="ml-3 font-semibold text-marison-terracotta-600 hover:text-marison-terracotta-800">Cetak</a>@endcan</td></tr>
            @empty
                <tr><td colspan="7" class="px-6 py-14 text-center text-sm text-gray-500">Belum ada data pada periode/filter ini.</td></tr>
            @endforelse
        </tbody></table></div></div>
        <p class="text-xs text-gray-500">Laporan ini saat ini menghitung performa aktivitas. Nominal fee belum dihitung.</p>
    </div></div>
</x-app-layout>
