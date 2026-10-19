<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$readonly ? 'Detail agenda' : ($agenda ? 'Edit agenda' : 'Agenda baru')" description="Jadwalkan follow-up lapangan dan tautkan ke Lead atau transaksi." />
    </x-slot>

    <div class="module-page">
        @if ($readonly)
            <div class="mx-auto grid w-full max-w-5xl gap-4">
                <x-form-section title="Detail agenda" description="Informasi agenda dan riwayat penugasannya.">
                    <div class="grid gap-4 text-sm md:grid-cols-2">
                        <div><span class="text-muted">Tanggal</span><p class="mt-1 font-semibold text-ink">{{ $agenda->agenda_date->translatedFormat('d M Y') }}</p></div>
                        <div><span class="text-muted">Status</span><p class="mt-1 font-semibold text-ink">{{ str($agenda->status)->replace('_', ' ')->title() }}</p></div>
                        <div><span class="text-muted">Judul</span><p class="mt-1 font-semibold text-ink">{{ $agenda->title }}</p></div>
                        <div><span class="text-muted">Kategori</span><p class="mt-1 font-semibold text-ink">{{ $agenda->activity_category }}</p></div>
                        <div><span class="text-muted">Pemilik agenda</span><p class="mt-1 font-semibold text-ink">{{ $agenda->owner?->name ?? '-' }}</p></div>
                        <div><span class="text-muted">Dibuat oleh</span><p class="mt-1 font-semibold text-ink">{{ $agenda->creator?->name ?? '-' }}</p></div>
                        <div><span class="text-muted">Lokasi</span><p class="mt-1 font-semibold text-ink">{{ $agenda->location ?: '-' }}</p></div>
                        <div><span class="text-muted">Hasil aktivitas</span><p class="mt-1 font-semibold text-ink">{{ $agenda->activity_result ?: '-' }}</p></div>
                    </div>
                </x-form-section>

                <x-form-section title="Bukti foto" description="Bukti yang terhubung dengan agenda ini.">
                    @forelse ($agenda->evidence as $evidence)
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($evidence->file_path) }}" target="_blank" rel="noopener noreferrer" class="block border-b border-line py-3 text-sm font-semibold text-lime-dark last:border-0 hover:underline">{{ $evidence->original_name }}</a>
                    @empty
                        <p class="text-sm text-muted">Belum ada bukti foto.</p>
                    @endforelse
                </x-form-section>

                @if ($agenda->lead)
                    <div class="rounded-2xl border border-orange/30 bg-orange/10 p-4 text-sm text-ink">Agenda ini terhubung ke Lead {{ $agenda->lead->id_lead }}. Pengalihan agenda tidak mengubah kepemilikan Lead.</div>
                @endif

                <x-form-section title="Riwayat penugasan" description="Perubahan owner dan pembatalan yang dilakukan koordinator.">
                    @forelse ($agenda->assignmentHistories as $history)
                        <div class="border-b border-line py-3 last:border-0">
                            <p class="text-sm font-semibold text-ink">{{ match ($history->action) { 'created_for' => 'Dibuat untuk', 'redirected' => 'Dialihkan', 'cancelled_by_coordinator' => 'Dibatalkan oleh koordinator', default => $history->action } }}</p>
                            <p class="mt-1 text-xs text-muted">{{ $history->fromUser?->name ?? '—' }} → {{ $history->toUser?->name ?? '—' }} · oleh {{ $history->byUser?->name ?? '—' }} · {{ \App\Support\BusinessTime::format($history->created_at) }}</p>
                            @if ($history->reason)<p class="mt-1 text-sm text-muted">{{ $history->reason }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-muted">Belum ada riwayat pengalihan atau pembatalan.</p>
                    @endforelse
                </x-form-section>

                <div class="flex justify-end"><a href="{{ route('sales-agendas.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Kembali</a></div>
            </div>
        @else
        <form method="POST" enctype="multipart/form-data" action="{{ $agenda ? route('sales-agendas.update', $agenda) : route('sales-agendas.store') }}" class="mx-auto w-full max-w-5xl">
            @csrf
            @if ($agenda)
                @method('PUT')
            @endif

            <x-form-section :title="$agenda ? 'Perbarui agenda' : 'Detail agenda'" description="Field bertanda * wajib diisi.">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    @if ($canChooseOwner)
                        <x-input-label for="owner_id" value="Pemilik agenda" />
                        <select id="owner_id" name="owner_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                            <option value="{{ Auth::id() }}">Saya sendiri</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}" @selected((string) old('owner_id') === (string) $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('owner_id')" class="mt-2" />
                    @endif
                </div>

                <div>
                    <x-input-label for="agenda_date" value="Tanggal Agenda *" />
                    <x-text-input id="agenda_date" name="agenda_date" type="date" class="mt-1 block w-full" required value="{{ old('agenda_date', $agenda?->agenda_date?->format('Y-m-d') ?? today()->toDateString()) }}" />
                    <x-input-error :messages="$errors->get('agenda_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="activity_category" value="Kategori Aktivitas *" />
                    <select id="activity_category" name="activity_category" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('activity_category', $agenda?->activity_category) === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('activity_category')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="title" value="Judul Agenda *" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" required value="{{ old('title', $agenda?->title) }}" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="location" value="Lokasi" />
                    <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" value="{{ old('location', $agenda?->location) }}" />
                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="sales_case_id" value="Konsumen / Sales Case (opsional)" />
                    <select id="sales_case_id" name="sales_case_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                        <option value="">Belum terhubung</option>
                        @foreach ($salesCases as $salesCase)
                            <option value="{{ $salesCase->id }}" @selected((string) old('sales_case_id', $agenda?->sales_case_id) === (string) $salesCase->id)>{{ $salesCase->consumer->nama_konsumen }} · {{ $salesCase->lot->project->name }}-{{ $salesCase->lot->code }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('sales_case_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="lead_id" value="Lead (opsional)" />
                    <select id="lead_id" name="lead_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                        <option value="">Belum terhubung</option>
                        @foreach ($leads as $leadOption)
                            <option value="{{ $leadOption->id }}" @selected((string) old('lead_id', $agenda?->lead_id) === (string) $leadOption->id)>{{ $leadOption->id_lead }} · {{ $leadOption->nama_konsumen }} · {{ $leadOption->salesPic->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lead_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="evidence" value="Bukti Foto" />
                    <input id="evidence" name="evidence[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="workspace-control mt-1 block w-full px-3 py-2 text-sm text-muted file:mr-3 file:rounded file:border-0 file:bg-lime-soft file:px-3 file:py-1 file:font-semibold file:text-lime-dark" />
                    <p class="mt-1 text-xs text-muted">Opsional. Maksimal 2 foto JPEG, PNG, atau WebP. Maksimal 10 MB per foto.</p>
                    <x-input-error :messages="$errors->get('evidence')" class="mt-2" />
                    <x-input-error :messages="$errors->get('evidence.*')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="activity_result" value="Hasil Aktivitas" />
                    <textarea id="activity_result" name="activity_result" rows="4" class="workspace-control mt-1 block w-full py-2.5 text-sm">{{ old('activity_result', $agenda?->activity_result) }}</textarea>
                    <x-input-error :messages="$errors->get('activity_result')" class="mt-2" />
                </div>
            </div>
            </x-form-section>

            @if ($agenda?->evidence?->isNotEmpty())
                <div class="module-table-card mt-3 p-5">
                    <p class="text-sm font-semibold text-ink">Bukti foto tersimpan</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($agenda->evidence as $evidence)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($evidence->file_path) }}" target="_blank" class="text-sm font-semibold text-lime-dark hover:underline">{{ $evidence->original_name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('sales-agendas.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Batal</a>
                <x-primary-button>{{ $agenda ? 'Perbarui agenda' : 'Simpan agenda' }}</x-primary-button>
            </div>
        </form>
        @endif
    </div>
</x-app-layout>
