<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-amber-700">Buku Saku Sales</p>
            <h1 class="mt-1 text-xl font-bold">{{ $lead ? 'Edit Lead' : 'Lead Baru' }}</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <form method="POST" action="{{ $lead ? route('leads.update', $lead) : route('leads.store') }}" class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @csrf
            @if ($lead)
                @method('PUT')
            @endif
            @if ($originAgenda)
                <input type="hidden" name="origin_agenda_id" value="{{ $originAgenda->id }}">
                <div class="rounded-xl border border-marison-gold-400/60 bg-marison-gold-100 px-4 py-3 text-sm text-marison-ink-950">Lead ini dibuat dari agenda <a href="{{ route('sales-agendas.edit', $originAgenda) }}" class="font-bold underline">{{ $originAgenda->title }}</a> — isian sudah diisi otomatis, periksa kembali sebelum menyimpan.</div>
            @endif

            <div class="grid gap-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <x-input-label for="tanggal_lead" value="Tanggal Lead *" />
                    <x-text-input id="tanggal_lead" name="tanggal_lead" type="date" class="mt-1 block w-full" required value="{{ old('tanggal_lead', $prefill['tanggal_lead'] ?? $lead?->tanggal_lead?->format('Y-m-d') ?? today()->toDateString()) }}" />
                    <x-input-error :messages="$errors->get('tanggal_lead')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status_lead" value="Status Lead *" />
                    <select id="status_lead" name="status_lead" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status_lead', $prefill['status_lead'] ?? $lead?->status_lead ?? 'No Respon') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status_lead')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sumber_lead" value="Sumber Lead *" />
                    <select id="sumber_lead" name="sumber_lead" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Pilih sumber</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source }}" @selected(old('sumber_lead', $prefill['sumber_lead'] ?? $lead?->sumber_lead) === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('sumber_lead')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="kanal_masuk" value="Kanal Masuk *" />
                    <select id="kanal_masuk" name="kanal_masuk" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Pilih kanal</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel }}" @selected(old('kanal_masuk', $prefill['kanal_masuk'] ?? $lead?->kanal_masuk) === $channel)>{{ $channel }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('kanal_masuk')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="aktivitas_lead" value="Aktivitas Lead *" />
                    <select id="aktivitas_lead" name="aktivitas_lead" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Pilih aktivitas</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity }}" @selected(old('aktivitas_lead', $prefill['aktivitas_lead'] ?? $lead?->aktivitas_lead) === $activity)>{{ $activity }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('aktivitas_lead')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sales_pic_id" value="Sales PIC *" />
                    <select id="sales_pic_id" name="sales_pic_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach ($salesUsers as $salesUser)
                            <option value="{{ $salesUser->id }}" @selected((string) old('sales_pic_id', $prefill['sales_pic_id'] ?? $lead?->sales_pic_id ?? auth()->id()) === (string) $salesUser->id)>{{ $salesUser->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('sales_pic_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="nama_konsumen" value="Nama Konsumen *" />
                    <x-text-input id="nama_konsumen" name="nama_konsumen" type="text" class="mt-1 block w-full" required value="{{ old('nama_konsumen', $lead?->nama_konsumen) }}" />
                    <x-input-error :messages="$errors->get('nama_konsumen')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="no_hp" value="No. HP *" />
                    <x-text-input id="no_hp" name="no_hp" type="text" class="mt-1 block w-full" required value="{{ old('no_hp', $lead?->no_hp) }}" />
                    <x-input-error :messages="$errors->get('no_hp')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="project_id" value="Proyek" />
                    <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Belum ditentukan</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) old('project_id', $lead?->project_id) === (string) $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="id_promo" value="ID Promo" />
                    <x-text-input id="id_promo" name="id_promo" type="text" class="mt-1 block w-full" value="{{ old('id_promo', $lead?->id_promo) }}" />
                </div>
                <div>
                    <x-input-label for="nama_promo" value="Nama Promo" />
                    <x-text-input id="nama_promo" name="nama_promo" type="text" class="mt-1 block w-full" value="{{ old('nama_promo', $lead?->nama_promo) }}" />
                </div>
                <div class="md:col-span-2">
                    <x-input-label for="keterangan" value="Keterangan" />
                    <textarea id="keterangan" name="keterangan" rows="4" class="mt-1 block w-full rounded-md border-gray-300">{{ old('keterangan', $prefill['keterangan'] ?? $lead?->keterangan) }}</textarea>
                    <x-input-error :messages="$errors->get('keterangan')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ $lead ? route('leads.show', $lead) : route('leads.index') }}" class="rounded-md px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Batal</a>
                <x-primary-button>{{ $lead ? 'Perbarui lead' : 'Simpan lead' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
