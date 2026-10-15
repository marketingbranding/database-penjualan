<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proyek" description="Kelola proyek, blok, dan jumlah kavling dalam setiap cabang.">
            <x-slot name="actions">@can('projects.create')<a href="{{ route('admin.projects.create') }}" class="module-button module-button-primary">＋ Tambah proyek</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    @php
        $projectBranchOptions = ['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all();
        $hasActiveFilters = filled($branchId);
    @endphp

    <div class="module-page">
        <x-table-card title="Daftar proyek" :count="number_format($projects->total(), 0, ',', '.').' proyek'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('admin.projects.index') }}" :result-count="$projects->total()" result-label="proyek" :has-active-filters="$hasActiveFilters" reset-href="{{ route('admin.projects.index') }}">
                    <x-slot name="filters"><x-filter-pill label="Cabang" name="branch_id" :options="$projectBranchOptions" :selected="$branchId" :active="filled($branchId)" /></x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Daftar proyek">
                <x-slot name="header"><tr><th>Proyek</th><th>Cabang</th><th>Blok</th><th>Kavling</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($projects as $project)
                        <tr>
                            <td><div class="font-semibold text-ink">{{ $project->name }}</div><div class="text-[11px] text-muted">{{ $project->code }}</div></td>
                            <td class="max-w-[180px] truncate text-muted" title="{{ $project->branch->name }}">{{ $project->branch->name }}</td>
                            <td class="whitespace-nowrap text-right text-muted">{{ $project->blocks_count }}</td>
                            <td class="whitespace-nowrap text-right text-muted">{{ $project->lots_count }}</td>
                            <td><x-status-badge :status="$project->is_active ? 'Aktif' : 'Nonaktif'" :tone="$project->is_active ? 'success' : 'neutral'" /></td>
                            <td><x-row-actions><x-slot name="primary">@can('projects.update')<a href="{{ route('admin.projects.edit', $project) }}" class="module-row-primary">Edit</a>@endcan</x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada proyek sesuai filter" :reset-href="$hasActiveFilters ? route('admin.projects.index') : null" action-label="Tambah proyek" action-href="{{ ! $hasActiveFilters && Auth::user()->can('projects.create') ? route('admin.projects.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($projects as $project)
                        <x-mobile.data-card :title="$project->name" :subtitle="$project->code.' · '.$project->branch->name" :chip="$project->is_active ? 'Aktif' : 'Nonaktif'">
                            <x-slot name="metrics"><div class="mobile-data-card-metric"><small>Blok</small><strong>{{ number_format($project->blocks_count, 0, ',', '.') }}</strong></div><div class="mobile-data-card-metric"><small>Kavling</small><strong>{{ number_format($project->lots_count, 0, ',', '.') }}</strong></div><div class="mobile-data-card-metric"><small>Cabang</small><strong>{{ $project->branch->name }}</strong></div></x-slot>
                            <x-slot name="actions">@can('projects.update')<a href="{{ route('admin.projects.edit', $project) }}">Edit proyek</a>@endcan</x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada proyek sesuai filter" :reset-href="$hasActiveFilters ? route('admin.projects.index') : null" action-label="Tambah proyek" action-href="{{ ! $hasActiveFilters && Auth::user()->can('projects.create') ? route('admin.projects.create') : null }}" />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$projects" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
