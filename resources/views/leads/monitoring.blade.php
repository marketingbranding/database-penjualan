<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-lime-dark">Control room</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Monitoring Lead</h1>
                <p class="mt-1 text-sm text-muted">Pantau pergerakan pipeline tanpa kehilangan konteks Sales dan tim.</p>
            </div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('leads.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Daftar Lead</a>@can('create', App\Models\Lead::class)<a href="{{ route('leads.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Lead baru</a>@endcan</div>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.card title="Filter monitoring" description="Gunakan periode dan cakupan tim untuk membaca performa." padding="p-5">
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if (Auth::user()->canAccessAllBranches())<div><label for="branch_id" class="text-sm font-medium text-ink">Cabang</label><select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@endif
                <div><label for="date_from" class="text-sm font-medium text-ink">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                <div><label for="date_to" class="text-sm font-medium text-ink">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                <div><label for="project_id" class="text-sm font-medium text-ink">Proyek</label><select id="project_id" name="project_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
                <div><label for="coordinator_id" class="text-sm font-medium text-ink">Koordinator</label><select id="coordinator_id" name="coordinator_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua koordinator</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected($filters['coordinator_id'] === $coordinator->id)>{{ $coordinator->name }}</option>@endforeach</select></div>
                <div><label for="sales_pic_id" class="text-sm font-medium text-ink">Sales</label><select id="sales_pic_id" name="sales_pic_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua sales</option>@foreach($salesUsers as $salesUser)<option value="{{ $salesUser->id }}" @selected($filters['sales_pic_id'] === $salesUser->id)>{{ $salesUser->name }}</option>@endforeach</select></div>
                <div><label for="sumber_lead" class="text-sm font-medium text-ink">Sumber Lead</label><select id="sumber_lead" name="sumber_lead" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua sumber</option>@foreach($sources as $source)<option value="{{ $source }}" @selected($filters['sumber_lead'] === $source)>{{ $source }}</option>@endforeach</select></div>
                <div><label for="kanal_masuk" class="text-sm font-medium text-ink">Kanal</label><select id="kanal_masuk" name="kanal_masuk" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua kanal</option>@foreach($channels as $channel)<option value="{{ $channel }}" @selected($filters['kanal_masuk'] === $channel)>{{ $channel }}</option>@endforeach</select></div>
                <div><label for="status_lead" class="text-sm font-medium text-ink">Status Lead</label><select id="status_lead" name="status_lead" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status_lead'] === $status)>{{ $status }}</option>@endforeach</select></div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4"><a href="{{ route('leads.monitoring') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Reset</a><button class="inline-flex min-h-10 items-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark">Terapkan filter</button></div>
            </form>
        </x-ui.card>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['Total Lead', 'total', 'bg-ink text-white', 'text-lime'], ['No Respon', 'new', 'bg-surface text-ink', 'text-muted'], ['Diskusi', 'discussion', 'bg-orange/15 text-ink', 'text-orange'], ['Cek Lokasi', 'siteVisit', 'bg-lime-soft text-ink', 'text-lime-dark'], ['UTJ', 'utj', 'bg-yellow/30 text-ink', 'text-ink']] as [$label, $key, $card, $labelColor])
                <article class="data-stripes rounded-3xl border border-line p-5 {{ $card }}"><p class="text-sm font-medium {{ $labelColor }}">{{ $label }}</p><p class="mt-3 text-3xl font-semibold">{{ number_format($summary[$key]) }}</p></article>
            @endforeach
        </section>

        <x-ui.card title="Rincian Lead" description="Data terfilter berdasarkan periode dan cakupan yang dipilih." padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Tanggal Lead</th><th class="px-6 py-4">Konsumen</th><th class="px-6 py-4">Sales</th><th class="px-6 py-4">Koordinator</th><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Sumber</th><th class="px-6 py-4">Kanal</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Aktivitas terakhir</th><th class="px-6 py-4"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse($leads as $lead)
                            <tr class="transition hover:bg-lime-soft/30"><td class="whitespace-nowrap px-6 py-4"><span class="font-medium text-ink">{{ $lead->tanggal_lead->format('d/m/Y') }}</span><div class="text-xs text-muted">{{ $lead->id_lead }}</div></td><td class="px-6 py-4"><div class="font-semibold text-ink">{{ $lead->nama_konsumen }}</div><div class="text-xs text-muted">{{ $lead->no_hp }}</div></td><td class="px-6 py-4 text-muted">{{ $lead->salesPic->name }}</td><td class="px-6 py-4 text-muted">{{ $lead->team?->coordinator?->name ?? '-' }}</td><td class="px-6 py-4 text-muted">{{ $lead->project?->name ?? '-' }}</td><td class="px-6 py-4 text-muted">{{ $lead->sumber_lead }}</td><td class="px-6 py-4 text-muted">{{ $lead->kanal_masuk }} / {{ $lead->aktivitas_lead }}</td><td class="px-6 py-4"><x-ui.stage-chip :stage="$lead->status_lead" size="sm" /></td><td class="px-6 py-4 text-xs text-muted">@if($lead->latestActivity)<div class="font-semibold text-ink">{{ $lead->latestActivity->activity_type }}</div><div>{{ $lead->latestActivity->happened_at->format('d/m/Y H:i') }}</div>@else-@endif</td><td class="px-6 py-4 text-right"><a href="{{ route('leads.show', $lead) }}" class="font-semibold text-lime-dark hover:underline">Detail</a></td></tr>
                        @empty
                            <tr><td colspan="10" class="px-6 py-14 text-center text-sm text-muted">Tidak ada Lead pada periode atau filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($leads->hasPages())<div class="border-t border-line px-6 py-4">{{ $leads->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
