<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah role" description="Buat role global atau role yang dibatasi pada tim tertentu." /></x-slot>
    <div class="module-page"><x-form-section title="Data role" description="Pilih permission yang dapat digunakan role ini."><form method="POST" action="{{ route('admin.roles.store') }}" class="module-process-form col-span-2">@csrf @include('admin.roles._form', ['role' => null])<div class="mt-5 flex justify-end"><x-primary-button>Simpan role</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
