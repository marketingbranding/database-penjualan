@php
    $guestPageTitles = [
        'login' => 'Masuk',
        'register' => 'Daftar',
        'password.request' => 'Lupa kata sandi',
        'password.reset' => 'Reset kata sandi',
        'password.confirm' => 'Konfirmasi kata sandi',
        'verification.notice' => 'Verifikasi email',
    ];
    $guestPageTitle = $guestPageTitles[request()->route()?->getName()] ?? 'Workspace';
    $brandVersion = file_exists(public_path('brand/oasis-next-mark.svg')) ? filemtime(public_path('brand/oasis-next-mark.svg')) : 1;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <meta property="og:title" content="{{ $guestPageTitle }} · {{ config('app.name', 'Oasis Next') }}">
        <meta property="og:description" content="Workspace operasional">
        <meta property="og:image" content="{{ url('og-image.png') }}?v={{ $brandVersion }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $guestPageTitle }} · {{ config('app.name', 'Oasis Next') }}">
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

        <title>{{ $guestPageTitle }} · {{ config('app.name', 'Oasis Next') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-body bg-canvas font-sans text-ink antialiased">
        <div class="guest-shell">
            <a href="/" class="guest-brand">
                <x-brand.logo variant="lockup" size="md" />
                <small>Workspace operasional</small>
            </a>

            <div class="guest-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
