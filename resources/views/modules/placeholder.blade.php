<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">{{ $section }}</p>
            <h1 class="mt-1 text-xl font-bold text-gray-900">{{ $title }}</h1>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:rounded-xl">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-800">Wadah modul siap</span>
                </div>
                <div class="px-6 py-12 sm:px-10">
                    <div class="max-w-2xl">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
                        <p class="mt-3 leading-7 text-gray-600">{{ $description }}</p>
                        <p class="mt-6 text-sm text-gray-500">Form, tabel, dan alur kerja akan ditambahkan setelah struktur data modul ini ditetapkan.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
