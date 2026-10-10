@php
    $link = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
    $activeLink = 'bg-marison-terracotta-600 text-white shadow-sm';
    $inactiveLink = 'text-stone-300 hover:bg-marison-forest-900 hover:text-white';
@endphp

<div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-950/60 lg:hidden" @click="sidebarOpen = false"></div>

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-marison-forest-950 text-white shadow-2xl transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
            <span class="grid size-10 shrink-0 place-items-center bg-marison-gold-400"><span class="size-5 rotate-45 bg-marison-forest-950"></span></span>
            <span class="min-w-0"><span class="block truncate text-sm font-bold uppercase tracking-wider">Marison</span><span class="block truncate text-xs text-stone-400">Work OS</span></span>
        </a>
        <button type="button" class="rounded-lg p-2 text-stone-400 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false" aria-label="Tutup menu">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-4 py-5">
        <div class="grid gap-1">
            @can('dashboard.view')
                <a href="{{ route('dashboard') }}" class="{{ $link }} {{ request()->routeIs('dashboard') ? $activeLink : $inactiveLink }}">Dashboard</a>
            @endcan
        </div>

        @can('consumers.view')
            <div class="my-5 border-t border-white/10"></div>
            <a href="{{ route('consumers.index') }}" class="{{ $link }} {{ request()->routeIs('consumers.*') ? $activeLink : $inactiveLink }}">Data Konsumen</a>
        @endcan

        @canany(['sales.view-own', 'sales.view-team', 'sales.view-all'])
            <div class="my-5 border-t border-white/10"></div>
            <a href="{{ route('sales-agendas.index') }}" class="{{ $link }} {{ request()->routeIs('sales-agendas.*') ? $activeLink : $inactiveLink }}">Agenda Sales</a>
        @endcanany

        @canany(['leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
            <a href="{{ route('leads.index') }}" class="{{ $link }} {{ request()->routeIs('leads.*') && ! request()->routeIs('leads.monitoring') ? $activeLink : $inactiveLink }}">Leads</a>
            @canany(['leads.monitor', 'leads.view-team', 'leads.view-all'])
                <a href="{{ route('leads.monitoring') }}" class="{{ $link }} {{ request()->routeIs('leads.monitoring') ? $activeLink : $inactiveLink }}">Monitoring Leads</a>
            @endcanany
            @canany(['sales-fee-reports.view', 'leads.monitor', 'leads.view-team', 'leads.view-all'])
                <a href="{{ route('sales-fee-reports.index') }}" class="{{ $link }} {{ request()->routeIs('sales-fee-reports.*') ? $activeLink : $inactiveLink }}">Laporan Performa Sales</a>
            @endcanany
        @endcanany

        @can('sales-process.view')
            <div class="my-5 border-t border-white/10"></div>
            <div x-data="{ open: {{ request()->routeIs('sales-process.*') ? 'true' : 'false' }} }">
                <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white" @click="open = ! open">
                    <span>Proses Penjualan</span>
                    <svg class="size-4 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" /></svg>
                </button>
                <div x-show="open" class="mt-1 grid gap-1 pl-3">
                    <a href="{{ route('sales-process.psjb-utj') }}" class="{{ $link }} {{ request()->routeIs('sales-process.psjb-utj') ? $activeLink : $inactiveLink }}">PSJB / UTJ</a>
                    <a href="{{ route('sales-process.bi-checking') }}" class="{{ $link }} {{ request()->routeIs('sales-process.bi-checking') ? $activeLink : $inactiveLink }}">BI Checking</a>
                     @can('filing.view')<a href="{{ route('sales-process.pemberkasan') }}" class="{{ $link }} {{ request()->routeIs('sales-process.pemberkasan') ? $activeLink : $inactiveLink }}">Pemberkasan</a>@endcan
                     @can('bank-process.view')<a href="{{ route('sales-process.bank') }}" class="{{ $link }} {{ request()->routeIs('sales-process.bank') ? $activeLink : $inactiveLink }}">Proses Bank</a>@endcan
                     @can('ppjb.view')<a href="{{ route('sales-process.ppjb-developer') }}" class="{{ $link }} {{ request()->routeIs('sales-process.ppjb-developer') ? $activeLink : $inactiveLink }}">PPJB Developer</a>@endcan
                     @can('akad.view')<a href="{{ route('sales-process.akad-bast') }}" class="{{ $link }} {{ request()->routeIs('sales-process.akad-bast') ? $activeLink : $inactiveLink }}">Akad & BAST</a>@endcan
                </div>
            </div>
        @endcan

        @can('warranties.view')
            <div class="my-5 border-t border-white/10"></div>
            <a href="{{ route('warranties.index') }}" class="{{ $link }} {{ request()->routeIs('warranties.*') ? $activeLink : $inactiveLink }}">Garansi</a>
        @endcan

        @canany(['branches.view', 'projects.view', 'lots.view', 'audit-logs.view', 'banks.view', 'lead-time.view', 'promotions.view', 'users.view', 'teams.view', 'roles.view'])
            <div class="my-5 border-t border-white/10"></div>
            <div x-data="{ open: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }">
                <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white" @click="open = ! open">
                    <span>Pengaturan</span>
                    <svg class="size-4 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" /></svg>
                </button>
                <div x-show="open" class="mt-1 grid gap-1 pl-3">
                    @can('spatial-workspace.view')<a href="{{ route('admin.spatial-workspace') }}" class="{{ $link }} {{ request()->routeIs('admin.spatial-workspace') ? $activeLink : $inactiveLink }}">Spatial Workspace</a>@endcan
                    @can('branches.view')<a href="{{ route('admin.branches.index') }}" class="{{ $link }} {{ request()->routeIs('admin.branches.*') ? $activeLink : $inactiveLink }}">Cabang</a>@endcan
                    @can('projects.view')<a href="{{ route('admin.projects.index') }}" class="{{ $link }} {{ request()->routeIs('admin.projects.*') ? $activeLink : $inactiveLink }}">Proyek</a>@endcan
                    @can('lots.view')<a href="{{ route('admin.lots.index') }}" class="{{ $link }} {{ request()->routeIs('admin.lots.*') ? $activeLink : $inactiveLink }}">Kavling</a>@endcan
                    @can('audit-logs.view')<a href="{{ route('admin.audit-logs.index') }}" class="{{ $link }} {{ request()->routeIs('admin.audit-logs.*') ? $activeLink : $inactiveLink }}">Audit Log</a>@endcan
                    @can('banks.view')<a href="{{ route('admin.banks.index') }}" class="{{ $link }} {{ request()->routeIs('admin.banks.*') ? $activeLink : $inactiveLink }}">Bank</a>@endcan
                    @can('lead-time.view')<a href="{{ route('admin.lead-times.index') }}" class="{{ $link }} {{ request()->routeIs('admin.lead-times.*') ? $activeLink : $inactiveLink }}">Target Lead Time</a>@endcan
                    @can('promotions.view')<a href="{{ route('admin.promotions.index') }}" class="{{ $link }} {{ request()->routeIs('admin.promotions.*') ? $activeLink : $inactiveLink }}">Promo</a>@endcan
                    @can('users.view')<a href="{{ route('admin.users.index') }}" class="{{ $link }} {{ request()->routeIs('admin.users.*') ? $activeLink : $inactiveLink }}">Pengguna</a>@endcan
                    @can('teams.view')<a href="{{ route('admin.teams.index') }}" class="{{ $link }} {{ request()->routeIs('admin.teams.*') ? $activeLink : $inactiveLink }}">Tim</a>@endcan
                    @can('roles.view')<a href="{{ route('admin.roles.index') }}" class="{{ $link }} {{ request()->routeIs('admin.roles.*') ? $activeLink : $inactiveLink }}">Role & Permission</a>@endcan
                </div>
            </div>
        @endcanany
    </nav>

    <div class="border-t border-white/10 p-4">
        @if ($availableTeams->isNotEmpty())
            <div class="mb-4" x-data="{ teamsOpen: false }">
                <button type="button" class="flex w-full items-center justify-between rounded-lg bg-white/5 px-3 py-2 text-left" @click="teamsOpen = ! teamsOpen">
                    <span class="min-w-0"><span class="block text-[10px] font-bold uppercase tracking-widest text-stone-500">Tim aktif</span><span class="block truncate text-sm font-semibold text-stone-200">{{ $activeTeam?->name ?? 'Pilih tim' }}</span></span>
                    <svg class="size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" /></svg>
                </button>
                <div x-show="teamsOpen" class="mt-2 grid gap-1">
                    @foreach ($availableTeams as $team)
                        <form method="POST" action="{{ route('team-context.update', $team) }}">@csrf<button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm {{ $activeTeam?->is($team) ? 'bg-marison-gold-400 font-semibold text-marison-forest-950' : 'text-stone-300 hover:bg-white/10' }}">{{ $team->name }}</button></form>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3 px-2">
            <div class="grid size-9 shrink-0 place-items-center rounded-full bg-marison-gold-400 font-bold text-marison-forest-950">{{ str(Auth::user()->name)->substr(0, 1)->upper() }}</div>
            <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold">{{ Auth::user()->name }}</div><a href="{{ route('profile.edit') }}" class="text-xs text-gray-400 hover:text-white">Profil</a></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-md p-2 text-gray-400 hover:bg-white/10 hover:text-white" title="Keluar"><svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" /></svg></button></form>
        </div>
    </div>
</aside>
