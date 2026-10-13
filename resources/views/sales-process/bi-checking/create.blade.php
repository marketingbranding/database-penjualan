<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah BI Checking" description="Catat hasil SLIK, keputusan bank, dan lead time pemeriksaan." /></x-slot>
    <div class="module-page"><x-form-section title="Data BI Checking"><form method="POST" action="{{ route('sales-process.bi-checking.store') }}" class="module-process-form col-span-2">@csrf @include('sales-process.bi-checking._form', ['biChecking' => null])<div class="mt-5 flex justify-end gap-3"><a href="{{ route('sales-process.bi-checking') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
