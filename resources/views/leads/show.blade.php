<x-app-layout>
    @php($qualificationLabels = [
        'not_qualified' => 'Belum memenuhi syarat',
        'qualified' => 'Memenuhi syarat',
        'not_fit' => 'Tidak sesuai',
        'deferred' => 'Ditunda',
    ])

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-orange">Buku saku sales</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Detail Lead</h1>
            </div>
            <div class="flex w-full gap-2 sm:w-auto">
                <a href="{{ route('leads.index') }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas sm:flex-none">Kembali</a>
                @can('update', $lead)
                    <a href="{{ route('leads.edit', $lead) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white sm:flex-none">Edit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-ui.toast message="Perubahan Lead berhasil disimpan." />
        @endif

        <section class="workspace-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-orange">{{ $lead->id_lead }}</p>
                    <h2 class="mt-1 break-words text-2xl font-semibold tracking-tight text-ink">{{ $lead->nama_konsumen }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ $lead->no_hp }}</p>
                </div>
                <x-ui.stage-chip :stage="$lead->status_lead" />
            </div>

            <dl class="mt-6 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Tanggal Lead</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $lead->tanggal_lead->translatedFormat('d M Y') }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Sales PIC</dt><dd class="mt-1 text-sm font-medium text-ink">{{ $lead->salesPic->name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Sumber / Kanal</dt><dd class="mt-1 text-sm text-ink">{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Aktivitas awal</dt><dd class="mt-1 text-sm text-ink">{{ $lead->aktivitas_lead }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Promo</dt><dd class="mt-1 text-sm text-ink">{{ $lead->id_promo ?: '-' }} · {{ $lead->nama_promo ?: '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Proyek</dt><dd class="mt-1 text-sm text-ink">{{ $lead->project?->name ?: '-' }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Tim / Cabang</dt><dd class="mt-1 text-sm text-ink">{{ $lead->team?->name ?: '-' }} / {{ $lead->branch->name }}</dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Diinput oleh</dt><dd class="mt-1 text-sm text-ink">{{ $lead->creator->name }}</dd></div>
                @if ($lead->originAgenda)
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-muted">Berasal dari agenda</dt><dd class="mt-1 text-sm text-ink"><a href="{{ route('sales-agendas.edit', $lead->originAgenda) }}" class="font-semibold text-lime-dark hover:underline">{{ $lead->originAgenda->title }}</a> · {{ $lead->originAgenda->agenda_date->format('d M Y') }}</dd></div>
                @endif
            </dl>

            <div class="mt-6 border-t border-line pt-5">
                <dt class="text-xs font-medium uppercase tracking-wide text-muted">Keterangan</dt>
                <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-ink">{{ $lead->keterangan ?: '-' }}</dd>
            </div>
        </section>

        @can('update', $lead)
            <section class="grid gap-5 lg:grid-cols-2">
                <form method="POST" action="{{ route('leads.follow-up.update', $lead) }}" class="workspace-card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold tracking-tight text-ink">Follow-up berikutnya</h2>
                    <p class="mt-1 text-sm leading-6 text-muted">Jadwalkan pekerjaan berikutnya tanpa mengikat Lead ke proses penjualan.</p>
                    <div class="mt-4"><x-input-label for="next_follow_up_at" value="Tanggal & waktu" /><x-text-input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local" class="workspace-control mt-2 block w-full py-2.5 text-sm" :value="old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i'))" /></div>
                    <div class="mt-4"><x-input-label for="next_follow_up_note" value="Catatan" /><textarea id="next_follow_up_note" name="next_follow_up_note" rows="3" class="workspace-control mt-2 block w-full p-3 text-sm text-ink">{{ old('next_follow_up_note', $lead->next_follow_up_note) }}</textarea></div>
                    <button class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark sm:w-auto">Simpan jadwal</button>
                </form>

                <form method="POST" action="{{ route('leads.qualification.update', $lead) }}" class="workspace-card p-6">
                    @csrf @method('PATCH')
                    <h2 class="text-lg font-semibold tracking-tight text-ink">Kualifikasi</h2>
                    <p class="mt-1 text-sm leading-6 text-muted">Kualifikasi ini hanya untuk Buku Saku Sales.</p>
                    <div class="mt-4"><x-input-label for="qualification_status" value="Status kualifikasi" /><select id="qualification_status" name="qualification_status" class="workspace-control mt-2 block w-full py-2.5 text-sm">@foreach (App\Models\Lead::QUALIFICATION_STATUSES as $status)<option value="{{ $status }}" @selected($lead->qualification_status === $status)>{{ $qualificationLabels[$status] ?? $status }}</option>@endforeach</select></div>
                    <div class="mt-4"><x-input-label for="qualification_note" value="Catatan kualifikasi" /><textarea id="qualification_note" name="qualification_note" rows="3" class="workspace-control mt-2 block w-full p-3 text-sm text-ink">{{ old('qualification_note', $lead->qualification_note) }}</textarea></div>
                    <button class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark sm:w-auto">Simpan kualifikasi</button>
                </form>

            </section>
        @endcan

        @can('update', $lead)
            <form method="POST" action="{{ route('leads.activities.store', $lead) }}" class="workspace-card p-6">
                @csrf
                <h2 class="text-lg font-semibold tracking-tight text-ink">Catat aktivitas</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div><x-input-label for="activity_type" value="Jenis aktivitas *" /><select id="activity_type" name="activity_type" class="workspace-control mt-2 block w-full py-2.5 text-sm" required>@foreach (App\Models\Lead::ACTIVITY_TYPES as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                    <div><x-input-label for="status_lead" value="Perbarui status Lead" /><select id="status_lead" name="status_lead" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Tidak mengubah status</option>@foreach (App\Models\Lead::STATUSES as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></div>
                    <div><x-input-label for="happened_at" value="Waktu aktivitas *" /><x-text-input id="happened_at" name="happened_at" type="datetime-local" class="workspace-control mt-2 block w-full py-2.5 text-sm" required value="{{ now()->format('Y-m-d\TH:i') }}" /></div>
                    <div class="md:col-span-2"><x-input-label for="description" value="Catatan aktivitas *" /><textarea id="description" name="description" rows="3" class="workspace-control mt-2 block w-full p-3 text-sm text-ink" required></textarea></div>
                </div>
                <div class="mt-4 flex justify-stretch sm:justify-end"><x-primary-button>Simpan aktivitas</x-primary-button></div>
            </form>
        @endcan

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="workspace-card p-6">
                <h2 class="text-lg font-semibold tracking-tight text-ink">Timeline aktivitas</h2>
                <div class="mt-5 space-y-5">
                    @forelse ($lead->activities as $activity)
                        <div class="border-l-2 border-lime pl-4">
                            <div class="flex flex-wrap justify-between gap-2"><p class="font-semibold text-ink">{{ $activity->activity_type }}</p><time class="text-xs text-muted">{{ $activity->happened_at->format('d/m/Y H:i') }}</time></div>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-muted">{{ $activity->description }}</p>
                            @if ($activity->status_to)<p class="mt-1 text-xs font-medium text-lime-dark">{{ $activity->status_from ?: 'Awal' }} &rarr; {{ $activity->status_to }}</p>@endif
                            <p class="mt-1 text-xs text-muted">{{ $activity->user?->name ?: 'Sistem' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-muted">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </section>

            <section class="workspace-card p-6">
                <h2 class="text-lg font-semibold tracking-tight text-ink">Agenda terkait</h2>
                <div class="mt-5 space-y-4">
                    @forelse ($lead->salesAgendas as $agenda)
                        <div class="rounded-2xl bg-canvas p-4"><div class="flex flex-wrap items-start justify-between gap-2"><p class="font-semibold text-ink">{{ $agenda->title }}</p><x-ui.pill tone="neutral">{{ $agenda->status }}</x-ui.pill></div><p class="mt-1 text-sm text-muted">{{ $agenda->agenda_date->format('d/m/Y') }} · {{ $agenda->activity_category }}</p><p class="mt-1 text-sm leading-6 text-muted">{{ $agenda->activity_result ?: 'Belum ada hasil aktivitas.' }}</p></div>
                    @empty
                        <p class="text-sm text-muted">Belum ada agenda yang terhubung.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
