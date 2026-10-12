<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-medium text-orange">Pengaturan organisasi</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Tambah tim</h1></div></x-slot>
    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto w-full max-w-3xl"><div class="workspace-card p-6"><form method="POST" action="{{ route('admin.teams.store') }}" class="grid gap-6">@csrf @include('admin.teams._form', ['team' => null])<div class="flex justify-end gap-3"><a href="{{ route('admin.teams.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Batal</a><x-primary-button>Simpan</x-primary-button></div></form></div></div></div>
</x-app-layout>
