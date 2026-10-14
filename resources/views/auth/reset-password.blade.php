<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" aria-describedby="email-error" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4 guest-field">
            <x-input-label for="password" :value="__('Password')" />
            <div class="guest-password-field"><x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" aria-describedby="password-error" /><button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <div class="guest-password-field"><x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" aria-describedby="password-confirmation-error" /><button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>

            <x-input-error id="password-confirmation-error" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
