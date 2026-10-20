<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Buku Saku Sales" description="Susun rencana kerja berdasarkan durasi. Jam mulai dan selesai dihitung otomatis.">
            <x-slot name="actions"><a href="{{ route('dashboard') }}" class="module-button module-button-secondary">Kembali ke dashboard</a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        @if (session('status') === 'daily-plan-submitted')<x-ui.toast message="Rencana kerja berhasil dikirim ke Koordinator." />@endif
        @if (session('status') === 'agenda-created')<x-ui.toast message="Agenda ditambahkan ke rencana kerja." />@endif
        @if (! $plan)
            <x-empty-state title="Team Sales belum dapat ditentukan." description="Pilih satu Team aktif atau hubungi admin agar Sales memiliki Team final yang deterministik." />
        @else
            @php($progress = $requiredMinutes > 0 ? min(100, (int) round(($totalMinutes / $requiredMinutes) * 100)) : 0)
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(300px,380px)]">
                <div class="grid gap-4">
                    <x-form-section title="{{ $plan->sales->name }} · {{ $plan->plan_date->translatedFormat('l, d F Y') }}" description="{{ $plan->status === 'submitted' ? 'Rencana sudah dikirim. Koordinator dapat melakukan penyesuaian dengan total tetap 420 menit.' : 'Draft dapat disimpan sebagian. Submit tersedia saat total tepat 420 menit.' }}">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-canvas p-4"><span class="text-xs text-muted">Rencana kerja</span><strong class="mt-1 block text-2xl text-ink">{{ $totalMinutes }} / {{ $requiredMinutes }}</strong><span class="text-xs text-muted">menit efektif</span></div>
                            <div class="rounded-2xl bg-canvas p-4"><span class="text-xs text-muted">Status</span><strong class="mt-1 block text-lg text-ink">{{ strtoupper($plan->status) }}</strong><span class="text-xs text-muted">{{ $plan->team->name }}</span></div>
                            <div class="rounded-2xl bg-canvas p-4"><span class="text-xs text-muted">Progress rencana</span><strong class="mt-1 block text-2xl text-ink">{{ $progress }}%</strong><div class="mt-2 h-2 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-lime" style="width: {{ $progress }}%"></span></div></div>
                        </div>
                    </x-form-section>

                    <x-form-section title="Timeline agenda" description="Istirahat 12:00–13:00 tidak dihitung sebagai durasi kerja.">
                        @if ($canEdit)<form id="daily-plan-reorder" method="POST" action="{{ route('daily-plans.reorder', $plan) }}">@csrf @method('PATCH') @endif
                        <div id="daily-plan-agendas" class="grid gap-3">
                            @forelse ($plan->agendas as $agenda)
                                <article class="rounded-2xl border border-line bg-surface p-4" data-agenda-id="{{ $agenda->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">{{ $agenda->sort_order }} · {{ $agenda->activity_category }}</p><h3 class="mt-1 text-base font-bold text-ink">{{ $agenda->title }}</h3><p class="mt-1 text-sm text-muted">{{ $agenda->plan_detail }}</p></div><div class="text-right"><strong class="block text-sm text-ink">{{ $agenda->duration_minutes }} menit</strong><span class="text-xs text-muted">{{ $agenda->planned_start_at ? \App\Support\BusinessTime::format($agenda->planned_start_at, 'H:i').'–'.\App\Support\BusinessTime::format($agenda->planned_end_at, 'H:i') : 'Legacy agenda' }}</span></div></div>
                                    @if ($agenda->planned_start_at && $agenda->planned_start_at->setTimezone(config('app.business_timezone'))->format('H:i') < '13:00' && $agenda->planned_end_at->setTimezone(config('app.business_timezone'))->format('H:i') > '13:00')<p class="mt-3 rounded-xl bg-orange/10 px-3 py-2 text-xs text-orange">Agenda melewati istirahat 12:00–13:00; tetap satu agenda dengan {{ $agenda->duration_minutes }} menit efektif.</p>@endif
                                    @if ($agenda->plan_detail && $agenda->planned_quantity)<p class="mt-2 text-xs text-muted">Target kuantitas: <strong class="text-ink">{{ $agenda->planned_quantity }}</strong></p>@endif
                                    @if ($canEdit)<div class="mt-3 flex flex-wrap gap-2"><a href="{{ route('sales-agendas.edit', $agenda) }}" class="module-button module-button-secondary">Edit</a><button type="button" class="module-button module-button-secondary" onclick="moveDailyAgenda({{ $agenda->id }}, -1)">Naik</button><button type="button" class="module-button module-button-secondary" onclick="moveDailyAgenda({{ $agenda->id }}, 1)">Turun</button><form method="POST" action="{{ $isCoordinator ? route('sales-agendas.cancel-by-coordinator', $agenda) : route('sales-agendas.cancel', $agenda) }}">@csrf @method('PATCH') @if ($isCoordinator)<input type="hidden" name="reason" value="Penyesuaian rencana kerja">@endif<button class="module-button module-button-secondary" type="submit">Batal</button></form></div>@endif
                                </article>
                            @empty
                                <x-empty-state title="Belum ada agenda." description="Tambahkan aktivitas dengan durasi dan detail yang bermakna." />
                            @endforelse
                        </div>
                        @if ($canEdit)<button type="submit" class="module-button module-button-secondary mt-3">Simpan urutan agenda</button></form>@endif
                    </x-form-section>

                    @if ($canEdit)
                        <x-form-section title="Tambah agenda" description="Contoh detail TikTok Live: tema, proyek, poin utama, dan CTA.">
                            <form method="POST" action="{{ route('daily-plans.agendas.store', $plan) }}" class="grid gap-4 md:grid-cols-2">
                                @csrf
                                <div><x-input-label for="activity_category" value="Kategori *" /><select id="activity_category" name="activity_category" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>@foreach ($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div>
                                <div><x-input-label for="title" value="Aktivitas *" /><x-text-input id="title" name="title" class="mt-1 block w-full" required placeholder="TikTok Live" /></div>
                                <div><x-input-label for="duration_minutes" value="Durasi (menit) *" /><x-text-input id="duration_minutes" name="duration_minutes" type="number" min="1" max="420" class="mt-1 block w-full" required placeholder="60" /></div>
                                <div><x-input-label for="planned_quantity" value="Target kuantitas (opsional)" /><x-text-input id="planned_quantity" name="planned_quantity" type="number" min="0" class="mt-1 block w-full" /></div>
                                <div class="md:col-span-2"><x-input-label for="plan_detail" value="Detail / Rencana Kegiatan *" /><textarea id="plan_detail" name="plan_detail" rows="4" class="workspace-control mt-1 block w-full py-2.5 text-sm" placeholder="Jelaskan tema Live, proyek yang dibahas, poin utama, dan CTA." required></textarea></div>
                                <div><x-input-label for="location" value="Lokasi (opsional)" /><x-text-input id="location" name="location" class="mt-1 block w-full" /></div>
                                <div><x-input-label for="lead_id" value="Lead (opsional)" /><select id="lead_id" name="lead_id" class="workspace-control mt-1 block w-full py-2.5 text-sm"><option value="">Belum terhubung</option>@foreach ($leads as $lead)<option value="{{ $lead->id }}">{{ $lead->id_lead }} · {{ $lead->nama_konsumen }}</option>@endforeach</select></div>
                                <div class="md:col-span-2"><button type="submit" class="module-button module-button-primary">Tambahkan ke rencana</button></div>
                            </form>
                        </x-form-section>
                    @endif
                </div>

                <aside class="grid content-start gap-4">
                    <div class="rounded-3xl border border-line bg-surface p-5"><p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Jam kerja efektif</p><div class="mt-3 grid gap-2 text-sm"><div class="flex justify-between"><span>Blok pagi</span><strong>09:00–12:00</strong></div><div class="flex justify-between"><span>Istirahat</span><strong>12:00–13:00</strong></div><div class="flex justify-between"><span>Blok sore</span><strong>13:00–17:00</strong></div></div></div>
                    @if ($plan->status === 'draft' && $plan->isWeekday() && $totalMinutes === $requiredMinutes && $plan->sales_id === Auth::id())<form method="POST" action="{{ route('daily-plans.submit', $plan) }}" class="rounded-3xl border border-lime/30 bg-lime-soft p-5">@csrf<button type="submit" class="module-button module-button-primary w-full">Submit ke Koordinator</button><p class="mt-2 text-center text-xs text-muted">Rencana kerja lengkap 420 / 420 menit.</p></form>@elseif ($plan->status === 'draft')<div class="rounded-3xl border border-line bg-surface p-5"><p class="text-sm font-semibold text-ink">{{ $totalMinutes > $requiredMinutes ? 'Durasi melebihi batas.' : 'Rencana belum lengkap.' }}</p><p class="mt-1 text-xs text-muted">Submit tersedia hanya pada hari kerja dengan total tepat 420 menit.</p></div>@else<div class="rounded-3xl border border-lime/30 bg-lime-soft p-5"><p class="text-sm font-semibold text-ink">Rencana sudah dikirim</p><p class="mt-1 text-xs text-muted">Dikirim {{ $plan->submitted_at?->setTimezone(config('app.business_timezone'))->format('d/m/Y H:i') }}.</p></div>@endif
                </aside>
            </div>
        @endif
    </div>
    @if ($canEdit)<script>
        function syncDailyAgendaOrder() {
            const container = document.getElementById('daily-plan-agendas');
            document.querySelectorAll('#daily-plan-reorder input[name="agenda_ids[]"]').forEach((input) => input.remove());
            [...container.querySelectorAll('[data-agenda-id]')].forEach((agenda) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'agenda_ids[]'; input.value = agenda.dataset.agendaId;
                document.getElementById('daily-plan-reorder').appendChild(input);
            });
        }
        function moveDailyAgenda(id, direction) {
            const container = document.getElementById('daily-plan-agendas');
            const item = container.querySelector(`[data-agenda-id="${id}"]`);
            if (!item) return;
            if (direction < 0 && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
            if (direction > 0 && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
            syncDailyAgendaOrder();
        }
        document.addEventListener('DOMContentLoaded', syncDailyAgendaOrder);
    </script>@endif
</x-app-layout>
