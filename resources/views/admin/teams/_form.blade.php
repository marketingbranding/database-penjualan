<div class="grid gap-6">
    <div>
        <x-input-label for="name" value="Nama tim" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $team?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label value="Kode" />
        <p class="workspace-control mt-1 bg-canvas px-3 py-2 text-sm text-muted">{{ $team?->code ?? 'Dibuat otomatis saat disimpan' }}</p>
    </div>

    <div>
        <x-input-label for="branch_id" value="Cabang" />
        <select id="branch_id" name="branch_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
            <option value="">Tanpa cabang</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $team?->branch_id) === (string) $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
    </div>

    <div>
        <x-input-label for="project_id" value="Proyek" />
        <select id="project_id" name="project_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
            <option value="">Tanpa proyek</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((string) old('project_id', $team?->project_id) === (string) $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-muted">Tim koordinator aktif wajib memilih proyek.</p>
        <x-input-error class="mt-2" :messages="$errors->get('project_id')" />
    </div>

    <div>
        <x-input-label for="parent_id" value="Induk tim" />
        <select id="parent_id" name="parent_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
            <option value="">Tanpa induk</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $team?->parent_id) === (string) $parent->id)>
                    {{ $parent->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('parent_id')" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="supervisor_id" value="SPV" />
            <select id="supervisor_id" name="supervisor_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                <option value="">Tanpa SPV</option>
                @foreach ($supervisors as $user)
                    <option value="{{ $user->id }}" @selected((string) old('supervisor_id', $team?->supervisor_id) === (string) $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('supervisor_id')" />
        </div>

        <div>
            <x-input-label for="coordinator_id" value="Koordinator Sales" />
            @if ($team)
                <select id="coordinator_id" name="coordinator_id" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                    <option value="">Tanpa koordinator</option>
                    @foreach ($coordinators as $user)
                        <option value="{{ $user->id }}" @selected((string) old('coordinator_id', $team?->coordinator_id) === (string) $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">Koordinator harus menjadi anggota tim dan memiliki role Koordinator Sales pada tim ini.</p>
            @else
                <p class="workspace-control mt-1 bg-canvas px-3 py-2 text-sm text-muted">Tetapkan setelah tim dibuat dan role tim tersedia.</p>
            @endif
            <x-input-error class="mt-2" :messages="$errors->get('coordinator_id')" />
        </div>
    </div>

    <label class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-line text-lime-dark" @checked(old('is_active', $team?->is_active ?? true))>
        <span class="text-sm text-ink">Tim aktif</span>
    </label>
</div>
