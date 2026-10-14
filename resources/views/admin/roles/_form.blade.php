<div class="grid gap-6">
    <div><x-input-label for="name" value="Nama role"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $role?->name)" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
    @if(!$role)<div><x-input-label for="team_id" value="Scope"/><select id="team_id" name="team_id" class="workspace-control mt-1 block w-full py-2.5 text-sm"><option value="">Global</option>@foreach($teams as $team)<option value="{{ $team->id }}" @selected((string) old('team_id') === (string) $team->id)>{{ $team->name }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('team_id')"/></div>@else<div class="rounded-2xl bg-canvas p-4 text-sm text-muted">Scope: <strong class="text-ink">{{ $role->team?->name ?? 'Global' }}</strong></div>@endif
    @php($selectedPermissions = old('permissions', $role?->permissions->pluck('name')->all() ?? []))
    <div>
        <x-input-label value="Hak akses"/>
        <p class="mt-1 text-sm text-muted">Pilih kemampuan yang boleh digunakan oleh role ini. Nama teknis permission disembunyikan.</p>
        <div class="mt-4 space-y-4">
            @foreach($permissionGroups as $group)
                <section class="rounded-2xl border border-line bg-surface p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold text-ink">{{ $group['label'] }}</h3>
                        <span class="text-xs text-muted">{{ count($group['permissions']) }} hak akses</span>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($group['permissions'] as $permission)
                            <label class="flex min-h-12 items-center gap-3 rounded-2xl border border-line bg-canvas px-3 py-2 transition hover:border-lime-dark">
                                <input type="checkbox" name="permissions[]" value="{{ $permission['name'] }}" class="size-4 shrink-0 rounded border-line text-lime-dark" @checked(in_array($permission['name'], $selectedPermissions, true))>
                                <span class="text-sm font-medium text-ink">{{ $permission['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('permissions')"/>
    </div>
</div>
