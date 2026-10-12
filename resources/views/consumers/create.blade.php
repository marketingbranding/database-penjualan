<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-medium text-orange">Database penjualan</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Tambah konsumen</h1></div></x-slot>
    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8"><form method="POST" action="{{ route('consumers.store') }}" class="mx-auto grid w-full max-w-5xl gap-6">@csrf @include('consumers._form', ['salesCase' => null])<div class="flex justify-end gap-3"><a href="{{ route('consumers.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Batal</a><x-primary-button>Simpan data konsumen</x-primary-button></div></form></div>
</x-app-layout>
