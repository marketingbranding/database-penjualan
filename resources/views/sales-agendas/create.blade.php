<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-amber-700">Buku Saku Sales</p>
            <h1 class="mt-1 text-xl font-bold">{{ $agenda ? 'Edit Agenda' : 'Agenda Baru' }}</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <form method="POST" enctype="multipart/form-data" action="{{ $agenda ? route('sales-agendas.update', $agenda) : route('sales-agendas.store') }}" class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @csrf
            @if ($agenda)
                @method('PUT')
            @endif

            <div class="grid gap-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2">
                <div>
                    <x-input-label for="agenda_date" value="Tanggal Agenda *" />
                    <x-text-input id="agenda_date" name="agenda_date" type="date" class="mt-1 block w-full" required value="{{ old('agenda_date', $agenda?->agenda_date?->format('Y-m-d') ?? today()->toDateString()) }}" />
                    <x-input-error :messages="$errors->get('agenda_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="activity_category" value="Kategori Aktivitas *" />
                    <select id="activity_category" name="activity_category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
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
                    <select id="sales_case_id" name="sales_case_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Belum terhubung</option>
                        @foreach ($salesCases as $salesCase)
                            <option value="{{ $salesCase->id }}" @selected((string) old('sales_case_id', $agenda?->sales_case_id) === (string) $salesCase->id)>{{ $salesCase->consumer->nama_konsumen }} · {{ $salesCase->lot->project->name }}-{{ $salesCase->lot->code }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('sales_case_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="lead_id" value="Lead (opsional)" />
                    <select id="lead_id" name="lead_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Belum terhubung</option>
                        @foreach ($leads as $leadOption)
                            <option value="{{ $leadOption->id }}" @selected((string) old('lead_id', $agenda?->lead_id) === (string) $leadOption->id)>{{ $leadOption->id_lead }} · {{ $leadOption->nama_konsumen }} · {{ $leadOption->salesPic->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lead_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="evidence" value="Bukti Foto" />
                    <input id="evidence" name="evidence[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-amber-100 file:px-3 file:py-1 file:font-semibold file:text-amber-900" />
                    <p class="mt-1 text-xs text-gray-500">Opsional. Maksimal 2 foto JPEG, PNG, atau WebP. Maksimal 10 MB per foto.</p>
                    <x-input-error :messages="$errors->get('evidence')" class="mt-2" />
                    <x-input-error :messages="$errors->get('evidence.*')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="activity_result" value="Hasil Aktivitas" />
                    <textarea id="activity_result" name="activity_result" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old('activity_result', $agenda?->activity_result) }}</textarea>
                    <x-input-error :messages="$errors->get('activity_result')" class="mt-2" />
                </div>
            </div>

            @if ($agenda?->evidence?->isNotEmpty())
                <div class="mt-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-gray-900">Bukti foto tersimpan</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($agenda->evidence as $evidence)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($evidence->file_path) }}" target="_blank" class="text-sm font-semibold text-amber-700 hover:text-amber-900">{{ $evidence->original_name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('sales-agendas.index') }}" class="rounded-md px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Batal</a>
                <x-primary-button>{{ $agenda ? 'Perbarui agenda' : 'Simpan agenda' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
