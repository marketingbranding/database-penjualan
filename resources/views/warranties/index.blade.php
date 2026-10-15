<x-app-layout>
    <x-slot name="header"><x-page-header title="Garansi" description="Pantau laporan garansi, komplain, dan tindak lanjut konsumen." /></x-slot>

    <div class="module-page">
        @if (session('status'))<x-ui.toast message="Data garansi berhasil diproses." />@endif
        @can('warranties.create')
            <x-form-section title="Tambah garansi" description="Catat laporan garansi dari transaksi yang sudah tersedia.">
                <form method="POST" action="{{ route('warranties.store') }}" class="module-process-form col-span-2">@csrf
                    <div class="module-form-grid">
                        <div class="md:col-span-2"><x-input-label value="Transaksi"/><select name="sales_case_id" required class="workspace-control mt-2 block w-full"><option value="">Pilih konsumen</option>@foreach($salesCases as $case)<option value="{{ $case->id }}">{{ $case->consumer->nama_konsumen }} · {{ $case->lot->code }}</option>@endforeach</select></div>
                        <div><x-input-label value="BAST"/><select name="bast_case_id" class="workspace-control mt-2 block w-full"><option value="">Belum dipilih</option>@foreach($basts as $bast)<option value="{{ $bast->id }}">{{ $bast->bast_number }}</option>@endforeach</select></div>
                        <div><x-input-label value="Status"/><select name="status" class="workspace-control mt-2 block w-full"><option value="belum_dipilih">Belum dipilih</option><option value="tidak_ada_komplain">Tidak ada komplain</option><option value="proses">Proses</option><option value="selesai">Selesai</option><option value="review_data">Review data</option></select></div>
                        <div><x-input-label value="Status komplain"/><x-text-input name="complaint_status" class="mt-2 block w-full"/></div><div><x-input-label value="Tanggal laporan"/><x-text-input name="reported_at" type="date" class="mt-2 block w-full"/></div><div><x-input-label value="PIC"/><select name="assigned_to" class="workspace-control mt-2 block w-full"><option value="">Belum dipilih</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div><x-input-label value="Tanggal selesai"/><x-text-input name="resolved_at" type="date" class="mt-2 block w-full"/></div>
                        <div class="md:col-span-4"><x-input-label value="Detail garansi"/><textarea name="detail" rows="3" class="workspace-control mt-2 block w-full"></textarea></div>
                        <div class="md:col-span-4 flex justify-end"><x-primary-button>Simpan garansi</x-primary-button></div>
                    </div>
                </form>
            </x-form-section>
        @endcan

        <x-table-card title="Daftar garansi" :count="number_format($records->total(), 0, ',', '.').' garansi'" class="min-h-0">
            <x-data-table caption="Daftar garansi">
                <x-slot name="header"><tr><th>Garansi</th><th>Konsumen</th><th>BAST</th><th>Komplain</th><th>Status</th><th>PIC</th></tr></x-slot>
                <x-slot name="body">
                    @forelse ($records as $record)
                        <tr><td class="font-semibold">{{ $record->warranty_number }}</td><td><div class="font-semibold">{{ $record->salesCase->consumer->nama_konsumen }}</div><div class="text-[11px] text-muted">{{ $record->salesCase->lot->code }}</div></td><td class="text-muted">{{ $record->bast?->bast_number ?? '-' }}</td><td class="text-muted">{{ $record->complaint_status ?? '-' }}</td><td><x-status-badge :status="str($record->status)->replace('_', ' ')->title()" :tone="$record->status === 'selesai' ? 'success' : ($record->status === 'proses' ? 'warning' : 'neutral')" /></td><td class="text-muted">{{ $record->assignee?->name ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Belum ada data garansi." /></td></tr>
                    @endforelse
                </x-slot>
                <x-slot name="mobile">
                    @forelse ($records as $record)
                        <x-mobile.data-card :title="$record->warranty_number" :subtitle="$record->salesCase->consumer->nama_konsumen.' · '.$record->salesCase->lot->code" :chip="str($record->status)->replace('_', ' ')->title()">
                            <x-slot name="details">
                                <div class="mobile-data-card-detail"><small>BAST</small><strong>{{ $record->bast?->bast_number ?? '-' }}</strong></div>
                                <div class="mobile-data-card-detail"><small>PIC</small><strong>{{ $record->assignee?->name ?? '-' }}</strong></div>
                                <div class="mobile-data-card-detail"><small>Komplain</small><strong>{{ $record->complaint_status ?? '-' }}</strong></div>
                                <div class="mobile-data-card-detail"><small>Laporan</small><strong>{{ $record->reported_at?->format('d M Y') ?? '-' }}</strong></div>
                            </x-slot>
                        </x-mobile.data-card>
                    @empty
                        <x-empty-state title="Belum ada data garansi." />
                    @endforelse
                </x-slot>
            </x-data-table>
            <x-slot name="footer"><x-pagination :paginator="$records" /></x-slot>
        </x-table-card>

        @can('warranties.update')
            <x-form-section title="Perbarui status garansi" description="Perubahan status tetap menggunakan data garansi existing.">
                <div class="col-span-2 grid gap-3 md:grid-cols-2">
                    @foreach ($records as $record)
                        <form method="POST" action="{{ route('warranties.update', $record) }}" class="module-inline-update-card">@csrf @method('PATCH')
                            <input type="hidden" name="sales_case_id" value="{{ $record->sales_case_id }}"><input type="hidden" name="bast_case_id" value="{{ $record->bast_case_id }}"><input type="hidden" name="consumer_id" value="{{ $record->consumer_id }}"><input type="hidden" name="lot_id" value="{{ $record->lot_id }}"><input type="hidden" name="assigned_to" value="{{ $record->assigned_to }}"><input type="hidden" name="complaint_status" value="{{ $record->complaint_status }}"><input type="hidden" name="reported_at" value="{{ $record->reported_at?->format('Y-m-d') }}"><input type="hidden" name="sales_to_sam_at" value="{{ $record->sales_to_sam_at?->format('Y-m-d') }}"><input type="hidden" name="sam_to_sat_at" value="{{ $record->sam_to_sat_at?->format('Y-m-d') }}"><input type="hidden" name="sat_to_sam_at" value="{{ $record->sat_to_sam_at?->format('Y-m-d') }}"><input type="hidden" name="sam_to_sales_at" value="{{ $record->sam_to_sales_at?->format('Y-m-d') }}"><input type="hidden" name="sales_to_consumer_at" value="{{ $record->sales_to_consumer_at?->format('Y-m-d') }}"><input type="hidden" name="detail" value="{{ $record->detail }}"><input type="hidden" name="resolved_at" value="{{ $record->resolved_at?->format('Y-m-d') }}">
                            <p class="text-sm font-semibold">{{ $record->warranty_number }} · {{ $record->salesCase->consumer->nama_konsumen }}</p><div class="module-inline-form mt-3"><select name="status" class="module-form-control grow"><option value="belum_dipilih" @selected($record->status === 'belum_dipilih')>Belum dipilih</option><option value="tidak_ada_komplain" @selected($record->status === 'tidak_ada_komplain')>Tidak ada komplain</option><option value="proses" @selected($record->status === 'proses')>Proses</option><option value="selesai" @selected($record->status === 'selesai')>Selesai</option><option value="review_data" @selected($record->status === 'review_data')>Review data</option></select><button class="module-row-primary">Simpan</button></div>
                        </form>
                    @endforeach
                </div>
            </x-form-section>
        @endcan
    </div>
</x-app-layout>
