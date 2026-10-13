<div class="grid gap-6">
    <div>
        <x-input-label value="Kode cabang" />
        <p class="workspace-readonly-control">{{ $branch?->code ?? 'Dibuat otomatis saat disimpan' }}</p>
    </div>
    <div>
        <x-input-label for="name" value="Nama cabang" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $branch?->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>
    <label class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="module-checkbox" @checked(old('is_active', $branch?->is_active ?? true))>
        <span class="text-sm text-ink">Cabang aktif</span>
    </label>
</div>
