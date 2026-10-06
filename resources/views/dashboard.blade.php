<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Akses aktif</p>
                    <h3 class="mt-2 text-2xl font-bold text-gray-900">{{ $activeTeam?->name ?? 'Tanpa tim' }}</h3>
                    <p class="mt-2 text-sm text-gray-600">Role global: {{ Auth::user()->globalRoles->pluck('name')->join(', ') ?: '-' }}</p>
                    <p class="mt-1 text-sm text-gray-600">Role tim: {{ Auth::user()->roles->pluck('name')->join(', ') ?: '-' }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
