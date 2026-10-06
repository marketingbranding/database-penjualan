<div class="grid gap-6">
    <div>
        <x-input-label for="name" value="Nama tim" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $team?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="code" value="Kode" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase" :value="old('code', $team?->code)" required />
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>

    <div>
        <x-input-label for="parent_id" value="Induk tim" />
        <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Tanpa induk</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $team?->parent_id) === (string) $parent->id)>
                    {{ $parent->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('parent_id')" />
    </div>

    <label class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $team?->is_active ?? true))>
        <span class="text-sm text-gray-700">Tim aktif</span>
    </label>
</div>
