<x-app-layout>
    <x-slot name="header"><x-page-header title="Tambah PSJB / UTJ" description="Lengkapi referensi konsumen, pembayaran, kavling, dan status proses." /></x-slot>
    <div class="module-page"><x-form-section title="Data PSJB / UTJ" description="Perhitungan DP dan harga tanah diperbarui otomatis saat field diisi."><form method="POST" action="{{ route('sales-process.psjb-utj.store') }}" class="module-process-form col-span-2">@csrf @include('sales-process.psjb-utj._form', ['psjbUtj' => null])<div class="mt-5 flex justify-end gap-3"><a href="{{ route('sales-process.psjb-utj') }}" class="module-button module-button-secondary">Batal</a><x-primary-button>Simpan PSJB</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
