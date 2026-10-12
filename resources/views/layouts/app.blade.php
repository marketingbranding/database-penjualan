<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1d2219">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-dvh overflow-hidden bg-canvas font-sans text-ink antialiased">
        <div x-data="{ sidebarOpen: false }" class="flex h-dvh gap-3.5 overflow-hidden bg-canvas p-3.5">
            @include('layouts.navigation')

            <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col lg:ml-[80px] xl:ml-[310px]">
                @if (session('impersonator_id'))
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-ink px-4 py-3 text-sm font-semibold text-white sm:px-6 lg:px-8">
                        <span>Anda sedang login sebagai {{ Auth::user()->name }}. Super Admin: {{ session('impersonator_name') }}.</span>
                        <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="rounded-xl bg-lime px-3 py-1.5 text-xs font-semibold text-ink transition hover:bg-lime-dark">Kembali ke Super Admin</button></form>
                    </div>
                @endif
                <header class="workspace-topbar z-20 flex min-h-[52px] shrink-0 flex-wrap items-center gap-3 overflow-x-clip px-1 py-1 sm:gap-4 lg:flex-nowrap">
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-xl text-ink transition hover:bg-lime-soft lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2 lg:hidden" aria-label="Buka dashboard">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-ink text-lime"><span class="text-lg font-bold">O</span></span>
                        <span class="workspace-mobile-brand-name truncate text-sm font-semibold text-ink">Oasis Next</span>
                    </a>
                    <a href="{{ route('search') }}" class="grid size-10 shrink-0 place-items-center rounded-xl text-ink transition hover:bg-lime-soft lg:hidden" aria-label="Buka pencarian" title="Cari">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="m16 16 4 4" /></svg>
                    </a>

                    <div class="workspace-page-heading order-last w-full max-w-full min-w-0 shrink-0 py-4 lg:order-none lg:w-auto lg:flex-1 lg:max-w-[38%] lg:py-3">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h1 class="truncate text-lg font-semibold text-ink">{{ config('app.name') }}</h1>
                        @endisset
                    </div>

                    <form method="GET" action="{{ route('search') }}" class="hidden min-w-0 max-w-[420px] flex-1 lg:block">
                        <label for="global-search" class="sr-only">Cari</label>
                        <div class="relative"><input id="global-search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Cari lead, agenda, konsumen..." class="workspace-control w-full bg-white py-3 pl-10 pr-16 text-[13px] text-ink placeholder:text-muted focus:border-lime-dark focus:ring-lime/30"><span class="pointer-events-none absolute left-3 top-3 text-muted">⌕</span><span class="pointer-events-none absolute right-3 top-2.5 rounded-lg bg-canvas px-1.5 py-1 text-[10px] font-semibold text-muted">Ctrl K</span></div>
                    </form>

                    @isset($branches)
                        @if (Auth::user()->canAccessAllBranches())
                            <form method="GET" action="{{ url()->current() }}" class="hidden xl:block">
                                <label for="topbar-branch" class="sr-only">Filter cabang</label>
                                <select id="topbar-branch" name="branch_id" onchange="this.form.submit()" class="workspace-control max-w-[150px] bg-surface py-2.5 pl-3 pr-8 text-xs font-medium text-ink">
                                    <option value="">Semua cabang</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) ($branchId ?? request('branch_id')) === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @elseif (Auth::user()->branch)
                            <span class="hidden rounded-xl bg-lime-soft px-3 py-2 text-xs font-medium text-lime-dark xl:inline">{{ Auth::user()->branch->name }}</span>
                        @endif
                    @endisset

                    <div class="workspace-notifications">
                    <x-dropdown align="right" width="80">
                        <x-slot name="trigger">
                            <button type="button" class="relative grid size-10 place-items-center rounded-2xl bg-white text-muted transition hover:bg-lime-soft hover:text-ink" aria-label="Notifikasi">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" /></svg>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-orange px-1 text-[10px] font-semibold text-ink">{{ min($unreadNotificationsCount, 99) }}</span>
                                @endif
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="flex items-center justify-between border-b border-line px-4 py-3"><span class="text-sm font-semibold text-ink">Notifikasi</span><a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-lime-dark">Lihat semua</a></div>
                            @forelse ($recentNotifications as $notification)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="block w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50"><span class="block text-sm font-semibold {{ $notification->read_at ? 'text-gray-600' : 'text-gray-950' }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span><span class="mt-1 block text-xs leading-5 text-gray-500">{{ $notification->data['message'] ?? '' }}</span></button></form>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-muted">Belum ada notifikasi.</p>
                            @endforelse
                        </x-slot>
                    </x-dropdown>
                    </div>

                    @if ($activeTeam)
                        <span class="hidden rounded-xl bg-lime-soft px-3 py-2 text-xs font-medium text-lime-dark sm:inline">{{ $activeTeam->code }}</span>
                    @endif
                    <div class="workspace-profile">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-2xl bg-white px-2.5 py-1.5 text-left transition hover:bg-lime-soft" aria-label="Buka profil">
                                <span class="grid size-8 place-items-center rounded-full bg-ink text-xs font-semibold text-lime">{{ str(Auth::user()->name)->substr(0, 1)->upper() }}</span><span class="hidden min-w-0 sm:block"><span class="block max-w-[105px] truncate text-[11px] font-semibold text-ink">{{ Auth::user()->name }}</span><span class="block text-[10px] text-muted">Profil</span></span>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="border-b border-line px-4 py-3"><p class="truncate text-sm font-semibold text-ink">{{ Auth::user()->name }}</p><p class="truncate text-xs text-muted">{{ Auth::user()->email }}</p></div>
                            <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2 text-left text-sm text-muted transition hover:bg-canvas hover:text-ink">Keluar</button></form>
                        </x-slot>
                    </x-dropdown>
                    </div>
                </header>

                <main class="{{ request()->routeIs('admin.spatial-workspace') ? 'spatial-main min-h-0 flex-1 overflow-hidden' : 'min-h-0 flex-1 overflow-y-auto thin-scrollbar' }}">
                    {{ $slot }}
                </main>
            </div>

        </div>
    </body>
</html>
