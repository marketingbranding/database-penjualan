<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-medium text-orange">Pengaturan aset</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Kavling</h1>
            <p class="mt-1 text-sm text-muted">Pantau status kavling dan riwayat perubahan tanpa meninggalkan daftar inventaris.</p>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-ui.toast message="Status kavling berhasil diperbarui." />
        @endif

        <x-ui.card title="Filter kavling" description="Gunakan urutan cabang, proyek, blok, dan status untuk menyaring inventaris." padding="p-5">
            <form method="GET" action="{{ route('admin.lots.index') }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div><label for="branch_id" class="text-sm font-medium text-ink">Cabang</label><select id="branch_id" name="branch_id" onchange="this.form.submit()" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua cabang</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div><label for="project_id" class="text-sm font-medium text-ink">Proyek</label><select id="project_id" name="project_id" onchange="this.form.submit()" class="workspace-control mt-2 block w-full py-2.5 text-sm" @disabled(!$branchId)><option value="">Semua proyek</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
                <div><label for="block_id" class="text-sm font-medium text-ink">Blok</label><select id="block_id" name="block_id" onchange="this.form.submit()" class="workspace-control mt-2 block w-full py-2.5 text-sm" @disabled(!$projectId)><option value="">Semua blok</option>@foreach ($blocks as $block)<option value="{{ $block->id }}" @selected($blockId === $block->id)>Blok {{ $block->code }}</option>@endforeach</select></div>
                <div><label for="status" class="text-sm font-medium text-ink">Status</label><select id="status" name="status" onchange="this.form.submit()" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua status</option>@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>@endforeach</select></div>
                <div class="flex items-end"><a href="{{ route('admin.lots.index') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-canvas">Reset filter</a></div>
            </form>
        </x-ui.card>

        <x-ui.card title="Daftar kavling" description="{{ number_format($lots->total()) }} kavling sesuai filter." padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white">
                        <tr><th class="px-6 py-4">Kode</th><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Cabang</th><th class="px-6 py-4">Blok</th><th class="px-6 py-4">Status</th><th class="px-6 py-4"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($lots as $lot)
                            @php
                                $statusTone = match ($lot->status) {
                                    \App\LotStatus::Available => 'success',
                                    \App\LotStatus::Reserved => 'warning',
                                    \App\LotStatus::Sold => 'lime',
                                    \App\LotStatus::Inactive => 'neutral',
                                };
                            @endphp
                            <tr class="transition hover:bg-lime-soft/30">
                                <td class="px-6 py-4 font-semibold text-ink">{{ $lot->code }}</td>
                                <td class="px-6 py-4 text-muted">{{ $lot->project->name }}</td>
                                <td class="px-6 py-4 text-muted">{{ $lot->project->branch->name }}</td>
                                <td class="px-6 py-4 text-muted">Blok {{ $lot->block->code }}</td>
                                <td class="px-6 py-4">@can('lots.update')<form method="POST" action="{{ route('admin.lots.update', $lot) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="workspace-control py-1.5 text-sm">@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($lot->status === $option)>{{ $option->label() }}</option>@endforeach</select></form>@else<x-ui.pill :tone="$statusTone">{{ $lot->status->label() }}</x-ui.pill>@endcan</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('admin.lots.history', $lot) }}" class="font-semibold text-lime-dark hover:underline">Riwayat</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-muted">Belum ada kavling untuk filter yang dipilih.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($lots->hasPages())<div class="border-t border-line px-6 py-4">{{ $lots->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
