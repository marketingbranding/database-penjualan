<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah Proyek" description="Buat proyek baru dan tentukan cabang operasionalnya." /></x-slot>
    <div class="module-page"><x-form-section title="Data proyek" description="Kode proyek dibuat otomatis saat disimpan."><form method="POST" action="{{ route('admin.projects.store') }}" class="module-process-form col-span-2">@csrf @include('admin.projects._form', ['project' => null])<div class="mt-5 flex justify-end"><x-primary-button>Simpan proyek</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
