<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Target Lead Sales" description="Atur target bulanan dan pantau periode historis tanpa mengubah data Lead." />
    </x-slot>

    <div class="module-page">
        @if (session('status') === 'sales-lead-target-saved')<x-ui.toast message="Target Lead berhasil disimpan." />@endif
        <x-form-section title="Pilih periode" description="Target disimpan satu kali untuk setiap Sales dan bulan.">
            <form method="GET" action="{{ route('admin.sales-lead-targets.index') }}" class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm font-medium text-ink">Bulan<select name="period_month" class="workspace-control mt-1 block w-full py-2.5 text-sm">@foreach ($months as $value => $label)<option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium text-ink">Tahun<input type="number" name="period_year" min="2020" max="2100" value="{{ $year }}" class="workspace-control mt-1 block w-full py-2.5 text-sm"></label>
                <div class="flex items-end"><button class="module-button module-button-secondary" type="submit">Tampilkan periode</button></div>
            </form>
        </x-form-section>

        <x-table-card title="Target {{ $months[$month] }} {{ $year }}" :count="number_format($salesUsers->count(), 0, ',', '.').' Sales'" class="mt-4 min-h-0">
            <x-data-table caption="Target Lead Sales" compact>
                <x-slot name="header"><tr><th>Sales</th><th>Cabang</th><th>Target Lead</th><th>Terakhir diubah</th><th class="text-right">Aksi</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($salesUsers as $sales)
                        @php($target = $targets->get($sales->id))
                        @php($formId = 'sales-target-'.$sales->id)
                        <tr>
                            <td><form id="{{ $formId }}" method="POST" action="{{ $target ? route('admin.sales-lead-targets.update', $target) : route('admin.sales-lead-targets.store') }}">@csrf @if ($target) @method('PATCH') @else<input type="hidden" name="sales_id" value="{{ $sales->id }}"><input type="hidden" name="period_year" value="{{ $year }}"><input type="hidden" name="period_month" value="{{ $month }}">@endif</form><span class="font-semibold">{{ $sales->name }}</span></td>
                            <td class="text-muted">{{ $sales->branch?->name ?? '—' }}</td>
                            <td><div class="module-inline-form"><input form="{{ $formId }}" type="number" name="lead_target" value="{{ old('lead_target', $target?->lead_target ?? 0) }}" min="0" max="100000" class="module-form-control w-28 text-xs" required><span class="text-[11px] text-muted">Lead</span></div></td>
                            <td class="text-muted">{{ $target?->updated_at?->diffForHumans() ?? 'Belum diatur' }}</td>
                            <td><x-row-actions><x-slot name="primary"><button form="{{ $formId }}" class="module-row-primary">Simpan</button></x-slot></x-row-actions></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Belum ada Sales aktif." description="Sales aktif dengan Team final akan muncul di sini." /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @foreach ($salesUsers as $sales)
                        @php($target = $targets->get($sales->id))
                        <x-mobile.data-card :title="$sales->name" :subtitle="$sales->branch?->name ?? 'Cabang belum diatur'" :chip="$target ? $target->lead_target.' Lead' : 'Belum diatur'">
                            <x-slot name="actions"><form method="POST" action="{{ $target ? route('admin.sales-lead-targets.update', $target) : route('admin.sales-lead-targets.store') }}" class="grid gap-2">@csrf @if ($target) @method('PATCH') @else<input type="hidden" name="sales_id" value="{{ $sales->id }}"><input type="hidden" name="period_year" value="{{ $year }}"><input type="hidden" name="period_month" value="{{ $month }}">@endif<input type="number" name="lead_target" value="{{ $target?->lead_target ?? 0 }}" min="0" max="100000" class="module-form-control" required><button type="submit">Simpan target</button></form></x-slot>
                        </x-mobile.data-card>
                    @endforeach
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><p class="text-xs text-muted">Progress dihitung dari Lead operasional dengan <code>sales_pic_id</code> milik Sales dan tanggal Lead berada di bulan ini. Status Lead tetap dihitung; hanya disposition cutover yang dikecualikan oleh scope operasional yang tidak dihitung.</p></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
