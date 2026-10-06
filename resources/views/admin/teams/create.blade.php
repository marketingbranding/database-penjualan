<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Tambah tim</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm sm:rounded-lg">
        <form method="POST" action="{{ route('admin.teams.store') }}" class="grid gap-6">
            @csrf
            @include('admin.teams._form', ['team' => null])
            <div class="flex justify-end gap-3"><a href="{{ route('admin.teams.index') }}" class="px-4 py-2 text-sm text-gray-600">Batal</a><x-primary-button>Simpan</x-primary-button></div>
        </form>
    </div></div></div>
</x-app-layout>
