<x-app-layout>
    <x-slot name="header"><x-page-header title="Kavling" description="Pantau status kavling dan riwayat perubahan tanpa meninggalkan daftar inventaris." /></x-slot>

    @php
        $lotBranchOptions = ['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all();
        $lotProjectOptions = ['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all();
        $lotBlockOptions = ['' => 'Semua blok'] + $blocks->mapWithKeys(fn ($block): array => [$block->id => 'Blok '.$block->code])->all();
        $lotStatusOptions = ['' => 'Semua status'] + collect($statuses)->mapWithKeys(fn ($lotStatus): array => [$lotStatus->value => $lotStatus->label()])->all();
        $hasActiveFilters = filled($branchId) || filled($projectId) || filled($blockId) || filled($status);
    @endphp

    <div class="module-page">
        @if (session('status'))<x-ui.toast message="Status kavling berhasil diperbarui." />@endif
        <x-table-card title="Daftar kavling" :count="number_format($lots->total(), 0, ',', '.').' kavling'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('admin.lots.index') }}" :result-count="$lots->total()" result-label="kavling" :has-active-filters="$hasActiveFilters" reset-href="{{ route('admin.lots.index') }}">
                    <x-slot name="filters">
                        <x-filter-pill label="Cabang" name="branch_id" :options="$lotBranchOptions" :selected="$branchId" :active="filled($branchId)" />
                        <x-filter-pill label="Proyek" name="project_id" :options="$lotProjectOptions" :selected="$projectId" :active="filled($projectId)" />
                        <x-filter-pill label="Blok" name="block_id" :options="$lotBlockOptions" :selected="$blockId" :active="filled($blockId)" />
                        <x-filter-pill label="Status" name="status" :options="$lotStatusOptions" :selected="$status?->value" :active="filled($status)" />
                    </x-slot>
                </x-toolbar>
            </x-slot>
            <x-data-table caption="Daftar kavling">
                <x-slot name="header"><tr><th>Kode</th><th>Proyek</th><th>Cabang</th><th>Blok</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($lots as $lot)
                        @php($statusTone = match ($lot->status) { \App\LotStatus::Available => 'success', \App\LotStatus::Reserved => 'warning', \App\LotStatus::Sold => 'lime', \App\LotStatus::Inactive => 'neutral' })
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-ink">{{ $lot->code }}</td>
                            <td class="max-w-[180px] truncate text-muted" title="{{ $lot->project->name }}">{{ $lot->project->name }}</td>
                            <td class="max-w-[160px] truncate text-muted" title="{{ $lot->project->branch->name }}">{{ $lot->project->branch->name }}</td>
                            <td class="whitespace-nowrap text-muted">Blok {{ $lot->block->code }}</td>
                            <td>
                                @can('lots.update')
                                    <form method="POST" action="{{ route('admin.lots.update', $lot) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="workspace-control py-1.5 text-xs">@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($lot->status === $option)>{{ $option->label() }}</option>@endforeach</select></form>
                                @else
                                    <x-status-badge :status="$lot->status->label()" :tone="$statusTone" />
                                @endcan
                            </td>
                            <td><x-row-actions><x-slot name="primary"><a href="{{ route('admin.lots.history', $lot) }}" class="module-row-primary">Riwayat</a></x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada kavling untuk filter yang dipilih" :reset-href="$hasActiveFilters ? route('admin.lots.index') : null" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($lots as $lot)
                        <x-mobile.data-card :title="$lot->code" :subtitle="$lot->project->name.' · Blok '.$lot->block->code" :chip="$lot->status->label()">
                            <x-slot name="details"><div class="mobile-data-card-detail"><small>Cabang</small><strong>{{ $lot->project->branch->name }}</strong></div><div class="mobile-data-card-detail"><small>Status</small><strong>{{ $lot->status->label() }}</strong></div></x-slot>
                            <x-slot name="actions">
                                <a href="{{ route('admin.lots.history', $lot) }}">Riwayat</a>
                                @can('lots.update')<form method="POST" action="{{ route('admin.lots.update', $lot) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="module-form-control">@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($lot->status === $option)>{{ $option->label() }}</option>@endforeach</select></form>@endcan
                            </x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada kavling untuk filter yang dipilih" :reset-href="$hasActiveFilters ? route('admin.lots.index') : null" />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$lots" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
