<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-medium text-lime-dark">Performa sales</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Laporan performa sales</h1><p class="mt-1 text-sm text-muted">Rekap aktivitas Sales berdasarkan periode, Lead, dan Agenda.</p></div>
            @can('sales-fee-reports.export')<a href="{{ route('sales-fee-reports.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Export Excel</a>@endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.card title="Filter laporan" description="Pilih periode dan cakupan untuk membaca performa." padding="p-5">
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div><label for="date_from" class="text-sm font-medium text-ink">Tanggal mulai</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                <div><label for="date_to" class="text-sm font-medium text-ink">Tanggal selesai</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                @if(Auth::user()->canAccessAllBranches())<div><label for="branch_id" class="text-sm font-medium text-ink">Cabang</label><select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@endif
                <div><label for="project_id" class="text-sm font-medium text-ink">Proyek</label><select id="project_id" name="project_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
                <div><label for="coordinator_id" class="text-sm font-medium text-ink">Koordinator</label><select id="coordinator_id" name="coordinator_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua koordinator</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected($filters['coordinator_id'] === $coordinator->id)>{{ $coordinator->name }}</option>@endforeach</select></div>
                <div><label for="sales_pic_id" class="text-sm font-medium text-ink">Sales</label><select id="sales_pic_id" name="sales_pic_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua sales</option>@foreach($salesUsers as $salesUser)<option value="{{ $salesUser->id }}" @selected($filters['sales_pic_id'] === $salesUser->id)>{{ $salesUser->name }}</option>@endforeach</select></div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5"><a href="{{ route('sales-fee-reports.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Reset</a><button class="inline-flex min-h-10 items-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark">Terapkan filter</button></div>
            </form>
        </x-ui.card>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach(['Total Agenda'=>'total_agenda','Agenda Selesai'=>'agenda_selesai','Total Lead'=>'total_lead','Tatap Muka'=>'tatap_muka','Cek Lokasi'=>'cek_lokasi','UTJ'=>'utj'] as $label => $key)
                <article class="data-stripes rounded-3xl border border-line bg-surface p-5"><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-3 text-3xl font-semibold tracking-tight text-ink">{{ number_format($report['totals'][$key]) }}</p></article>
            @endforeach
        </section>

        <x-ui.card title="Rekap per Sales" description="Buka detail untuk melihat Lead dan Agenda pada baris terkait." padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Sales</th><th class="px-6 py-4">Koordinator</th><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Total agenda</th><th class="px-6 py-4">Agenda selesai</th><th class="px-6 py-4">Total Lead</th><th class="px-6 py-4">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse($report['rows'] as $row)
                            <tr class="transition hover:bg-lime-soft/30"><td class="px-6 py-4 font-semibold text-ink">{{ $row['sales_name'] }}</td><td class="px-6 py-4 text-muted">{{ $row['coordinator_name'] }}</td><td class="px-6 py-4 text-muted">{{ $row['project_name'] }}</td><td class="px-6 py-4 font-semibold text-ink">{{ $row['total_agenda'] }}</td><td class="px-6 py-4 font-semibold text-lime-dark">{{ $row['agenda_selesai'] }}</td><td class="px-6 py-4 font-semibold text-orange">{{ $row['total_lead'] }}</td><td class="whitespace-nowrap px-6 py-4"><a href="{{ route('sales-fee-reports.show', array_filter(['salesUser' => $row['sales_id'], 'project' => $row['project_id']])) }}" class="font-semibold text-lime-dark hover:underline">Detail</a>@can('sales-fee-reports.print')<a href="{{ route('sales-fee-reports.print', array_filter([...$filters, 'sales_pic_id' => $row['sales_id'], 'project_id' => $row['project_id']])) }}" target="_blank" class="ml-3 font-semibold text-orange hover:underline">Cetak</a>@endcan</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-14 text-center text-sm text-muted">Belum ada data pada periode atau filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
        <p class="text-xs text-muted">Laporan ini saat ini menghitung performa aktivitas. Nominal fee belum dihitung.</p>
    </div>
</x-app-layout>
