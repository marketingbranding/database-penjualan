<div class="grid gap-6">
    <div>
        <x-input-label value="Kode cabang" />
        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ $branch?->code ?? 'Dibuat otomatis saat disimpan' }}</p>
    </div>
    <div>
        <x-input-label for="name" value="Nama cabang" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $branch?->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>
    <label class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" @checked(old('is_active', $branch?->is_active ?? true))>
        <span class="text-sm text-gray-700">Cabang aktif</span>
    </label>
</div>
