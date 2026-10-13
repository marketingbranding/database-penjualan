@props([
    'breadcrumbs' => [],
    'searchAction' => null,
    'searchValue' => null,
    'searchPlaceholder' => 'Cari lead, agenda, konsumen...',
    'teamCode' => null,
    'user' => null,
    'unreadCount' => 0,
])

@php($profileUser = $user ?: auth()->user())

<header {{ $attributes->merge(['class' => 'module-global-bar']) }}>
    <button type="button" class="module-mobile-menu" @click="sidebarOpen = true" aria-label="Buka navigasi">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" /></svg>
    </button>
    <a href="{{ $searchAction ?: route('search') }}" class="module-mobile-search" aria-label="Buka pencarian">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="m16 16 4 4" /></svg>
    </a>
    <nav class="module-breadcrumb" aria-label="Breadcrumb">
        @foreach ($breadcrumbs as $index => $breadcrumb)
            @if ($index > 0)
                <span aria-hidden="true">›</span>
            @endif
            <span class="{{ $loop->last ? 'module-breadcrumb-current' : '' }}">{{ is_array($breadcrumb) ? ($breadcrumb['label'] ?? '') : $breadcrumb }}</span>
        @endforeach
    </nav>

    <div class="module-global-actions">
        @if ($searchAction)
            <form method="GET" action="{{ $searchAction }}" class="module-global-search">
                <label for="global-module-search" class="sr-only">Cari</label>
                <span aria-hidden="true">⌕</span>
                <input id="global-module-search" name="q" value="{{ $searchValue ?? request('q') }}" placeholder="{{ $searchPlaceholder }}">
                <kbd>Ctrl K</kbd>
            </form>
        @endif

        @isset($notifications)
            {{ $notifications }}
        @else
            <button type="button" class="module-icon-button" aria-label="Notifikasi">⌁</button>
        @endisset

        <button type="button" class="module-icon-button module-theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Aktifkan mode gelap" title="Aktifkan mode gelap">
            <svg class="size-4 theme-icon-light" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></svg>
            <svg class="size-4 theme-icon-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5 8.5 8.5 0 1 0 20.5 15.2Z" /></svg>
        </button>

        @if ($teamCode)
            <span class="module-team-chip">{{ $teamCode }}</span>
        @endif

        @if ($profileUser)
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="module-profile-trigger" aria-label="Buka profil">
                        <x-ui.avatar :name="$profileUser->name" size="sm" tone="ink" />
                        <span class="module-profile-name">{{ $profileUser->name }}</span>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                    <div class="module-theme-picker" role="group" aria-label="Tema tampilan">
                        <p class="module-theme-picker-label">Tema tampilan</p>
                        <div class="module-theme-picker-options">
                            <button type="button" data-theme-choice="light" aria-pressed="false" aria-label="Gunakan tema terang">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></svg><span>Terang</span>
                            </button>
                            <button type="button" data-theme-choice="dark" aria-pressed="false" aria-label="Gunakan tema gelap">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5 8.5 8.5 0 1 0 20.5 15.2Z" /></svg><span>Gelap</span>
                            </button>
                            <button type="button" data-theme-choice="system" aria-pressed="false" aria-label="Ikuti tema sistem">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M8 21h8m-4-4v4" /></svg><span>Sistem</span>
                            </button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-muted transition hover:bg-canvas hover:text-ink">Keluar</button>
                    </form>
                </x-slot>
            </x-dropdown>
        @endif
    </div>
</header>
