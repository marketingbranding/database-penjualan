<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Data konsumen" description="Kelola data konsumen dan reservasi aktif dalam satu ruang kerja.">
            <x-slot name="actions">
                @can('consumers.export')
                    <a href="{{ route('consumers.export', request()->query()) }}" class="module-button module-button-secondary">Export Excel</a>
                @endcan
                @can('create', App\Models\SalesCase::class)
                    <a href="{{ route('consumers.create') }}" class="module-button module-button-primary">＋ Tambah konsumen</a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $projectOptions = ['' => 'Semua proyek'] + $projects->mapWithKeys(fn ($project): array => [$project->id => $project->name])->all();
        $paymentOptions = ['' => 'Semua metode'] + collect($paymentMethods)->mapWithKeys(fn ($method): array => [$method->value => $method->label()])->all();
        $hasActiveFilters = filled($branchId) || filled($projectId) || filled($paymentMethod) || filled($search);
    @endphp

    <div class="module-page">
        @if (session('status'))
            <x-ui.toast message="Data konsumen berhasil diperbarui." />
        @endif

        <x-table-card title="Reservasi aktif" :count="number_format($salesCases->total()).' konsumen'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar
                    action="{{ route('consumers.index') }}"
                    :result-count="$salesCases->total()"
                    result-label="konsumen"
                    :has-active-filters="$hasActiveFilters"
                    reset-href="{{ route('consumers.index') }}"
                >
                    <x-slot name="search"><x-search-input name="search" :value="$search" placeholder="Cari nama, NIK, atau kavling" /></x-slot>
                    <x-slot name="filters">
                        @if (Auth::user()->canAccessAllBranches())
                            <x-filter-pill label="Cabang" name="branch_id" :options="['' => 'Semua cabang'] + $branches->mapWithKeys(fn ($branch): array => [$branch->id => $branch->name])->all()" :selected="$branchId" :active="filled($branchId)" />
                        @else
                            <x-filter-pill label="Cabang" :value="Auth::user()->branch?->name ?? 'Belum diatur'" />
                        @endif
                        <x-filter-pill label="Proyek" name="project_id" :options="$projectOptions" :selected="$projectId" :active="filled($projectId)" />
                        <x-filter-pill label="Pembayaran" name="payment_method" :options="$paymentOptions" :selected="$paymentMethod?->value" :active="filled($paymentMethod)" />
                    </x-slot>
                </x-toolbar>
            </x-slot>

            <x-data-table caption="Reservasi aktif">
                <x-slot name="header">
                    <tr><th>Konsumen</th><th>Kavling</th><th>Status</th><th>Tanggal lahir</th><th>Pekerjaan</th><th>Kontak</th><th>Metode</th><th>Promo</th><th class="text-right">Aksi</th></tr>
                </x-slot>
                <x-slot name="body">
                    @forelse ($salesCases as $case)
                        <tr>
                            <td><div class="flex min-w-0 items-center gap-2.5"><x-ui.avatar :name="$case->consumer->nama_konsumen" size="sm" :tone="['lime', 'orange', 'yellow'][$case->consumer->id % 3] ?? 'lime'" /><div class="min-w-0"><div class="truncate font-semibold text-ink" title="{{ $case->consumer->nama_konsumen }}">{{ $case->consumer->nama_konsumen }}</div><div class="text-[11px] text-muted">{{ $case->consumer->no_ktp }}</div></div></div></td>
                            <td><div class="max-w-[170px] truncate font-semibold text-ink" title="{{ $case->lot->project->name }}-{{ $case->lot->code }}">{{ $case->lot->project->name }}-{{ $case->lot->code }}</div><div class="truncate text-[11px] text-muted">{{ $case->lot->project->branch->name }}</div></td>
                            <td><x-status-badge :status="$case->lot->status->label()" tone="success" /></td>
                            <td class="whitespace-nowrap text-muted">{{ $case->consumer->tanggal_lahir->translatedFormat('d M Y') }}<div class="text-[11px]">{{ $case->consumer->age }} tahun</div></td>
                            <td class="max-w-[150px] truncate text-muted" title="{{ $case->consumer->pekerjaan }}">{{ $case->consumer->pekerjaan }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $case->consumer->no_hp }}</td>
                            <td><x-status-badge :status="$case->payment_method->label()" tone="warning" /></td>
                            <td class="max-w-[170px] truncate" title="{{ $case->promotion?->name ?? '-' }}">@if ($case->promotion)<strong>{{ $case->promotion->promo_id }}</strong><div class="truncate text-[11px] text-muted">{{ $case->promotion->name }}</div>@else<span class="text-muted">-</span>@endif</td>
                            <td><x-row-actions><x-slot name="primary"><a href="{{ route('consumers.show', $case) }}" class="module-row-primary">Detail</a></x-slot><x-slot name="menu">@can('update', $case)<a href="{{ route('consumers.edit', $case) }}" class="module-menu-link">Edit</a>@endcan</x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state title="Belum ada konsumen sesuai filter" :reset-href="$hasActiveFilters ? route('consumers.index') : null" action-label="Tambah konsumen" action-href="{{ ! $hasActiveFilters && Auth::user()->can('create', App\Models\SalesCase::class) ? route('consumers.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
            </x-data-table>

            <x-slot name="footer"><x-pagination :paginator="$salesCases" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
