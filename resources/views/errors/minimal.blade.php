@php($brandVersion = file_exists(public_path('brand/oasis-next-mark.svg')) ? filemtime(public_path('brand/oasis-next-mark.svg')) : 1)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $brandVersion }}" type="image/svg+xml">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $brandVersion }}">
        <script>
            (() => {
                const preference = ['light', 'dark', 'system'].includes(localStorage.getItem('theme')) ? localStorage.getItem('theme') : 'system';
                document.documentElement.dataset.theme = preference === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : preference;
            })();
        </script>
        <title>@yield('title') · {{ config('app.name', 'Oasis Next') }}</title>
        <style>
            :root { color-scheme: light; --canvas: #ecece9; --surface: #fff; --text: #1d2219; --muted: #687064; --accent: #9ccc4e; }
            :root[data-theme='dark'] { color-scheme: dark; --canvas: #0f130d; --surface: #181d16; --text: #ecefe6; --muted: #8f988a; --accent: #9ccc4e; }
            * { box-sizing: border-box; }
            html, body { min-height: 100%; }
            body { margin: 0; background: var(--canvas); color: var(--text); font-family: Poppins, ui-sans-serif, system-ui, sans-serif; }
            .error-shell { display: grid; min-height: 100dvh; place-items: center; padding: 1.5rem; }
            .error-card { width: min(100%, 32rem); border: 1px solid color-mix(in srgb, var(--text) 12%, transparent); border-radius: 28px; background: var(--surface); padding: 2.5rem 2rem; text-align: center; box-shadow: 0 18px 50px color-mix(in srgb, var(--text) 10%, transparent); }
            .error-mark { width: 56px; height: 56px; margin: 0 auto 1.25rem; }
            .error-code { color: var(--accent); font-size: .75rem; font-weight: 700; letter-spacing: .12em; }
            .error-title { margin: .5rem 0 0; font-size: 1.5rem; font-weight: 700; }
            .error-message { margin: .5rem 0 0; color: var(--muted); font-size: .85rem; }
            .error-link { display: inline-flex; margin-top: 1.5rem; border-radius: 12px; background: var(--accent); padding: .7rem 1rem; color: #1d2219; font-size: .8rem; font-weight: 700; text-decoration: none; }
            @media (prefers-reduced-motion: no-preference) { .error-card { animation: error-reveal 200ms ease both; } }
            @keyframes error-reveal { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        </style>
    </head>
    <body>
        <main class="error-shell" role="main">
            <section class="error-card">
                <x-brand.logo variant="mark" size="lg" class="error-mark" />
                <div class="error-code">@yield('code')</div>
                <h1 class="error-title">@yield('message')</h1>
                <p class="error-message">Oasis Next · Workspace operasional</p>
                <a class="error-link" href="{{ url('/') }}">Kembali ke beranda</a>
            </section>
        </main>
    </body>
</html>
