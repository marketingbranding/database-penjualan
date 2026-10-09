<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-amber-700">Aktivitas</p><h1 class="mt-1 text-xl font-bold">Notifikasi</h1></div>@if($unreadNotificationsCount > 0)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Tandai semua dibaca</button></form>@endif</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8"><div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        @forelse($notifications as $notification)
            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="border-b border-gray-100 last:border-0">@csrf @method('PATCH')<button class="flex w-full gap-4 px-6 py-5 text-left hover:bg-gray-50"><span class="mt-1 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-gray-200' : 'bg-amber-500' }}"></span><span><span class="block font-semibold text-gray-950">{{ $notification->data['title'] ?? 'Notifikasi' }}</span><span class="mt-1 block text-sm leading-6 text-gray-600">{{ $notification->data['message'] ?? '' }}</span><span class="mt-2 block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span></span></button></form>
        @empty
            <p class="px-6 py-16 text-center text-gray-500">Belum ada notifikasi.</p>
        @endforelse
    </div><div class="mt-6">{{ $notifications->links() }}</div></div></div>
</x-app-layout>
