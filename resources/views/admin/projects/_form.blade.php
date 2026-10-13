<div class="grid gap-6">
    <div><x-input-label for="branch_id" value="Cabang"/><select id="branch_id" name="branch_id" class="workspace-control mt-1 block w-full py-2.5 text-sm" required><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $project?->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('branch_id')"/></div>
    <div><x-input-label value="Kode proyek"/><p class="workspace-readonly-control">{{ $project?->code ?? 'Dibuat otomatis saat disimpan' }}</p></div>
    <div><x-input-label for="name" value="Nama proyek"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $project?->name)" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
    <label class="flex items-center gap-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="module-checkbox" @checked(old('is_active', $project?->is_active ?? true))><span class="text-sm text-ink">Proyek aktif</span></label>
</div>
