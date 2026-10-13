<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah Promo" description="Buat promo dan tentukan target cabang, proyek, atau blok." /></x-slot>
    <div class="module-page"><form method="POST" action="{{ route('admin.promotions.store') }}" class="admin-form-stack mx-auto w-full max-w-4xl">@csrf @include('admin.promotions._form', ['promotion' => null])<div class="mt-1 flex justify-end gap-3"><a href="{{ route('admin.promotions.index') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan</x-primary-button></div></form></div>
</x-app-layout>
