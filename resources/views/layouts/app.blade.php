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
    <body class="bg-stone-100 font-sans text-gray-900 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            @include('layouts.navigation')

            <div class="lg:pl-72">
                <header class="sticky top-0 z-20 flex min-h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">
                    <button type="button" class="-ml-2 rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>

                    <div class="min-w-0 flex-1 py-4">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h1 class="truncate text-lg font-semibold">{{ config('app.name') }}</h1>
                        @endisset
                    </div>

                    @if ($activeTeam)
                        <span class="hidden rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 sm:inline">{{ $activeTeam->code }}</span>
                    @endif
                </header>

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
