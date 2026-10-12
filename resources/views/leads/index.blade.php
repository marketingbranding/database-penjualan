<x-app-layout>
    @php($qualificationLabels = [
        'not_qualified' => 'Belum memenuhi syarat',
        'qualified' => 'Memenuhi syarat',
        'not_fit' => 'Tidak sesuai',
        'deferred' => 'Ditunda',
    ])

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-lime-dark">Buku saku sales</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Leads</h1>
                <p class="workspace-page-description mt-1 text-sm text-muted">Kelola Lead akuisisi dengan filter dan tabel operasional.</p>
            </div>
            @can('create', App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Lead baru</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-semibold text-ink">Daftar Lead</p>
            <p class="mt-1 text-sm text-muted">{{ number_format($leads->total()) }} Lead sesuai filter.</p>
        </div>

        <x-ui.card title="Filter Lead" description="Gunakan filter ringkas untuk memfokuskan daftar." padding="p-5">
            <form method="GET" action="{{ route('leads.index') }}" class="space-y-4">
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <div>
                        <label for="sales_pic_id" class="text-sm font-medium text-ink">Sales</label>
                        <select id="sales_pic_id" name="sales_pic_id" class="workspace-control mt-2 block w-full py-2.5 text-sm">
                            <option value="">Semua sales</option>
                            @foreach ($salesUsers as $salesUser)
                                <option value="{{ $salesUser->id }}" @selected($salesPicId === $salesUser->id)>{{ $salesUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="branch_id" class="text-sm font-medium text-ink">Cabang</label>
                        <select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm" @disabled(! Auth::user()->canAccessAllBranches())>
                            <option value="">Semua cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex min-h-11 items-center gap-3 self-end rounded-2xl border border-line px-4 py-2.5 text-sm font-medium text-ink">
                        <input type="checkbox" name="active" value="1" @checked($active) class="size-4 rounded border-line text-lime-dark focus:ring-lime">
                        Lead aktif saja
                    </label>
                </div>

                <details class="group rounded-2xl border border-line bg-canvas/60 p-4">
                    <summary class="cursor-pointer list-none text-sm font-semibold text-ink">Filter lanjutan <span class="ml-1 text-muted transition group-open:rotate-180">⌄</span></summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div><label for="status_lead" class="text-xs font-medium text-muted">Tahap</label><select id="status_lead" name="status_lead" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua tahap</option>@foreach($statuses as $item)<option value="{{ $item }}" @selected($status === $item)>{{ $item }}</option>@endforeach</select></div>
                        <div><label for="sumber_lead" class="text-xs font-medium text-muted">Sumber</label><select id="sumber_lead" name="sumber_lead" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua sumber</option>@foreach($sources as $item)<option value="{{ $item }}" @selected($source === $item)>{{ $item }}</option>@endforeach</select></div>
                        <div><label for="kanal_masuk" class="text-xs font-medium text-muted">Kanal</label><select id="kanal_masuk" name="kanal_masuk" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua kanal</option>@foreach($channels as $item)<option value="{{ $item }}" @selected($channel === $item)>{{ $item }}</option>@endforeach</select></div>
                        <div><label for="project_id" class="text-xs font-medium text-muted">Proyek</label><select id="project_id" name="project_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((int) request('project_id') === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
                        <div><label for="date_from" class="text-xs font-medium text-muted">Dari tanggal</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                        <div><label for="date_to" class="text-xs font-medium text-muted">Sampai tanggal</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                        <div><label for="follow_up" class="text-xs font-medium text-muted">Follow-up</label><select id="follow_up" name="follow_up" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua jadwal</option><option value="today" @selected($followUp === 'today')>Hari ini</option><option value="overdue" @selected($followUp === 'overdue')>Terlambat</option><option value="upcoming" @selected($followUp === 'upcoming')>Akan datang</option></select></div>
                        <div><label for="search" class="text-xs font-medium text-muted">Cari</label><input id="search" name="search" value="{{ request('search') }}" placeholder="ID, nama, no. HP" class="workspace-control mt-2 block w-full py-2.5 text-sm"></div>
                    </div>
                </details>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('leads.index') }}" class="inline-flex min-h-10 w-full items-center justify-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas sm:w-auto">Reset</a>
                    <button class="inline-flex min-h-10 w-full items-center justify-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark sm:w-auto">Terapkan filter</button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Tabel Lead" description="Gunakan Detail untuk memperbarui aktivitas, kualifikasi, dan follow-up." padding="p-0">
            <p class="px-6 pb-4 text-xs leading-5 text-muted sm:hidden">Geser tabel ke samping untuk melihat kolom lainnya.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Tanggal</th><th class="px-6 py-4">Konsumen</th><th class="px-6 py-4">Sales</th><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Sumber</th><th class="px-6 py-4">Tahap dan kualifikasi</th><th class="px-6 py-4">Follow-up</th><th class="px-6 py-4"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($leads as $lead)
                            <tr class="transition hover:bg-lime-soft/30"><td class="whitespace-nowrap px-6 py-4 font-medium text-ink">{{ $lead->tanggal_lead->translatedFormat('d M Y') }}<div class="text-xs font-normal text-muted">{{ $lead->id_lead }}</div></td><td class="px-6 py-4"><div class="font-semibold text-ink">{{ $lead->nama_konsumen }}</div><div class="text-xs text-muted">{{ $lead->no_hp }}</div></td><td class="px-6 py-4 text-muted">{{ $lead->salesPic->name }}</td><td class="px-6 py-4 text-muted">{{ $lead->project?->name ?? '-' }}</td><td class="px-6 py-4 text-muted">{{ $lead->sumber_lead }}</td><td class="px-6 py-4"><x-ui.stage-chip :stage="$lead->status_lead" size="sm" /><div class="mt-2 text-xs text-muted">{{ $qualificationLabels[$lead->qualification_status] ?? $lead->qualification_status }}</div></td><td class="px-6 py-4 text-muted">{{ $lead->next_follow_up_at?->format('d/m/Y H:i') ?? 'Belum dijadwalkan' }}</td><td class="px-6 py-4 text-right"><a href="{{ route('leads.show', $lead) }}" class="font-semibold text-lime-dark hover:underline">Detail</a></td></tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-14 text-center text-sm text-muted">Belum ada Lead sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($leads->hasPages())<div class="border-t border-line px-6 py-4">{{ $leads->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
