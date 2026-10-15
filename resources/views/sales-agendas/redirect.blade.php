<x-app-layout>
    <x-slot name="header"><x-page-header title="Alihkan agenda" description="Pindahkan tanggung jawab agenda kepada Sales lain dalam tim yang sama." /></x-slot>
    <div class="module-page">
        <form method="POST" action="{{ route('sales-agendas.redirect', $agenda) }}" class="mx-auto w-full max-w-2xl">
            @csrf @method('PATCH')
            <x-form-section title="Pengalihan agenda" description="Alasan wajib diisi untuk menjaga riwayat perubahan.">
                @if ($agenda->lead)<div class="mb-4 rounded-2xl border border-orange/30 bg-orange/10 p-4 text-sm text-ink">Agenda ini terhubung ke Lead. Pengalihan agenda tidak memindahkan kepemilikan Lead.</div>@endif
                <div class="grid gap-5">
                    <div><x-input-label for="owner_id" value="Sales baru *" /><select id="owner_id" name="owner_id" class="workspace-control mt-1 block w-full py-2.5 text-sm" required><option value="">Pilih Sales</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('owner_id')" class="mt-2" /></div>
                    <div><x-input-label for="agenda_date" value="Tanggal baru (opsional)" /><x-text-input id="agenda_date" name="agenda_date" type="date" class="mt-1 block w-full" :value="old('agenda_date', $agenda->agenda_date?->format('Y-m-d'))" /><x-input-error :messages="$errors->get('agenda_date')" class="mt-2" /></div>
                    <div><x-input-label for="reason" value="Alasan pengalihan *" /><textarea id="reason" name="reason" rows="4" class="workspace-control mt-1 block w-full" required>{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2" /></div>
                </div>
            </x-form-section>
            <div class="mt-6 flex justify-end gap-3"><a href="{{ route('sales-agendas.show', $agenda) }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink">Batal</a><x-primary-button>Alihkan agenda</x-primary-button></div>
        </form>
    </div>
</x-app-layout>
