<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-orange">Pengaturan aset</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Proyek</h1>
                <p class="mt-1 text-sm text-muted">Kelola proyek, blok, dan jumlah kavling dalam setiap cabang.</p>
            </div>
            @can('projects.create')
                <a href="{{ route('admin.projects.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Tambah proyek</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.card title="Filter proyek" description="Pilih cabang untuk memfokuskan daftar proyek." padding="p-5">
            <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1 sm:max-w-sm">
                    <label for="branch_id" class="text-sm font-medium text-ink">Cabang</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="workspace-control mt-2 block w-full py-2.5 text-sm">
                        <option value="">Semua cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('admin.projects.index') }}" class="inline-flex min-h-11 items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-canvas">Reset filter</a>
            </form>
        </x-ui.card>

        <x-ui.card title="Daftar proyek" description="{{ number_format($projects->total()) }} proyek sesuai filter." padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white">
                        <tr><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Cabang</th><th class="px-6 py-4">Blok</th><th class="px-6 py-4">Kavling</th><th class="px-6 py-4">Status</th><th class="px-6 py-4"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($projects as $project)
                            <tr class="transition hover:bg-lime-soft/30">
                                <td class="px-6 py-4"><div class="font-semibold text-ink">{{ $project->name }}</div><div class="text-xs text-muted">{{ $project->code }}</div></td>
                                <td class="px-6 py-4 text-muted">{{ $project->branch->name }}</td>
                                <td class="px-6 py-4 text-muted">{{ $project->blocks_count }}</td>
                                <td class="px-6 py-4 text-muted">{{ $project->lots_count }}</td>
                                <td class="px-6 py-4"><x-ui.pill :tone="$project->is_active ? 'success' : 'neutral'">{{ $project->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.pill></td>
                                <td class="px-6 py-4 text-right">@can('projects.update')<a href="{{ route('admin.projects.edit', $project) }}" class="font-semibold text-lime-dark hover:underline">Edit</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-muted">Belum ada proyek.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($projects->hasPages())<div class="border-t border-line px-6 py-4">{{ $projects->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
