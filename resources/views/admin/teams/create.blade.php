<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah tim" description="Buat struktur tim dan tentukan cakupan organisasinya." /></x-slot>
    <div class="module-page"><x-form-section title="Data tim" description="Kode tim dibuat otomatis saat disimpan."><form method="POST" action="{{ route('admin.teams.store') }}" class="module-process-form col-span-2">@csrf @include('admin.teams._form', ['team' => null])<div class="mt-5 flex justify-end gap-3"><a href="{{ route('admin.teams.index') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
