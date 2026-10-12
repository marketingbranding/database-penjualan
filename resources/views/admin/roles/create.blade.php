<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-medium text-orange">Pengaturan akses</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Tambah role</h1></div></x-slot>
    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto w-full max-w-5xl"><div class="workspace-card p-6"><form method="POST" action="{{ route('admin.roles.store') }}" class="grid gap-6">@csrf @include('admin.roles._form', ['role' => null])<div class="flex justify-end"><x-primary-button>Simpan role</x-primary-button></div></form></div></div></div>
</x-app-layout>
