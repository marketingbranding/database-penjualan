<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-orange">Pengaturan organisasi</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Cabang</h1>
                <p class="mt-1 text-sm text-muted">Kelola daftar cabang yang menjadi dasar struktur organisasi dan proyek.</p>
            </div>
            @can('branches.create')
                <a href="{{ route('admin.branches.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Tambah cabang</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.card title="Daftar cabang" description="{{ number_format($branches->total()) }} cabang terdaftar." padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white">
                        <tr><th class="px-6 py-4">Cabang</th><th class="px-6 py-4">Proyek</th><th class="px-6 py-4">Status</th><th class="px-6 py-4"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($branches as $branch)
                            <tr class="transition hover:bg-lime-soft/30">
                                <td class="px-6 py-4"><div class="font-semibold text-ink">{{ $branch->name }}</div><div class="text-xs text-muted">{{ $branch->code }}</div></td>
                                <td class="px-6 py-4 text-muted">{{ $branch->projects_count }}</td>
                                <td class="px-6 py-4"><x-ui.pill :tone="$branch->is_active ? 'success' : 'neutral'">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.pill></td>
                                <td class="px-6 py-4 text-right">@can('branches.update')<a href="{{ route('admin.branches.edit', $branch) }}" class="font-semibold text-lime-dark hover:underline">Edit</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-14 text-center text-sm text-muted">Belum ada cabang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($branches->hasPages())<div class="border-t border-line px-6 py-4">{{ $branches->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
