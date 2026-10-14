<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" aria-describedby="name-error" />
            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4 guest-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" aria-describedby="email-error" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4 guest-field">
            <x-input-label for="password" :value="__('Password')" />
            <div class="guest-password-field"><x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" aria-describedby="password-error" /><button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>

            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 guest-field">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <div class="guest-password-field"><x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" aria-describedby="password-confirmation-error" /><button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>

            <x-input-error id="password-confirmation-error" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="guest-link text-sm underline" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
