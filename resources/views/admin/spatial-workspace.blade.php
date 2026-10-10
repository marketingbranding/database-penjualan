<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-marison-terracotta-600">Organization Lab</p>
                <h1 class="mt-1 text-xl font-bold">Spatial Workspace</h1>
                <p class="mt-1 text-sm text-gray-500">Peta 3D struktur cabang, tim, dan assignment user.</p>
            </div>
            <div class="flex items-center gap-3">
                @can('users.assign-roles')
                    <button type="button" data-spatial-toggle-edit class="rounded-lg border border-marison-line bg-marison-paper px-3 py-2 text-xs font-bold uppercase tracking-wider text-marison-forest-950 hover:bg-marison-cream-50">Edit mode</button>
                @endcan
                <span data-spatial-mode class="rounded-full bg-marison-gold-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-marison-forest-950">View mode</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-[1800px] px-3 sm:px-5 lg:px-6">
            <section class="relative min-h-[calc(100vh-10rem)] overflow-hidden rounded-2xl border border-marison-line bg-marison-forest-950 shadow-xl">
                <div class="absolute inset-x-0 top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-marison-forest-950/80 px-5 py-4 text-white backdrop-blur-sm">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-marison-gold-400">Spatial map</p>
                        <p class="mt-1 text-sm text-stone-300">Klik Branch, Team, atau User. Drag canvas untuk memutar scene.</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-stone-300">
                        <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-marison-gold-400"></span>Cabang</span>
                        <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-marison-teal-600"></span>Tim</span>
                        <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-marison-terracotta-600"></span>User</span>
                    </div>
                </div>
                <div data-spatial-workspace class="relative min-h-[calc(100vh-10rem)] w-full touch-none">
                    <div data-spatial-loading class="absolute inset-0 grid place-items-center text-sm text-stone-300">Menyiapkan workspace 3D...</div>
                    <div data-spatial-error class="absolute inset-0 hidden place-items-center px-6 text-center text-sm text-stone-300"></div>
                    <div data-spatial-labels class="pointer-events-none absolute inset-0 z-[2] overflow-hidden"></div>
                </div>

                <aside class="absolute right-4 top-20 z-10 hidden w-72 rounded-2xl border border-white/15 bg-marison-paper/95 p-4 shadow-2xl backdrop-blur sm:right-6 sm:w-80" data-spatial-inspector>
                <div data-spatial-empty class="flex min-h-[20rem] flex-col items-center justify-center text-center">
                    <div class="grid size-14 place-items-center rounded-2xl bg-marison-cream-50 text-2xl text-marison-terracotta-600">⌘</div>
                    <h2 class="mt-4 font-bold text-marison-ink-950">Pilih objek</h2>
                    <p class="mt-2 max-w-xs text-sm leading-6 text-gray-500">Klik cabang, tim, atau user di dalam workspace untuk membuka ringkasan assignment.</p>
                </div>
                <div data-spatial-detail class="hidden">
                    <p data-spatial-kind class="text-xs font-bold uppercase tracking-widest text-marison-terracotta-600"></p>
                    <h2 data-spatial-title class="mt-1 text-xl font-bold text-marison-ink-950"></h2>
                    <p data-spatial-subtitle class="mt-1 text-sm text-gray-500"></p>
                    <div data-spatial-content class="mt-6 grid gap-4"></div>
                    @can('users.assign-roles')
                        <div data-spatial-actions class="mt-6 hidden border-t border-marison-line pt-5">
                            <p class="text-xs font-bold uppercase tracking-widest text-marison-terracotta-600">Edit assignment</p>
                            <label class="mt-4 block text-xs font-semibold text-gray-600">Team<select data-spatial-team class="mt-1 block w-full rounded-lg border-marison-line bg-marison-cream-50 text-sm"></select></label>
                            <label class="mt-3 block text-xs font-semibold text-gray-600">Atasan<select data-spatial-reports-to class="mt-1 block w-full rounded-lg border-marison-line bg-marison-cream-50 text-sm"></select></label>
                            <button type="button" data-spatial-save class="mt-4 w-full rounded-lg bg-marison-forest-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-marison-forest-900">Simpan assignment</button>
                            <p data-spatial-save-status class="mt-3 hidden text-xs"></p>
                        </div>
                    @endcan
                </div>
                </aside>
            </section>
        </div>
    </div>

    <script>
        window.spatialWorkspace = {{ Illuminate\Support\Js::from($workspace) }};
        window.spatialWorkspaceCanEdit = @json(auth()->user()->can('users.assign-roles'));
        window.spatialWorkspaceUpdateUrl = @json(route('admin.spatial-workspace.users.update', ['user' => '__USER__']));
    </script>
</x-app-layout>
