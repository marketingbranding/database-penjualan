<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notifikasi" description="Aktivitas terbaru dari workflow operasional Anda.">
            <x-slot name="actions">
                @if ($unreadNotificationsCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="module-button module-button-secondary">Tandai semua dibaca</button></form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="module-page">
        <x-table-card title="Aktivitas terbaru" :count="number_format($notifications->total()).' notifikasi'" class="notification-card">
            <div class="notification-list">
                @forelse ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="notification-item {{ $notification->read_at ? 'is-read' : '' }}">@csrf @method('PATCH')<button type="submit"><span class="notification-dot"></span><span class="min-w-0"><span class="notification-title">{{ $notification->data['title'] ?? 'Notifikasi' }}</span><span class="notification-message">{{ $notification->data['message'] ?? '' }}</span><span class="notification-time">{{ $notification->created_at->diffForHumans() }}</span></span></button></form>
                @empty
                    <x-empty-state title="Belum ada notifikasi." description="Aktivitas baru akan muncul di sini." />
                @endforelse
            </div>
            <x-slot name="footer"><x-pagination :paginator="$notifications" /></x-slot>
        </x-table-card>
    </div>
</x-app-layout>
