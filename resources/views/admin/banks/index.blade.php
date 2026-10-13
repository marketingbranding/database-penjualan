<x-app-layout>
    <x-slot name="header"><x-page-header title="Bank & KC/Unit" description="Kelola bank, kantor cabang, dan unit yang digunakan dalam proses penjualan." /></x-slot>

    <div class="module-page">
        @if (session('status'))<x-ui.toast message="Data bank berhasil diperbarui." />@endif
        @if ($errors->any())<x-ui.toast variant="error" :message="$errors->first()" />@endif

        @can('banks.create')
            <x-form-section title="Tambah bank" class="module-form-section-compact" id="tambah-bank">
                <form method="POST" action="{{ route('admin.banks.store') }}" class="module-inline-form">@csrf<x-text-input name="name" class="module-form-control" placeholder="Nama bank" required/><x-primary-button>Simpan bank</x-primary-button></form>
            </x-form-section>
        @endcan

        @forelse ($banks as $bank)
            <x-table-card :title="$bank->name" :count="$bank->units->count().' unit'" class="module-bank-card">
                <x-slot name="toolbar">
                    <div class="module-bank-header-form">
                        <form method="POST" action="{{ route('admin.banks.update', $bank) }}" class="module-inline-form">@csrf @method('PATCH')<input name="name" value="{{ $bank->name }}" class="module-form-control font-semibold" required>@can('banks.update')<label class="module-inline-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($bank->is_active)>Aktif</label><button class="module-menu-link">Simpan</button>@else<x-status-badge :status="$bank->is_active ? 'Aktif' : 'Nonaktif'" :tone="$bank->is_active ? 'success' : 'neutral'" />@endcan</form>
                        @can('banks.delete')<form method="POST" action="{{ route('admin.banks.destroy', $bank) }}" onsubmit="return confirm('Hapus bank ini?')">@csrf @method('DELETE')<button type="submit" class="module-menu-link is-danger">Hapus</button></form>@endcan
                    </div>
                </x-slot>
                <x-data-table caption="Unit {{ $bank->name }}" compact>
                    <x-slot name="header"><tr><th>KC/Unit</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                    <x-slot name="body">
                        @forelse ($bank->units as $unit)
                            <tr>
                                <td><form id="unit-{{ $unit->id }}" method="POST" action="{{ route('admin.bank-units.update', $unit) }}">@csrf @method('PATCH')</form><input form="unit-{{ $unit->id }}" name="name" value="{{ $unit->name }}" class="module-form-control text-xs" required></td>
                                <td>@can('banks.update')<label class="module-inline-check"><input form="unit-{{ $unit->id }}" type="hidden" name="is_active" value="0"><input form="unit-{{ $unit->id }}" type="checkbox" name="is_active" value="1" @checked($unit->is_active)>Aktif</label>@else<x-status-badge :status="$unit->is_active ? 'Aktif' : 'Nonaktif'" :tone="$unit->is_active ? 'success' : 'neutral'" />@endcan</td>
                                <td><x-row-actions><x-slot name="primary">@can('banks.update')<button form="unit-{{ $unit->id }}" class="module-row-primary">Simpan</button>@endcan</x-slot><x-slot name="menu">@can('banks.delete')<form method="POST" action="{{ route('admin.bank-units.destroy', $unit) }}" onsubmit="return confirm('Hapus KC/unit ini?')">@csrf @method('DELETE')<button type="submit" class="module-menu-link is-danger">Hapus</button></form>@endcan</x-slot></x-row-actions></td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-empty-state title="Belum ada KC/unit" /></td></tr>
                        @endforelse
                    </x-slot>
                </x-data-table>
                @can('banks.create')<form method="POST" action="{{ route('admin.banks.units.store', $bank) }}" class="module-inline-form module-bank-add-unit">@csrf<x-text-input name="name" class="module-form-control" placeholder="Nama KC/unit baru" required/><x-secondary-button>Tambah KC/unit</x-secondary-button></form>@endcan
            </x-table-card>
        @empty
            <x-empty-state title="Belum ada data bank" action-label="Tambah bank" action-href="{{ Auth::user()->can('banks.create') ? '#tambah-bank' : null }}" />
        @endforelse
    </div>
</x-app-layout>
