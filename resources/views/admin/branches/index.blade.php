<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cabang" description="Kelola daftar cabang yang menjadi dasar struktur organisasi dan proyek.">
            <x-slot name="actions">@can('branches.create')<a href="{{ route('admin.branches.create') }}" class="module-button module-button-primary">＋ Tambah cabang</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        <x-table-card title="Daftar cabang" :count="number_format($branches->total(), 0, ',', '.').' cabang'" class="min-h-0">
            <x-data-table caption="Daftar cabang">
                <x-slot name="header"><tr><th>Cabang</th><th>Proyek</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($branches as $branch)
                        <tr>
                            <td><div class="font-semibold text-ink">{{ $branch->name }}</div><div class="text-[11px] text-muted">{{ $branch->code }}</div></td>
                            <td class="whitespace-nowrap text-muted">{{ $branch->projects_count }}</td>
                            <td><x-status-badge :status="$branch->is_active ? 'Aktif' : 'Nonaktif'" :tone="$branch->is_active ? 'success' : 'neutral'" /></td>
                            <td><x-row-actions><x-slot name="primary">@can('branches.update')<a href="{{ route('admin.branches.edit', $branch) }}" class="module-row-primary">Edit</a>@endcan</x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state title="Belum ada cabang" action-label="Tambah cabang" action-href="{{ Auth::user()->can('branches.create') ? route('admin.branches.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($branches as $branch)
                        <x-mobile.data-card :title="$branch->name" :subtitle="$branch->code" :chip="$branch->is_active ? 'Aktif' : 'Nonaktif'" :value="number_format($branch->projects_count, 0, ',', '.')" value-label="Proyek">
                            <x-slot name="actions">@can('branches.update')<a href="{{ route('admin.branches.edit', $branch) }}">Edit cabang</a>@endcan</x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada cabang" action-label="Tambah cabang" action-href="{{ Auth::user()->can('branches.create') ? route('admin.branches.create') : null }}" />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$branches" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
