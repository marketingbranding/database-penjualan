<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-[24px] font-semibold leading-7 tracking-[-0.5px] text-ink">Spatial Workspace <span class="sr-only">Spatial workspace</span></h1>
            <p class="workspace-page-description mt-0.5 text-xs text-muted">Atur cabang, tim, dan akses dalam scene organisasi 3D.</p>
        </div>
    </x-slot>

    <div class="flex h-full min-h-0 flex-col">
        @php($hasTeams = collect($workspace['branches'])->sum(fn (array $branch): int => count($branch['teams'])) > 0)
        @php($assignedUserIds = collect($workspace['branches'])->flatMap(fn (array $branch) => collect($branch['teams'])->flatMap(fn (array $team) => $team['userIds'] ?? []))->map(fn ($id): int => (int) $id)->all())
        @php($unassignedUsers = collect($workspace['users'])->reject(fn (array $user): bool => in_array((int) $user['id'], $assignedUserIds, true))->values())
        @php($unassignedCount = $unassignedUsers->count())
        @if (count($workspace['branches']) === 0 || ! $hasTeams)
            <x-ui.card title="Belum ada struktur organisasi" description="Tambahkan cabang atau tim melalui halaman Pengaturan yang sudah tersedia." padding="p-6" class="m-4">
                <div class="grid min-h-64 place-items-center rounded-3xl border border-dashed border-line bg-canvas px-6 py-10 text-center">
                    <div class="max-w-md">
                        <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-lime-soft text-lime-dark"><svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 20h18M5 17V7l7-4 7 4v10M8 20v-5h8v5" /></svg></div>
                        <p class="mt-4 text-sm leading-6 text-muted">Workspace akan menampilkan cabang, tim, dan user setelah data organisasi tersedia.</p>
                    </div>
                </div>
            </x-ui.card>
        @else
            <section data-spatial-workspace class="spatial-workspace-shell relative min-h-0 flex-1 overflow-hidden rounded-[28px]">
                <div class="spatial-scene-stage absolute inset-0">
                    <div data-spatial-loading class="absolute inset-0 z-10 grid place-items-center text-xs text-muted"><span class="grid justify-items-center gap-2"><x-brand.logo variant="mark" size="md" class="spatial-loading-mark" /><span>Menyiapkan peta organisasi...</span></span></div>
                    <canvas data-spatial-canvas data-spatial-svg class="spatial-scene-canvas h-full w-full" role="img" tabindex="0" aria-keyshortcuts="Q E" aria-label="Peta 3D organisasi cabang dan tim"></canvas>
                    <div data-spatial-mobile class="hidden"></div>

                    <div data-spatial-toolbar class="spatial-hud spatial-mode-switch absolute left-5 top-[18px] z-10 flex items-center gap-1.5 rounded-[18px] bg-surface/90 p-1.5 backdrop-blur-sm">
                        @can('users.assign-roles')
                            <button type="button" data-spatial-edit-toggle aria-pressed="false" class="spatial-mode-button">Mode edit</button>
                        @endcan
                        <button type="button" data-spatial-view-toggle aria-pressed="true" class="spatial-mode-button is-active">Mode lihat</button>
                    </div>

                    <div data-spatial-legend class="spatial-hud spatial-legend absolute left-5 top-[78px] z-10 w-[275px] rounded-[18px] bg-surface/90 p-3.5 backdrop-blur-sm">
                        <button type="button" data-spatial-legend-toggle aria-expanded="false" aria-controls="spatial-legend-content" class="flex w-full items-center justify-between text-left"><span><span class="block text-xs font-semibold text-ink">Peta organisasi</span><span class="mt-0.5 block text-[10px] text-muted">Cabang, tim, dan role dalam scene 3D</span></span><span class="text-[10px] font-semibold text-lime-dark">Legenda</span></button>
                        <div id="spatial-legend-content" data-spatial-legend-content class="spatial-legend-content mt-2.5 flex flex-wrap gap-1.5">
                            <span class="spatial-legend-key"><i class="bg-orange"></i>Cabang</span><span class="spatial-legend-key"><i class="bg-lime-dark"></i>Tim</span><span class="spatial-legend-key"><i class="bg-ink"></i>User</span>
                            <div data-spatial-role-legend class="contents"></div>
                        </div>
                    </div>

                    <div class="spatial-scene-tools absolute left-5 top-1/2 z-10 flex -translate-y-1/2 flex-col gap-2 rounded-[18px] bg-surface/90 p-1.5 backdrop-blur-sm" aria-label="Alat scene">
                        <button type="button" class="spatial-tool-button is-active" aria-label="Pilih"><svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.5 11V5.5a1.5 1.5 0 0 1 3 0V11m0-2.5V4a1.5 1.5 0 0 1 3 0v4.5m0 0V5.5a1.5 1.5 0 0 1 3 0v6m0 0v-1a1.5 1.5 0 0 1 3 0V13c0 4.7-2.8 7.5-7.5 7.5h-1.2c-2.4 0-3.7-1.3-4.8-3L4.3 15a1.5 1.5 0 0 1 2.6-1.5l1.6 2.1V11Z" /></svg></button>
                        <button type="button" class="spatial-tool-button" aria-label="Geser kanvas"><svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 11V5a1.5 1.5 0 0 1 3 0v5m0-2V3.5a1.5 1.5 0 0 1 3 0V10m0-2.5a1.5 1.5 0 0 1 3 0v5m0-2a1.5 1.5 0 0 1 3 0v3.5c0 4.4-2.6 7-7 7h-1.5c-2.2 0-3.5-1.1-4.7-2.8L4 15a1.5 1.5 0 0 1 2.6-1.5L8 15" /></svg></button>
                        <button type="button" class="spatial-tool-button" aria-label="Hubungkan" title="Pilih user untuk mengatur atasan"><svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.5 14.5 14.5 9.5m-7.2 8.2-1-1a3 3 0 0 1 0-4.2l2.2-2.2a3 3 0 0 1 4.2 0m1-1 1-1a3 3 0 0 1 4.2 0l1 1a3 3 0 0 1 0 4.2l-2.2 2.2a3 3 0 0 1-4.2 0" /></svg></button>
                        @can('users.assign-roles')
                            <button type="button" data-spatial-undo class="spatial-tool-button hidden" aria-label="Undo">↶</button>
                        @endcan
                    </div>

                    @if ($unassignedCount > 0)
                        @php($unassignedRoles = $unassignedUsers->flatMap(fn ($user) => array_merge($user['globalRoles'] ?? [], array_map(fn ($role) => $role['name'], $user['roles'] ?? [])))->unique()->values())
                        <div data-spatial-unassigned-card class="spatial-unassigned-card spatial-hud absolute bottom-[18px] left-5 z-10 w-full max-w-[330px] rounded-[18px] bg-surface/90 p-3.5 backdrop-blur-sm">
                            <button type="button" data-spatial-unassigned-toggle aria-expanded="false" class="flex w-full items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-ink">Belum ditugaskan</span>
                                <span data-spatial-unassigned-count class="rounded-full bg-lime-soft px-2 py-1 text-[10px] font-semibold text-lime-dark">{{ $unassignedCount }} user</span>
                            </button>
                            <div data-spatial-unassigned-body class="hidden">
                                <div class="mt-2.5 flex gap-1.5">
                                    <input type="search" data-spatial-unassigned-search placeholder="Cari nama…" aria-label="Cari user belum ditugaskan" class="min-w-0 flex-1 rounded-lg border border-line bg-surface px-2 py-1.5 text-[11px] text-ink" />
                                </div>
                                @if ($unassignedRoles->isNotEmpty())
                                    <div data-spatial-unassigned-roles class="mt-1.5 flex flex-wrap gap-1">
                                        <button type="button" data-role-filter="" class="rounded-full bg-lime px-2 py-0.5 text-[10px] font-semibold" aria-pressed="true">Semua</button>
                                        @foreach ($unassignedRoles as $role)
                                            <button type="button" data-role-filter="{{ $role }}" class="rounded-full border border-line px-2 py-0.5 text-[10px]" aria-pressed="false">{{ $role }}</button>
                                        @endforeach
                                    </div>
                                @endif
                                <div data-spatial-unassigned-list class="spatial-unassigned-list mt-2.5 max-h-[320px] overflow-y-auto" role="list">
                                    @foreach ($unassignedUsers as $unassignedUser)
                                        @php($userRoles = implode(',', array_merge($unassignedUser['globalRoles'] ?? [], array_map(fn ($role) => $role['name'], $unassignedUser['roles'] ?? []))))
                                        <div class="spatial-unassigned-person" role="listitem" data-user-id="{{ $unassignedUser['id'] }}" data-user-name="{{ strtolower($unassignedUser['name']) }}" data-user-roles="{{ $userRoles }}">
                                            <button type="button" data-spatial-unassigned-focus class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                                <span class="spatial-unassigned-avatar">{{ str($unassignedUser['name'])->substr(0, 1)->upper() }}</span>
                                                <span class="truncate">{{ $unassignedUser['name'] }}</span>
                                            </button>
                                            <button type="button" data-spatial-unassigned-place class="shrink-0 rounded-lg border border-line px-1.5 py-1 text-[10px] font-semibold" title="Pilih user untuk ditempatkan via panel inspector">Tempatkan ke tim…</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div data-spatial-unassigned-preview class="spatial-unassigned-list mt-2.5">
                                @foreach ($unassignedUsers->take(3) as $unassignedUser)
                                    <div class="spatial-unassigned-person"><span class="spatial-unassigned-avatar">{{ str($unassignedUser['name'])->substr(0, 1)->upper() }}</span><span class="truncate">{{ $unassignedUser['name'] }}</span></div>
                                @endforeach
                                @if ($unassignedCount > 3)<span class="spatial-unassigned-more">+{{ $unassignedCount - 3 }}</span>@endif
                            </div>
                        </div>
                    @endif

                    @can('users.assign-roles')
                        <span data-spatial-pending class="spatial-pending-pill absolute right-[344px] top-[18px] z-10 hidden items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-[11px] font-semibold" aria-live="polite"><i class="size-1.5 rounded-full bg-orange"></i>0 perubahan</span>
                    @endcan
                    <div class="spatial-camera-hud spatial-hud absolute bottom-[18px] right-[344px] z-10 flex items-center gap-1.5 rounded-[18px] bg-surface/90 p-1.5 backdrop-blur-sm" aria-label="Kontrol kamera">
                        <button type="button" data-spatial-zoom-out class="spatial-camera-button" aria-label="Perkecil tampilan">−</button>
                        <span data-spatial-zoom-value class="min-w-10 text-center text-[11px] font-semibold text-ink">100%</span>
                        <button type="button" data-spatial-zoom-in class="spatial-camera-button" aria-label="Perbesar tampilan">+</button>
                        <button type="button" data-spatial-reset class="spatial-camera-button px-2" aria-label="Reset tampilan">Reset</button>
                        <button type="button" data-spatial-rotate-right class="spatial-camera-button" aria-label="Putar kamera 90 derajat" title="Putar kanan (E)">↷</button>
                    </div>

                    <aside data-spatial-panel class="spatial-inspector absolute right-[18px] top-[18px] bottom-[18px] z-20 w-[308px] rounded-3xl bg-surface p-5" aria-label="Detail user" aria-hidden="true">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-medium text-muted">Detail pilihan</p><h2 data-spatial-panel-name class="mt-1 text-base font-semibold text-ink">Scene organisasi</h2></div><button type="button" data-spatial-panel-close class="grid size-7 place-items-center rounded-lg text-muted hover:bg-canvas" aria-label="Tutup detail">×</button></div>
                        <div data-spatial-panel-body class="mt-4 min-h-0 overflow-y-auto thin-scrollbar pb-20"><p class="text-xs leading-5 text-muted">Pilih pion user untuk melihat detail, role, dan struktur pelaporannya.</p></div>
                        @can('users.assign-roles')
                            <div class="spatial-inspector-actions absolute inset-x-5 bottom-5 flex gap-2"><button type="button" data-spatial-cancel class="hidden flex-1 rounded-xl border border-line bg-surface px-3 py-2.5 text-[11px] font-semibold text-ink">Batalkan</button><button type="button" data-spatial-save class="hidden flex-1 rounded-xl bg-lime px-3 py-2.5 text-[11px] font-semibold text-on-accent hover:bg-lime-dark hover:text-white">Simpan perubahan</button></div>
                        @endcan
                        <p data-spatial-status class="absolute inset-x-5 bottom-1 text-[10px] text-muted" role="status" aria-live="polite"></p>
                    </aside>
                </div>
            </section>
        @endif
    </div>

    <script>
        window.spatialWorkspace = {{ Illuminate\Support\Js::from($workspace) }};
        window.spatialWorkspaceCanEdit = @json(Auth::user()->can('users.assign-roles'));
        window.spatialWorkspaceCanAccessAllBranches = @json(Auth::user()->canAccessAllBranches());
        window.spatialWorkspaceUpdateUrl = @json(route('admin.spatial-workspace.users.update', ['user' => '__USER__']));
        window.spatialWorkspaceGlobalRoleOrder = @json(array_keys(\App\Support\Rbac::GLOBAL_ROLES));
        window.spatialWorkspaceTeamRoleOrder = @json(array_keys(\App\Support\Rbac::TEAM_ROLES));
    </script>
</x-app-layout>
