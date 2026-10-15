<nav class="mobile-tab-bar" aria-label="Navigasi mobile">
    <a href="{{ route('dashboard') }}" class="mobile-tab {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
        <span class="mobile-tab-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z" /></svg></span>
        <span>Beranda</span>
    </a>
    <a href="{{ route('leads.index') }}" class="mobile-tab {{ request()->routeIs('leads.*') ? 'is-active' : '' }}">
        <span class="mobile-tab-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19V9m7 10V5m7 14v-7" /></svg></span>
        <span>Leads</span>
    </a>
    <button type="button" class="mobile-tab mobile-tab-create" @click="openMobileSheet('quick')" aria-label="Buka aksi cepat">
        <span class="mobile-create-icon">+</span>
        <span class="sr-only">Tambah</span>
    </button>
    <a href="{{ route('sales-agendas.index') }}" class="mobile-tab {{ request()->routeIs('sales-agendas.*') ? 'is-active' : '' }}">
        <span class="mobile-tab-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect width="16" height="16" x="4" y="5" rx="2" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M8 3v4m8-4v4M4 10h16m-9 4h.01M15 14h.01M9 17h.01M15 17h.01" /></svg></span>
        <span>Agenda</span>
    </a>
    <button type="button" class="mobile-tab {{ request()->routeIs('dashboard', 'leads.*', 'sales-agendas.*') ? '' : 'is-active' }}" @click="openMobileSheet('menu')" :aria-expanded="(mobileSheet === 'menu').toString()">
        <span class="mobile-tab-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 7h14M5 12h14M5 17h14" /></svg></span>
        <span>Menu</span>
    </button>
</nav>

<div x-cloak x-show="mobileSheet" x-transition.opacity class="mobile-sheet-backdrop" @click="closeMobileSheet()" aria-hidden="true"></div>

<section x-cloak x-show="mobileSheet" x-transition:enter="mobile-sheet-enter" x-transition:enter-start="mobile-sheet-enter-start" x-transition:enter-end="mobile-sheet-enter-end" x-transition:leave="mobile-sheet-leave" x-transition:leave-start="mobile-sheet-leave-start" x-transition:leave-end="mobile-sheet-leave-end" class="mobile-sheet" role="dialog" aria-modal="true" :aria-label="mobileSheet === 'menu' ? 'Menu navigasi' : mobileSheet === 'quick' ? 'Aksi cepat' : mobileSheet === 'team' ? 'Pilih tim' : 'Profil pengguna'" @click.stop>
    <div class="mobile-sheet-grabber" aria-hidden="true"></div>

    <template x-if="mobileSheet === 'menu'">
        <div class="mobile-sheet-content">
            <header class="mobile-sheet-header">
                <div><p class="mobile-sheet-eyebrow">Workspace</p><h2>Menu</h2></div>
                <button type="button" class="mobile-sheet-close" @click="closeMobileSheet()" aria-label="Tutup menu">×</button>
            </header>
            <label class="mobile-menu-search"><span aria-hidden="true">⌕</span><input type="search" x-model="mobileMenuQuery" placeholder="Cari menu" aria-label="Cari menu"></label>
            <div class="mobile-menu-list">
                @can('dashboard.view')
                    <a x-show="mobileMenuMatches('Dashboard')" href="{{ route('dashboard') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">⌂</span><span>Dashboard</span></a>
                @endcan
                @can('consumers.view')
                    <a x-show="mobileMenuMatches('Data konsumen')" href="{{ route('consumers.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◎</span><span>Data konsumen</span></a>
                @endcan

                @canany(['sales.view-own', 'sales.view-team', 'sales.view-all', 'leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
                    <p class="mobile-menu-group">Buku Saku Sales</p>
                @endcanany
                @canany(['sales.view-own', 'sales.view-team', 'sales.view-all'])
                    <a x-show="mobileMenuMatches('Agenda sales')" href="{{ route('sales-agendas.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◷</span><span>Agenda sales</span></a>
                @endcanany
                @canany(['leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
                    <a x-show="mobileMenuMatches('Leads')" href="{{ route('leads.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">⌁</span><span>Leads</span></a>
                    <a x-show="mobileMenuMatches('Monitoring leads')" href="{{ route('leads.monitoring') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">⌗</span><span>Monitoring leads</span></a>
                @endcanany
                @can('reports.team-performance')
                    <a x-show="mobileMenuMatches('Performa tim')" href="{{ route('team-performance.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▥</span><span>Performa tim</span></a>
                @endcan
                @can('sales-fee-reports.view')
                    <a x-show="mobileMenuMatches('Laporan performa')" href="{{ route('sales-fee-reports.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▤</span><span>Laporan performa</span></a>
                @endcan

                @can('sales-process.view')
                    <p class="mobile-menu-group">Proses penjualan</p>
                    <a x-show="mobileMenuMatches('Alur kavling')" href="{{ route('sales-process.psjb-utj') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◇</span><span>Alur kavling</span></a>
                    <a x-show="mobileMenuMatches('BI Checking')" href="{{ route('sales-process.bi-checking') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">✓</span><span>BI Checking</span></a>
                    @can('filing.view')<a x-show="mobileMenuMatches('Pemberkasan')" href="{{ route('sales-process.pemberkasan') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▧</span><span>Pemberkasan</span></a>@endcan
                    @can('bank-process.view')<a x-show="mobileMenuMatches('Proses bank')" href="{{ route('sales-process.bank') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▣</span><span>Proses bank</span></a>@endcan
                    @can('ppjb.view')<a x-show="mobileMenuMatches('PPJB developer')" href="{{ route('sales-process.ppjb-developer') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">□</span><span>PPJB developer</span></a>@endcan
                    @can('akad.view')<a x-show="mobileMenuMatches('Akad dan BAST')" href="{{ route('sales-process.akad-bast') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▱</span><span>Akad dan BAST</span></a>@endcan
                @endcan

                @can('warranties.view')
                    <p class="mobile-menu-group">Layanan</p>
                    <a x-show="mobileMenuMatches('Garansi')" href="{{ route('warranties.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◇</span><span>Garansi</span></a>
                @endcan

                @canany(['branches.view', 'projects.view', 'lots.view', 'audit-logs.view', 'banks.view', 'lead-time.view', 'promotions.view', 'users.view', 'teams.view', 'roles.view', 'spatial-workspace.view'])
                    <p class="mobile-menu-group">Pengaturan</p>
                    @can('spatial-workspace.view')<a x-show="mobileMenuMatches('Spatial workspace')" href="{{ route('admin.spatial-workspace') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◉</span><span>Spatial workspace</span></a>@endcan
                    @can('branches.view')<a x-show="mobileMenuMatches('Cabang')" href="{{ route('admin.branches.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">⌂</span><span>Cabang</span></a>@endcan
                    @can('projects.view')<a x-show="mobileMenuMatches('Proyek')" href="{{ route('admin.projects.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">□</span><span>Proyek</span></a>@endcan
                    @can('lots.view')<a x-show="mobileMenuMatches('Kavling')" href="{{ route('admin.lots.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▦</span><span>Kavling</span></a>@endcan
                    @can('audit-logs.view')<a x-show="mobileMenuMatches('Audit log')" href="{{ route('admin.audit-logs.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">≡</span><span>Audit log</span></a>@endcan
                    @can('banks.view')<a x-show="mobileMenuMatches('Bank')" href="{{ route('admin.banks.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">▣</span><span>Bank</span></a>@endcan
                    @can('lead-time.view')<a x-show="mobileMenuMatches('Target lead time')" href="{{ route('admin.lead-times.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◷</span><span>Target lead time</span></a>@endcan
                    @can('promotions.view')<a x-show="mobileMenuMatches('Promo')" href="{{ route('admin.promotions.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">%</span><span>Promo</span></a>@endcan
                    @can('users.view')<a x-show="mobileMenuMatches('Pengguna')" href="{{ route('admin.users.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◉</span><span>Pengguna</span></a>@endcan
                    @can('teams.view')<a x-show="mobileMenuMatches('Tim')" href="{{ route('admin.teams.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">◎</span><span>Tim</span></a>@endcan
                    @can('roles.view')<a x-show="mobileMenuMatches('Role dan permission')" href="{{ route('admin.roles.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">✦</span><span>Role dan permission</span></a>@endcan
                @endcanany

                <p class="mobile-menu-group">Akun</p>
                <a x-show="mobileMenuMatches('Notifikasi')" href="{{ route('notifications.index') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">⌁</span><span>Notifikasi</span></a>
                <a x-show="mobileMenuMatches('Profil')" href="{{ route('profile.edit') }}" class="mobile-menu-link"><span class="mobile-menu-link-icon">●</span><span>Profil</span></a>
            </div>
        </div>
    </template>

    <template x-if="mobileSheet === 'quick'">
        <div class="mobile-sheet-content">
            <header class="mobile-sheet-header"><div><p class="mobile-sheet-eyebrow">Buat baru</p><h2>Aksi cepat</h2></div><button type="button" class="mobile-sheet-close" @click="closeMobileSheet()" aria-label="Tutup aksi cepat">×</button></header>
            <div class="mobile-quick-grid">
                @can('leads.create')<a href="{{ route('leads.create') }}" class="mobile-quick-action"><strong>＋</strong><span>Lead baru</span></a>@endcan
                @can('sales.create')<a href="{{ route('sales-agendas.create') }}" class="mobile-quick-action"><strong>＋</strong><span>Agenda baru</span></a>@endcan
                @can('consumers.create')<a href="{{ route('consumers.create') }}" class="mobile-quick-action"><strong>＋</strong><span>Konsumen baru</span></a>@endcan
                @can('psjb-utj.create')<a href="{{ route('sales-process.psjb-utj.create') }}" class="mobile-quick-action"><strong>＋</strong><span>PSJB / UTJ</span></a>@endcan
            </div>
        </div>
    </template>

    <template x-if="mobileSheet === 'team'">
        <div class="mobile-sheet-content">
            <header class="mobile-sheet-header"><div><p class="mobile-sheet-eyebrow">Workspace</p><h2>Pilih tim</h2></div><button type="button" class="mobile-sheet-close" @click="closeMobileSheet()" aria-label="Tutup pemilih tim">×</button></header>
            <div class="mobile-sheet-list">
                @forelse ($availableTeams as $team)
                    <form method="POST" action="{{ route('team-context.update', $team) }}">@csrf<button type="submit" class="mobile-sheet-list-item {{ $activeTeam?->is($team) ? 'is-active' : '' }}"><span class="mobile-sheet-list-avatar">{{ str($team->name)->substr(0, 1)->upper() }}</span><span class="min-w-0 flex-1 text-left"><strong class="block truncate">{{ $team->name }}</strong><small class="block truncate">{{ $team->branch?->name ?? 'Tim aktif' }}</small></span>@if($activeTeam?->is($team))<span aria-label="Tim aktif">✓</span>@endif</button></form>
                @empty
                    <p class="mobile-sheet-empty">Belum ada tim yang tersedia.</p>
                @endforelse
            </div>
        </div>
    </template>

    <template x-if="mobileSheet === 'profile'">
        <div class="mobile-sheet-content">
            <header class="mobile-sheet-header"><div class="mobile-profile-heading"><x-ui.avatar :name="Auth::user()->name" size="md" tone="ink" /><div><p class="mobile-sheet-eyebrow">Akun</p><h2 class="truncate">{{ Auth::user()->name }}</h2></div></div><button type="button" class="mobile-sheet-close" @click="closeMobileSheet()" aria-label="Tutup profil">×</button></header>
            <div class="mobile-profile-actions">
                <a href="{{ route('profile.edit') }}" class="mobile-profile-link">Profil saya <span>›</span></a>
                <div class="mobile-theme-options"><p>Tema tampilan</p><div class="mobile-theme-choice-list">
                    <button type="button" data-theme-choice="light" aria-pressed="false"><span>☼</span>Terang</button>
                    <button type="button" data-theme-choice="dark" aria-pressed="false"><span>☾</span>Gelap</button>
                    <button type="button" data-theme-choice="system" aria-pressed="false"><span>◌</span>Sistem</button>
                </div></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="mobile-profile-link is-danger">Keluar <span>↗</span></button></form>
            </div>
        </div>
    </template>
</section>
