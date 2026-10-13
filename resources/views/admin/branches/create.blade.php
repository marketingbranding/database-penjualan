<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah Cabang" description="Buat cabang operasional baru untuk mengelola proyek dan tim." /></x-slot>
    <div class="module-page"><x-form-section title="Data cabang" description="Kode cabang dibuat otomatis saat disimpan."><form method="POST" action="{{ route('admin.branches.store') }}" class="module-process-form col-span-2">@csrf @include('admin.branches._form', ['branch' => null])<div class="mt-5 flex justify-end gap-3"><a href="{{ route('admin.branches.index') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
