<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Profile') }}" description="Kelola informasi akun, keamanan, dan akses profil Anda." />
    </x-slot>

    <div class="module-page">
        <div class="profile-page-grid">
            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="profile-card profile-card-danger">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
