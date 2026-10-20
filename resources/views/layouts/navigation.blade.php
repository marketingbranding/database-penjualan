@php
    $panelLink = 'group flex h-[35px] min-h-0 items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-[13px] font-medium transition duration-150';
    $activePanelLink = 'bg-lime text-on-accent';
    $inactivePanelLink = 'text-rail-text/75 hover:bg-surface-2 hover:text-rail-text';
    $railLink = 'group relative grid size-[42px] place-items-center rounded-[14px] text-muted transition duration-150 hover:bg-surface-2 hover:text-lime';
    $activeRailLink = 'bg-surface-2 text-lime';
    $settingsRoute = Auth::user()->can('spatial-workspace.view')
        ? 'admin.spatial-workspace'
        : (Auth::user()->can('users.view')
            ? 'admin.users.index'
            : (Auth::user()->can('teams.view')
                ? 'admin.teams.index'
                : (Auth::user()->can('projects.view') ? 'admin.projects.index' : 'admin.audit-logs.index')));
@endphp

<div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-rail/60 lg:hidden" @click="sidebarOpen = false"></div>

<aside class="fixed inset-y-3.5 left-3.5 z-50 hidden w-[66px] flex-col items-center gap-2.5 rounded-3xl bg-rail py-4 text-rail-text lg:hidden" aria-hidden="true">
    <a href="{{ route('dashboard') }}" class="brand-rail-mark mb-2.5 grid size-10 place-items-center rounded-[13px] transition-transform duration-150 hover:scale-[1.06] motion-reduce:transition-none motion-reduce:hover:scale-100" aria-label="Oasis Next, buka dashboard" title="Oasis Next, buka dashboard">
        <x-brand.logo variant="mark" size="md" aria-hidden="true" class="size-10" />
    </a>

    <nav class="flex flex-col items-center gap-2.5">
        @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="{{ $railLink }} {{ request()->routeIs('dashboard') ? $activeRailLink : '' }}" aria-label="Dashboard" title="Dashboard">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z" /></svg>
            </a>
        @endcan
        @can('consumers.view')
            <a href="{{ route('consumers.index') }}" class="{{ $railLink }} {{ request()->routeIs('consumers.*') ? $activeRailLink : '' }}" aria-label="Data konsumen" title="Data konsumen">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.5a3.5 3.5 0 0 1 0 6.8M17 15a3.5 3.5 0 0 1 3 3.5V20" /></svg>
            </a>
        @endcan
        @canany(['sales.view-own', 'sales.view-team', 'sales.view-all'])
            <a href="{{ route('sales-agendas.index') }}" class="{{ $railLink }} {{ request()->routeIs('sales-agendas.*') ? $activeRailLink : '' }}" aria-label="Agenda sales" title="Agenda sales">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect width="16" height="16" x="4" y="5" rx="2" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M8 3v4m8-4v4M4 10h16m-9 4h.01M15 14h.01M9 17h.01M15 17h.01" /></svg>
            </a>
        @endcanany
        @canany(['leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
            <a href="{{ route('leads.index') }}" class="{{ $railLink }} {{ request()->routeIs('leads.index', 'leads.create', 'leads.show', 'leads.edit') ? $activeRailLink : '' }}" aria-label="Leads" title="Leads">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19V9m7 10V5m7 14v-7" /></svg>
            </a>
        @endcanany
    </nav>

    <div class="mt-auto flex flex-col items-center gap-2">
        @canany(['branches.view', 'projects.view', 'lots.view', 'audit-logs.view', 'banks.view', 'lead-time.view', 'promotions.view', 'users.view', 'teams.view', 'roles.view', 'sales-targets.manage'])
            <a href="{{ route($settingsRoute) }}" class="{{ $railLink }} {{ request()->routeIs('admin.*') ? $activeRailLink : '' }}" aria-label="Pengaturan" title="Pengaturan">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.75 3.5h4.5l.55 2.05a7.7 7.7 0 0 1 1.7.98l2.03-.65 2.25 3.9-1.48 1.5a7.7 7.7 0 0 1 0 1.96l1.48 1.5-2.25 3.9-2.03-.65a7.7 7.7 0 0 1-1.7.98l-.55 2.05h-4.5l-.55-2.05a7.7 7.7 0 0 1-1.7-.98l-2.03.65-2.25-3.9 1.48-1.5a7.7 7.7 0 0 1 0-1.96l-1.48-1.5 2.25-3.9 2.03.65a7.7 7.7 0 0 1 1.7-.98l.55-2.05Z" /><circle cx="12" cy="12" r="2.75" stroke-width="1.8" /></svg>
            </a>
        @endcanany
    </div>
</aside>

<aside class="workspace-sidebar fixed inset-y-3.5 left-3.5 z-50 flex w-[248px] -translate-x-full flex-col rounded-3xl bg-rail p-3.5 text-rail-text transition-transform duration-200 lg:translate-x-0" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Navigasi utama">
    <div class="flex items-center justify-between pb-3.5">
        <a href="{{ route('dashboard') }}" class="min-w-0">
            <span class="md:hidden">
                <x-brand.logo variant="lockup" size="sm" aria-hidden="true" />
            </span>
            <span class="hidden items-center gap-2 md:flex">
                <x-brand.logo variant="mark" size="sm" aria-hidden="true" class="size-8 shrink-0" />
                <span class="min-w-0">
                    <span class="block truncate text-[15px] font-bold leading-5 text-ink">Oasis Next</span>
                    <span class="block truncate text-[10px] leading-4 text-muted">Workspace operasional</span>
                </span>
            </span>
        </a>
        <button type="button" class="grid size-9 place-items-center rounded-xl text-muted transition hover:bg-canvas hover:text-ink lg:hidden" @click="sidebarOpen = false" aria-label="Tutup navigasi">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <nav class="workspace-nav-scroll min-h-0 flex-1 overflow-y-auto" aria-label="Menu workspace">
        <div class="grid gap-1">
            @can('dashboard.view')
                <a href="{{ route('dashboard') }}" class="{{ $panelLink }} {{ request()->routeIs('dashboard') ? $activePanelLink : $inactivePanelLink }}">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z" /></svg><span>Dashboard</span>
                </a>
            @endcan
            @can('consumers.view')
                <a href="{{ route('consumers.index') }}" class="{{ $panelLink }} {{ request()->routeIs('consumers.*') ? $activePanelLink : $inactivePanelLink }}">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m6-9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.5a3.5 3.5 0 0 1 0 6.8M17 15a3.5 3.5 0 0 1 3 3.5V20" /></svg><span>Data konsumen</span>
                </a>
            @endcan
        </div>

        @canany(['sales.view-own', 'sales.view-team', 'sales.view-all', 'leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
            <div class="my-3 border-t border-line"></div>
            <p class="mb-1.5 px-2.5 pt-3.5 text-[11px] font-medium text-muted">Buku Saku Sales</p>
            <div class="grid gap-1">
                @canany(['sales.view-own', 'sales.view-team', 'sales.view-all'])
                    <a href="{{ route('sales-agendas.index') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-agendas.*') ? $activePanelLink : $inactivePanelLink }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect width="16" height="16" x="4" y="5" rx="2" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M8 3v4m8-4v4M4 10h16" /></svg><span>Agenda sales</span>
                    </a>
                    <a href="{{ route('daily-plans.index') }}" class="{{ $panelLink }} {{ request()->routeIs('daily-plans.*') ? $activePanelLink : $inactivePanelLink }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 5h14v14H5zM8 9h8M8 12h8M8 15h5" /></svg><span>Buku Saku harian</span>
                    </a>
                @endcanany
                @canany(['leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
                    <a href="{{ route('leads.index') }}" class="{{ $panelLink }} {{ request()->routeIs('leads.index', 'leads.create', 'leads.show', 'leads.edit') ? $activePanelLink : $inactivePanelLink }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19V9m7 10V5m7 14v-7" /></svg><span class="min-w-0 flex-1">Leads</span>
                    </a>
                @endcanany
                @canany(['leads.monitor', 'leads.view-team', 'leads.view-all'])
                    <a href="{{ route('leads.monitoring') }}" class="{{ $panelLink }} {{ request()->routeIs('leads.monitoring') ? $activePanelLink : $inactivePanelLink }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M4 18h16M6 15V9m4 6V5m4 10v-3m4 3V7" /></svg><span>Monitoring leads</span>
                    </a>
                @endcanany
                @can('reports.team-performance')
                    <a href="{{ route('team-performance.index') }}" class="{{ $panelLink }} {{ request()->routeIs('team-performance.*') ? $activePanelLink : $inactivePanelLink }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-7m4 7V4" /></svg><span>Performa tim</span>
                    </a>
                @endcan
            </div>
        @endcanany

        @can('sales-process.view')
            <div class="my-3 border-t border-line"></div>
            <div x-data="{ open: {{ request()->routeIs('sales-process.*') ? 'true' : 'false' }} }">
                <button type="button" class="flex min-h-[35px] w-full items-center justify-between rounded-xl px-2.5 py-2 text-left text-[11px] font-medium text-muted transition hover:bg-canvas hover:text-ink" @click="open = ! open" :aria-expanded="open.toString()">
                    <span>Proses penjualan</span><svg class="size-3.5 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div x-show="open" x-transition.opacity class="mt-1 grid gap-1 pl-3">
                    <a href="{{ route('sales-process.psjb-utj') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.psjb-utj') ? $activePanelLink : $inactivePanelLink }}"><span>Alur kavling</span></a>
                    <a href="{{ route('sales-process.bi-checking') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.bi-checking') ? $activePanelLink : $inactivePanelLink }}"><span>BI Checking</span></a>
                    @can('filing.view')<a href="{{ route('sales-process.pemberkasan') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.pemberkasan') ? $activePanelLink : $inactivePanelLink }}"><span>Pemberkasan</span></a>@endcan
                    @can('bank-process.view')<a href="{{ route('sales-process.bank') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.bank') ? $activePanelLink : $inactivePanelLink }}"><span>Proses bank</span></a>@endcan
                    @can('ppjb.view')<a href="{{ route('sales-process.ppjb-developer') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.ppjb-developer') ? $activePanelLink : $inactivePanelLink }}"><span>PPJB developer</span></a>@endcan
                    @can('akad.view')<a href="{{ route('sales-process.akad-bast') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-process.akad-bast') ? $activePanelLink : $inactivePanelLink }}"><span>Akad dan BAST</span></a>@endcan
                </div>
            </div>
        @endcan

        @can('warranties.view')
            <a href="{{ route('warranties.index') }}" class="mt-3 {{ $panelLink }} {{ request()->routeIs('warranties.*') ? $activePanelLink : $inactivePanelLink }}">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m12 3 7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z" /><path stroke-linecap="round" stroke-width="1.8" d="m9 12 2 2 4-4" /></svg><span>Garansi</span>
            </a>
        @endcan

        @can('sales-fee-reports.view')
            <div class="my-3 border-t border-line"></div>
            <p class="mb-1.5 px-2.5 pt-3.5 text-[11px] font-medium text-muted">Laporan</p>
            <div class="grid gap-1">
                <a href="{{ route('sales-fee-reports.index') }}" class="{{ $panelLink }} {{ request()->routeIs('sales-fee-reports.*') ? $activePanelLink : $inactivePanelLink }}">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-7m4 7V4" /></svg><span>Laporan Fee Sales</span>
                </a>
            </div>
        @endcan

        @canany(['branches.view', 'projects.view', 'lots.view', 'audit-logs.view', 'banks.view', 'lead-time.view', 'promotions.view', 'users.view', 'teams.view', 'roles.view', 'sales-targets.manage'])
            <div class="my-3 border-t border-line"></div>
            <div x-data="{ open: {{ request()->routeIs('admin.*') ? 'true' : 'false' }} }">
                <button type="button" class="flex min-h-[35px] w-full items-center justify-between rounded-xl px-2.5 py-2 text-left text-[11px] font-medium text-muted transition hover:bg-canvas hover:text-ink" @click="open = ! open" :aria-expanded="open.toString()">
                    <span>Pengaturan</span><svg class="size-3.5 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div x-show="open" x-transition.opacity class="mt-1 grid gap-1 pl-3">
                    @can('spatial-workspace.view')<a href="{{ route('admin.spatial-workspace') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.spatial-workspace') ? $activePanelLink : $inactivePanelLink }}"><span>Spatial workspace</span></a>@endcan
                    @can('branches.view')<a href="{{ route('admin.branches.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.branches.*') ? $activePanelLink : $inactivePanelLink }}"><span>Cabang</span></a>@endcan
                    @can('projects.view')<a href="{{ route('admin.projects.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.projects.*') ? $activePanelLink : $inactivePanelLink }}"><span>Proyek</span></a>@endcan
                    @can('lots.view')<a href="{{ route('admin.lots.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.lots.*') ? $activePanelLink : $inactivePanelLink }}"><span>Kavling</span></a>@endcan
                    @can('audit-logs.view')<a href="{{ route('admin.audit-logs.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.audit-logs.*') ? $activePanelLink : $inactivePanelLink }}"><span>Audit log</span></a>@endcan
                    @can('banks.view')<a href="{{ route('admin.banks.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.banks.*') ? $activePanelLink : $inactivePanelLink }}"><span>Bank</span></a>@endcan
                    @can('lead-time.view')<a href="{{ route('admin.lead-times.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.lead-times.*') ? $activePanelLink : $inactivePanelLink }}"><span>Target lead time</span></a>@endcan
                    @can('sales-targets.manage')<a href="{{ route('admin.sales-lead-targets.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.sales-lead-targets.*') ? $activePanelLink : $inactivePanelLink }}"><span>Target Lead Sales</span></a>@endcan
                    @can('promotions.view')<a href="{{ route('admin.promotions.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.promotions.*') ? $activePanelLink : $inactivePanelLink }}"><span>Promo</span></a>@endcan
                    @can('users.view')<a href="{{ route('admin.users.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.users.*') ? $activePanelLink : $inactivePanelLink }}"><span>Pengguna</span></a>@endcan
                    @can('teams.view')<a href="{{ route('admin.teams.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.teams.*') ? $activePanelLink : $inactivePanelLink }}"><span>Tim</span></a>@endcan
                    @can('roles.view')<a href="{{ route('admin.roles.index') }}" class="{{ $panelLink }} {{ request()->routeIs('admin.roles.*') ? $activePanelLink : $inactivePanelLink }}"><span>Role dan permission</span></a>@endcan
                </div>
            </div>
        @endcanany
    </nav>

    <div class="mt-3 pt-3">
        @if ($availableTeams->isNotEmpty())
            <div class="mb-3" x-data="{ teamsOpen: false }">
                <button type="button" class="flex w-full items-center justify-between rounded-2xl bg-surface-2 px-3 py-2.5 text-left transition hover:bg-lime-soft" @click="teamsOpen = ! teamsOpen" :aria-expanded="teamsOpen.toString()">
                    <span class="min-w-0"><span class="block text-[10px] font-medium text-muted">{{ $isCoordinatorDashboard ? 'Cabang · Proyek' : 'Tim aktif' }}</span><span class="block truncate text-xs font-semibold text-ink">{{ $isCoordinatorDashboard ? (($activeTeam?->branch?->name ?? 'Cabang').' · '.($activeTeam?->project?->name ?? 'Tanpa proyek')) : ($activeTeam?->name ?? 'Pilih tim') }}</span></span>
                    <svg class="size-4 text-muted transition" :class="teamsOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div x-show="teamsOpen" x-transition.opacity class="mt-2 grid gap-1">
                    @foreach ($availableTeams as $team)
                        <form method="POST" action="{{ route('team-context.update', $team) }}">@csrf<button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-xs transition {{ $activeTeam?->is($team) ? 'bg-lime font-semibold text-on-accent' : 'text-muted hover:bg-canvas hover:text-ink' }}">{{ $team->name }}</button></form>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</aside>
