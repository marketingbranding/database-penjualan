<x-guest-layout>
    <div class="mb-4 text-sm text-muted">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <div class="guest-password-field"><x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" aria-describedby="password-error" /><button type="button" class="guest-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>

            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
