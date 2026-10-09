<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#111827">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-marison-cream-50 font-sans text-marison-ink-950 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            @include('layouts.navigation')

            <div class="lg:pl-72">
                @if (session('impersonator_id'))
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-marison-gold-400 px-4 py-3 text-sm font-semibold text-marison-ink-950 sm:px-6 lg:px-8">
                        <span>Anda sedang login sebagai {{ Auth::user()->name }}. Super Admin: {{ session('impersonator_name') }}.</span>
                        <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="rounded-lg bg-marison-forest-950 px-3 py-1.5 text-xs font-bold text-white hover:bg-marison-forest-900">Kembali ke Super Admin</button></form>
                    </div>
                @endif
                <header class="sticky top-0 z-20 flex min-h-16 items-center gap-4 border-b border-marison-line bg-marison-paper/95 px-4 shadow-sm backdrop-blur sm:px-6 lg:px-8">
                    <button type="button" class="-ml-2 rounded-lg p-2 text-marison-forest-900 hover:bg-marison-cream-50 lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <div class="min-w-0 flex-1 py-4">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h1 class="truncate text-lg font-semibold">{{ config('app.name') }}</h1>
                        @endisset
                    </div>

                    <form method="GET" action="{{ route('search') }}" class="hidden min-w-0 max-w-sm flex-1 lg:block">
                        <label for="global-search" class="sr-only">Cari</label>
                        <div class="relative"><input id="global-search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Cari Lead, agenda, konsumen..." class="w-full rounded-lg border-marison-line bg-marison-cream-50 py-2 pl-10 pr-3 text-sm focus:border-marison-terracotta-600 focus:ring-marison-terracotta-600/20"><span class="pointer-events-none absolute left-3 top-2.5 text-gray-400">⌕</span></div>
                    </form>

                    <x-dropdown align="right" width="80">
                        <x-slot name="trigger">
                            <button type="button" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Notifikasi">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6.002 6.002 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" /></svg>
                                @if ($unreadNotificationsCount > 0)
                                    <span class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ min($unreadNotificationsCount, 99) }}</span>
                                @endif
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3"><span class="text-sm font-bold text-gray-900">Notifikasi</span><a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-amber-700">Lihat semua</a></div>
                            @forelse ($recentNotifications as $notification)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="block w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50"><span class="block text-sm font-semibold {{ $notification->read_at ? 'text-gray-600' : 'text-gray-950' }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span><span class="mt-1 block text-xs leading-5 text-gray-500">{{ $notification->data['message'] ?? '' }}</span></button></form>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-gray-500">Belum ada notifikasi.</p>
                            @endforelse
                        </x-slot>
                    </x-dropdown>

                    @if ($activeTeam)
                        <span class="hidden rounded-full bg-marison-gold-100 px-3 py-1 text-xs font-semibold text-marison-forest-950 sm:inline">{{ $activeTeam->code }}</span>
                    @endif
                </header>

                @if (request()->routeIs('leads.*') || request()->routeIs('sales-agendas.*') || request()->routeIs('sales-fee-reports.*'))
                    <nav class="border-b border-marison-line bg-marison-paper px-4 sm:px-6 lg:px-8" aria-label="Buku Saku Sales">
                        <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto py-2">
                            @canany(['leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all', 'leads.monitor'])
                                <a href="{{ route('leads.index') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider {{ request()->routeIs('leads.index', 'leads.create', 'leads.show', 'leads.edit') ? 'bg-marison-terracotta-600 text-white' : 'text-gray-600 hover:bg-marison-cream-50' }}">Leads</a>
                            @endcanany
                            @canany(['sales.view-own', 'sales.view-team', 'sales.view-all'])
                                <a href="{{ route('sales-agendas.index') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider {{ request()->routeIs('sales-agendas.*') ? 'bg-marison-gold-400 text-marison-forest-950' : 'text-gray-600 hover:bg-marison-cream-50' }}">Agenda</a>
                            @endcanany
                            @canany(['leads.monitor', 'leads.view-team', 'leads.view-all'])
                                <a href="{{ route('leads.monitoring') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider {{ request()->routeIs('leads.monitoring') ? 'bg-marison-terracotta-600 text-white' : 'text-gray-600 hover:bg-marison-cream-50' }}">Monitoring</a>
                            @endcanany
                            @canany(['sales-fee-reports.view', 'leads.monitor', 'leads.view-team', 'leads.view-all'])
                                <a href="{{ route('sales-fee-reports.index') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider {{ request()->routeIs('sales-fee-reports.*') ? 'bg-marison-teal-600 text-white' : 'text-gray-600 hover:bg-marison-cream-50' }}">Laporan</a>
                            @endcanany
                        </div>
                    </nav>
                @endif

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
