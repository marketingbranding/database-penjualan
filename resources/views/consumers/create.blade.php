<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah konsumen" description="Buat reservasi baru dan lengkapi identitas konsumen." /></x-slot>
    <div class="module-page"><form method="POST" action="{{ route('consumers.store') }}" class="mx-auto grid w-full max-w-5xl gap-6">@csrf @include('consumers._form', ['salesCase' => null])<div class="flex justify-end gap-3"><a href="{{ route('consumers.index') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan data konsumen</x-primary-button></div></form></div>
</x-app-layout>
