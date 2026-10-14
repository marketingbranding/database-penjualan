<x-guest-layout>
    <div class="guest-intro">
        <h1>Selamat datang kembali</h1>
        <p>Masuk untuk melanjutkan ke workspace operasional.</p>
    </div>

    <x-auth-session-status class="guest-status" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" data-login-form="true">
        @csrf

        <div class="guest-field">
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" aria-describedby="email-error" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="guest-field">
            <x-input-label for="password" value="Kata sandi" />
            <div class="guest-password-field">
                <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" aria-describedby="password-error" />
                <button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                </button>
            </div>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="guest-form-row">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-line text-lime-dark shadow-sm focus:ring-lime-dark" name="remember">
                <span>Ingat saya di perangkat ini</span>
            </label>
            @if (Route::has('password.request'))
                <a class="guest-link" href="{{ route('password.request') }}">Lupa kata sandi?</a>
            @endif
        </div>

        <x-primary-button class="guest-submit" data-login-submit>
            Masuk
        </x-primary-button>

        <p class="guest-help">Belum punya akses? Hubungi admin cabang.</p>
    </form>
</x-guest-layout>
