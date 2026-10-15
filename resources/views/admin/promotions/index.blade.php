<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Promo" description="Kelola program promo, periode, target, dan status publikasinya.">
            <x-slot name="actions">
                @can('promotions.create')
                    <a href="{{ route('admin.promotions.create') }}" class="module-button module-button-primary">＋ Tambah promo</a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $statusOptions = ['' => 'Semua status', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'upcoming' => 'Akan datang', 'ended' => 'Berakhir'];
        $hasActiveFilters = filled($search) || filled($status);
    @endphp

    <div class="module-page">
        @if (session('status') === 'promotion-created')<x-ui.toast message="Promo berhasil dibuat." />@endif
        @if (session('status') === 'promotion-updated')<x-ui.toast message="Promo berhasil diperbarui." />@endif
        @if (session('status') === 'promotion-deleted')<x-ui.toast message="Promo berhasil dihapus." />@endif
        @if (session('status') === 'promotion-toggled')<x-ui.toast message="Status promo berhasil diperbarui." />@endif

        <x-table-card title="Daftar promo" :count="number_format($promotions->total(), 0, ',', '.').' promo'" class="min-h-0">
            <x-slot name="toolbar">
                <x-toolbar action="{{ route('admin.promotions.index') }}" :result-count="$promotions->total()" result-label="promo" :has-active-filters="$hasActiveFilters" reset-href="{{ route('admin.promotions.index') }}">
                    <x-slot name="search"><x-search-input name="search" :value="$search" placeholder="Cari ID atau nama promo" /></x-slot>
                    <x-slot name="filters"><x-filter-pill label="Status" name="status" :options="$statusOptions" :selected="$status" :active="filled($status)" /></x-slot>
                </x-toolbar>
            </x-slot>

            <x-data-table caption="Daftar promo">
                <x-slot name="header"><tr><th>ID promo</th><th>Nama</th><th>Periode</th><th>Status</th><th>Target</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($promotions as $promotion)
                        @php($promotionTone = match ($promotion->status()) { 'active' => 'success', 'upcoming' => 'warning', default => 'neutral' })
                        <tr>
                            <td class="whitespace-nowrap font-semibold">{{ $promotion->promo_id }}</td>
                            <td class="max-w-[230px]" title="{{ $promotion->name }}"><div class="truncate font-semibold">{{ $promotion->name }}</div>@if($promotion->description)<div class="line-clamp-2 text-[11px] text-muted">{{ $promotion->description }}</div>@endif</td>
                            <td class="whitespace-nowrap text-muted">{{ $promotion->start_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }} – {{ $promotion->end_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }}</td>
                            <td><x-status-badge :status="$promotion->statusLabel()" :tone="$promotionTone" /></td>
                            <td class="max-w-[190px]" title="{{ $promotion->is_global ? 'Lintas cabang' : $promotion->targets->map(fn ($target) => $target->targetLabel())->join(', ') }}"><div class="line-clamp-2 text-muted">@if($promotion->is_global)Lintas cabang@else{{ $promotion->targets->map(fn ($target) => $target->targetLabel())->join(', ') ?: '-' }}@endif</div></td>
                            <td><x-row-actions><x-slot name="primary">@can('promotions.update')<a href="{{ route('admin.promotions.edit', $promotion) }}" class="module-row-primary">Edit</a>@endcan</x-slot><x-slot name="menu">@can('promotions.update')<form method="POST" action="{{ route('admin.promotions.toggle-active', $promotion) }}">@csrf @method('PATCH')<button type="submit" class="module-menu-link">{{ $promotion->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>@endcan</x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada promo sesuai filter" :reset-href="$hasActiveFilters ? route('admin.promotions.index') : null" action-label="Tambah promo" action-href="{{ ! $hasActiveFilters && Auth::user()->can('promotions.create') ? route('admin.promotions.create') : null }}" /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($promotions as $promotion)
                        @php($promotionTone = match ($promotion->status()) { 'active' => 'success', 'upcoming' => 'warning', default => 'neutral' })
                        <x-mobile.data-card :title="$promotion->name" :subtitle="$promotion->promo_id" :chip="$promotion->statusLabel()">
                            <x-slot name="details"><div class="mobile-data-card-detail"><small>Periode</small><strong>{{ $promotion->start_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }} – {{ $promotion->end_date?->translatedFormat('d M Y') ?? 'Tanpa batas' }}</strong></div><div class="mobile-data-card-detail"><small>Target</small><strong>{{ $promotion->is_global ? 'Lintas cabang' : ($promotion->targets->map(fn ($target) => $target->targetLabel())->join(', ') ?: '-') }}</strong></div></x-slot>
                            <x-slot name="actions">@can('promotions.update')<a href="{{ route('admin.promotions.edit', $promotion) }}">Edit promo</a><form method="POST" action="{{ route('admin.promotions.toggle-active', $promotion) }}">@csrf @method('PATCH')<button type="submit">{{ $promotion->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>@endcan</x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada promo sesuai filter" :reset-href="$hasActiveFilters ? route('admin.promotions.index') : null" action-label="Tambah promo" action-href="{{ ! $hasActiveFilters && Auth::user()->can('promotions.create') ? route('admin.promotions.create') : null }}" />
                    @endforelse
                </x-slot>
            </x-data-table>

            <x-slot name="footer"><x-pagination :paginator="$promotions" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
