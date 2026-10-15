<x-app-layout>
    <x-slot name="header"><x-page-header title="Target lead time" description="Atur target waktu proses dalam hari kalender untuk memantau SLA penjualan." /></x-slot>

    <div class="module-page">
        @if (session('status') === 'lead-time-updated')<x-ui.toast message="Target lead time berhasil diperbarui." />@endif
        <x-table-card title="Target proses" :count="number_format($targets->count(), 0, ',', '.').' target'" class="min-h-0">
            <x-data-table caption="Target lead time" compact>
                <x-slot name="header"><tr><th>Proses</th><th>Target proses</th><th>Target hari kalender</th><th>Status</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @foreach ($targets as $target)
                        @php($formId = 'lead-time-'.$target->id)
                        <tr>
                            <td><form id="{{ $formId }}" method="POST" action="{{ route('admin.lead-times.update', $target) }}">@csrf @method('PATCH')</form><span class="font-semibold">{{ str($target->process)->replace('_', ' ')->title() }}</span></td>
                            <td class="max-w-[260px] truncate text-muted" title="{{ $target->target_description }}">{{ $target->target_description }}</td>
                            <td><div class="module-inline-form"><input form="{{ $formId }}" type="number" name="target_days" value="{{ $target->target_days }}" min="0" max="3650" class="module-form-control w-24 text-xs" placeholder="Manual"><span class="text-[11px] text-muted">hari</span></div></td>
                            <td><label class="module-inline-check"><input form="{{ $formId }}" type="hidden" name="is_active" value="0"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($target->is_active)>Aktif</label></td>
                            <td><x-row-actions><x-slot name="primary"><button form="{{ $formId }}" class="module-row-primary">Simpan</button></x-slot></x-row-actions></td>
                        </tr>
                    @endforeach
                </x-slot>
                <x-slot name="mobile">
                    @foreach ($targets as $target)
                        @php($formId = 'mobile-lead-time-'.$target->id)
                        <x-mobile.data-card :title="str($target->process)->replace('_', ' ')->title()" :subtitle="$target->target_description" :chip="$target->is_active ? 'Aktif' : 'Nonaktif'">
                            <x-slot name="details"><div class="mobile-data-card-detail"><small>Target</small><strong>{{ $target->target_days }} hari</strong></div><div class="mobile-data-card-detail"><small>Status</small><strong>{{ $target->is_active ? 'Aktif' : 'Nonaktif' }}</strong></div></x-slot>
                            <x-slot name="actions"><form id="{{ $formId }}" method="POST" action="{{ route('admin.lead-times.update', $target) }}">@csrf @method('PATCH')<input type="number" name="target_days" value="{{ $target->target_days }}" min="0" max="3650" class="module-form-control"><input type="hidden" name="is_active" value="0"><label class="mobile-inline-check"><input type="checkbox" name="is_active" value="1" @checked($target->is_active)> Aktif</label><button type="submit">Simpan</button></form></x-slot>
                        </x-mobile.data-card>
                    @endforeach
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><p class="text-xs text-muted">Target dihitung dalam hari kalender. Tarif manual dapat disesuaikan sesuai kebijakan operasional.</p></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
