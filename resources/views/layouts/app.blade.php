@php
    $moduleBreadcrumbs = match (true) {
        request()->routeIs('dashboard') => ['Utama', 'Dashboard'],
        request()->routeIs('consumers.*') => ['Utama', 'Data konsumen'],
        request()->routeIs('admin.users.*') => ['Pengaturan', 'Pengguna'],
        request()->routeIs('admin.promotions.*') => ['Pengaturan', 'Promo'],
        request()->routeIs('admin.branches.*') => ['Pengaturan', 'Cabang'],
        request()->routeIs('admin.projects.*') => ['Pengaturan', 'Proyek'],
        request()->routeIs('admin.lots.*') => ['Pengaturan', 'Kavling'],
        request()->routeIs('admin.audit-logs.*') => ['Pengaturan', 'Audit log'],
        request()->routeIs('admin.banks.*') => ['Pengaturan', 'Bank'],
        request()->routeIs('admin.lead-times.*') => ['Pengaturan', 'Target lead time'],
        request()->routeIs('admin.teams.*') => ['Pengaturan', 'Tim'],
        request()->routeIs('admin.roles.*') => ['Pengaturan', 'Role & permission'],
        request()->routeIs('admin.*') => ['Pengaturan'],
        request()->routeIs('leads.monitoring') => ['Buku Saku Sales', 'Monitoring Lead'],
        request()->routeIs('leads.*') => ['Buku Saku Sales', 'Leads'],
        request()->routeIs('sales-agendas.*') => ['Buku Saku Sales', 'Agenda sales'],
        request()->routeIs('sales-fee-reports.*') => ['Buku Saku Sales', 'Laporan performa'],
        request()->routeIs('warranties.*') => ['Layanan', 'Garansi'],
        request()->routeIs('sales-process.psjb-utj*') => ['Buku Saku Sales', 'PSJB / UTJ'],
        request()->routeIs('sales-process.bi-checking') => ['Buku Saku Sales', 'BI Checking'],
        request()->routeIs('sales-process.pemberkasan') => ['Buku Saku Sales', 'Pemberkasan'],
        request()->routeIs('sales-process.bank') => ['Buku Saku Sales', 'Proses Bank'],
        request()->routeIs('sales-process.ppjb-developer') => ['Buku Saku Sales', 'PPJB Developer'],
        request()->routeIs('sales-process.akad-bast') => ['Buku Saku Sales', 'Akad & BAST'],
        request()->routeIs('notifications.*') => ['Aktivitas', 'Notifikasi'],
        request()->routeIs('profile.*') => ['Akun', 'Profile'],
        default => ['Utama'],
    };
    $pageTitle = last($moduleBreadcrumbs) ?: config('app.name', 'Oasis Next');
    $brandVersion = file_exists(public_path('brand/oasis-next-mark.svg')) ? filemtime(public_path('brand/oasis-next-mark.svg')) : 1;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#ecece9" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0f130d" media="(prefers-color-scheme: dark)">
        <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $brandVersion }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $brandVersion }}" sizes="any">
        <link rel="icon" href="{{ asset('favicon-32x32.png') }}?v={{ $brandVersion }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon-16x16.png') }}?v={{ $brandVersion }}" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $brandVersion }}" sizes="180x180">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $brandVersion }}">
        <meta name="description" content="Workspace operasional">
        <meta property="og:title" content="{{ $pageTitle }} · {{ config('app.name', 'Oasis Next') }}">
        <meta property="og:description" content="Workspace operasional">
        <meta property="og:image" content="{{ url('og-image.png') }}?v={{ $brandVersion }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }} · {{ config('app.name', 'Oasis Next') }}">
        <meta name="twitter:description" content="Workspace operasional">
        <meta name="twitter:image" content="{{ url('og-image.png') }}?v={{ $brandVersion }}">

        <script>
            (() => {
                const preference = ['light', 'dark', 'system'].includes(localStorage.getItem('theme')) ? localStorage.getItem('theme') : 'system';
                const theme = preference === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : preference;
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            })();
        </script>

        <title>{{ $pageTitle }} · {{ config('app.name', 'Oasis Next') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-dvh overflow-hidden bg-canvas font-sans text-ink antialiased">
        <div class="welcome-overlay" data-auth-welcome hidden aria-hidden="true">
            <div class="welcome-overlay-card" role="dialog" aria-label="Selamat datang">
                <div class="welcome-check" aria-hidden="true">✓</div>
                <h2>Selamat datang, {{ Auth::user()->name }}</h2>
                <p>Menyiapkan workspace operasional…</p>
                <div class="welcome-progress" data-welcome-progress><span></span></div>
                <canvas class="welcome-grid" aria-hidden="true"></canvas>
                <button type="button" class="welcome-skip" data-welcome-skip>Lewati</button>
            </div>
        </div>
        <div x-data="{ sidebarOpen: false, mobileSheet: null, mobileMenuQuery: '', openMobileSheet(sheet) { this.mobileSheet = sheet; window.history.pushState({ oasisMobileSheet: sheet }, ''); }, closeMobileSheet() { if (! this.mobileSheet) return; this.mobileSheet = null; if (window.history.state?.oasisMobileSheet) window.history.back(); }, mobileMenuMatches(label) { return ! this.mobileMenuQuery || label.toLowerCase().includes(this.mobileMenuQuery.toLowerCase()); } }" @keydown.escape.window="closeMobileSheet(); sidebarOpen = false" @popstate.window="mobileSheet = null" data-mobile-shell :class="{ 'mobile-sheet-open': mobileSheet }" class="mobile-shell flex h-dvh gap-3.5 overflow-hidden bg-canvas p-3.5">
            @include('layouts.navigation')

            <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col lg:ml-[80px] xl:ml-[310px]">
                @if (session('impersonator_id'))
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-inverse px-4 py-3 text-sm font-semibold text-inverse-text sm:px-6 lg:px-8">
                        <span>Anda sedang login sebagai {{ Auth::user()->name }}. Super Admin: {{ session('impersonator_name') }}.</span>
                        <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="rounded-xl bg-lime px-3 py-1.5 text-xs font-semibold text-on-accent transition hover:bg-lime-dark">Kembali ke Super Admin</button></form>
                    </div>
                @endif
                <x-global-bar
                    class="workspace-topbar z-20 shrink-0"
                    :breadcrumbs="$moduleBreadcrumbs"
                    :search-action="route('search')"
                    :search-value="request()->routeIs('search') ? request('q') : null"
                    :team-code="$activeTeam?->code"
                    :user="Auth::user()"
                    :unread-count="$unreadNotificationsCount"
                >
                    <x-slot name="notifications">
                        <x-dropdown align="right" width="80">
                            <x-slot name="trigger">
                                <button type="button" class="relative module-icon-button" aria-label="Notifikasi">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" /></svg>
                                    @if ($unreadNotificationsCount > 0)<span class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-orange px-1 text-[10px] font-semibold text-ink">{{ min($unreadNotificationsCount, 99) }}</span>@endif
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="flex items-center justify-between border-b border-line px-4 py-3"><span class="text-sm font-semibold text-ink">Notifikasi</span><a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-lime-dark">Lihat semua</a></div>
                                @forelse ($recentNotifications as $notification)
                                     <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="block w-full border-b border-line px-4 py-3 text-left transition hover:bg-canvas"><span class="block text-sm font-semibold {{ $notification->read_at ? 'text-muted' : 'text-ink' }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span><span class="mt-1 block text-xs leading-5 text-muted">{{ $notification->data['message'] ?? '' }}</span></button></form>
                                @empty
                                    <p class="px-4 py-8 text-center text-sm text-muted">Belum ada notifikasi.</p>
                                @endforelse
                            </x-slot>
                        </x-dropdown>
                    </x-slot>
                </x-global-bar>

                @isset($header)
                    <div class="module-layout-page-header">{{ $header }}</div>
                @endisset

                <main class="{{ request()->routeIs('admin.spatial-workspace') ? 'spatial-main min-h-0 flex-1 overflow-hidden' : 'min-h-0 flex-1 overflow-y-auto thin-scrollbar' }}">
                    {{ $slot }}
                </main>
            </div>

            @include('components.mobile-navigation')

        </div>
    </body>
</html>
