<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ $salesCase->consumer->nama_konsumen }}" description="Detail konsumen, reservasi, dan progres proses penjualan.">
            <x-slot name="actions">@can('update', $salesCase)<a href="{{ route('consumers.edit', $salesCase) }}" class="module-button module-button-primary">Edit data</a>@endcan</x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        @if (session('status'))
            <x-ui.toast message="Reservasi berhasil dibatalkan dan kavling kembali tersedia." />
        @endif

        @if ($salesCase->isCancelled())
            <div class="workspace-card border-red-200 bg-red-50 p-5"><p class="font-semibold text-red-900">Reservasi dibatalkan</p><p class="mt-2 text-sm text-red-800">{{ $salesCase->cancellation_reason }}</p><p class="mt-2 text-xs text-red-700">{{ \App\Support\BusinessTime::format($salesCase->cancelled_at, 'd M Y H:i') }} oleh {{ $salesCase->cancelledBy?->name ?? 'Sistem' }}</p></div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <article class="rounded-3xl bg-ink p-5 text-white"><p class="text-xs font-medium text-lime">Kavling</p><p class="mt-2 text-xl font-semibold">{{ $salesCase->lot->project->name }}-{{ $salesCase->lot->code }}</p></article>
            <article class="workspace-card p-5"><p class="text-xs font-medium text-muted">Cabang</p><p class="mt-2 font-semibold text-ink">{{ $salesCase->lot->project->branch->name }}</p></article>
            <article class="workspace-card p-5"><p class="text-xs font-medium text-muted">Pembayaran</p><p class="mt-2 font-semibold text-ink">{{ $salesCase->payment_method->value }}</p></article>
            <article class="workspace-card p-5"><p class="text-xs font-medium text-muted">Promo</p>@if($salesCase->promotion)<p class="mt-2 font-semibold text-ink">{{ $salesCase->promotion->promo_id }}</p><p class="text-sm text-muted">{{ $salesCase->promotion->name }}</p>@else<p class="mt-2 font-semibold text-muted">Tanpa promo</p>@endif</article>
            <article class="workspace-card p-5"><p class="text-xs font-medium text-muted">Status kavling</p><p class="mt-2 font-semibold text-ink">{{ $salesCase->lot->status->label() }}</p></article>
        </section>

        <x-ui.card title="Timeline proses" description="{{ $salesCase->psjbUtj?->process_stage ? str($salesCase->psjbUtj->process_stage)->replace('_', ' ')->title() : 'Belum dimulai' }}" padding="p-6">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($timeline as $step)
                    <div class="rounded-2xl border border-line bg-canvas p-4"><div class="flex items-start justify-between gap-3"><p class="text-sm font-semibold text-ink">{{ $step['label'] }}</p><x-ui.pill tone="neutral">{{ str($step['status'])->replace('_', ' ')->title() }}</x-ui.pill></div><p class="mt-3 text-xs text-muted">{{ $step['date']?->translatedFormat('d M Y') ?? 'Belum ada tanggal' }}</p><a href="{{ route($step['route']) }}" class="mt-3 inline-flex text-xs font-semibold text-lime-dark hover:underline">Buka modul</a></div>
                @endforeach
            </div>
        </x-ui.card>

        <section class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Identitas" padding="p-6"><dl class="grid grid-cols-[140px_1fr] gap-x-4 gap-y-3 text-sm"><dt class="text-muted">NIK</dt><dd class="font-semibold text-ink">{{ $salesCase->consumer->no_ktp }}</dd><dt class="text-muted">Tanggal lahir</dt><dd>{{ $salesCase->consumer->tanggal_lahir->translatedFormat('d F Y') }} ({{ $salesCase->consumer->age }} tahun)</dd><dt class="text-muted">Pekerjaan</dt><dd>{{ $salesCase->consumer->pekerjaan }}</dd><dt class="text-muted">Detail</dt><dd>{{ $salesCase->consumer->detail_pekerjaan ?: '-' }}</dd><dt class="text-muted">No. HP</dt><dd>{{ $salesCase->consumer->no_hp }}</dd></dl></x-ui.card>
            <x-ui.card title="Alamat & Kontak" padding="p-6"><p class="text-sm leading-6 text-muted">{{ $salesCase->consumer->alamat }}, {{ $salesCase->consumer->kelurahan }}, {{ $salesCase->consumer->kecamatan }}, {{ $salesCase->consumer->kabupaten_kota }}</p><div class="mt-5 border-t border-line pt-5 text-sm"><p class="text-muted">Kontak darurat</p><p class="mt-1 font-semibold text-ink">{{ $salesCase->consumer->nama_kontak_darurat }} · {{ $salesCase->consumer->no_hp_kontak_darurat }}</p></div></x-ui.card>
        </section>

        <x-ui.card title="Riwayat pembelian" padding="p-0"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line text-sm"><thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Kavling</th><th class="px-6 py-4">Cabang</th><th class="px-6 py-4">Metode</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Tanggal input</th></tr></thead><tbody class="divide-y divide-line">@foreach($relatedSalesCases as $case)<tr class="transition hover:bg-lime-soft/30"><td class="px-6 py-4 font-semibold text-ink">{{ $case->lot->project->name }}-{{ $case->lot->code }}</td><td class="px-6 py-4 text-muted">{{ $case->lot->project->branch->name }}</td><td class="px-6 py-4 text-muted">{{ $case->payment_method->value }}</td><td class="px-6 py-4"><x-ui.pill :tone="$case->isCancelled() ? 'error' : 'success'">{{ $case->isCancelled() ? 'Dibatalkan' : $case->lot->status->label() }}</x-ui.pill></td><td class="px-6 py-4 text-muted">{{ $case->created_at->translatedFormat('d M Y H:i') }}</td></tr>@endforeach</tbody></table></div></x-ui.card>

        <x-ui.card title="Status konsumen" padding="p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div><x-ui.pill :tone="$salesCase->consumer_status === 'LANJUT' ? 'success' : 'error'">{{ $salesCase->consumer_status }}</x-ui.pill>@if($salesCase->consumer_status_reason)<p class="mt-2 text-sm text-muted">{{ $salesCase->consumer_status_reason }}</p>@endif</div>@if($salesCase->consumer_status !== 'MUNDUR')@can('sales-process.update')<button type="button" onclick="document.getElementById('withdraw-consumer').showModal()" class="inline-flex min-h-10 items-center rounded-2xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50">Tandai mundur</button>@endcan @endif</div></x-ui.card>

        @if (!$salesCase->isCancelled())
            @can('consumers.cancel')
                <section class="workspace-card border-red-200 p-6"><h2 class="text-lg font-semibold text-red-900">Batalkan reservasi</h2><p class="mt-1 text-sm text-muted">Sales case tetap tersimpan sebagai histori dan kavling akan kembali menjadi Tersedia.</p><form method="POST" action="{{ route('consumers.cancel', $salesCase) }}" class="mt-5 grid gap-4">@csrf @method('PATCH')<div><x-input-label for="reason" value="Alasan pembatalan"/><textarea id="reason" name="reason" rows="3" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>{{ old('reason') }}</textarea><x-input-error class="mt-2" :messages="$errors->get('reason')"/></div><div><x-danger-button onclick="return confirm('Batalkan reservasi ini?')">Batalkan reservasi</x-danger-button></div></form></section>
            @endcan
        @endif

        @if ($salesCase->consumer_status !== 'MUNDUR')
            <dialog id="withdraw-consumer" class="rounded-3xl p-0 shadow-xl"><form method="POST" action="{{ route('sales-process.sales-cases.withdraw', $salesCase) }}" class="w-[min(90vw,32rem)] p-6">@csrf @method('PATCH')<h2 class="text-lg font-semibold text-ink">Tandai konsumen mundur</h2><p class="mt-2 text-sm text-muted">Jika belum Akad/BAST, kavling akan kembali tersedia.</p><textarea name="reason" rows="3" class="workspace-control mt-4 block w-full py-2.5 text-sm" placeholder="Alasan mundur" required></textarea><div class="mt-5 flex justify-end gap-3"><button type="button" onclick="this.closest('dialog').close()" class="inline-flex min-h-10 items-center rounded-2xl border border-line px-4 py-2 text-sm font-semibold text-ink">Batal</button><x-danger-button>Konfirmasi mundur</x-danger-button></div></form></dialog>
        @endif
    </div>
</x-app-layout>
