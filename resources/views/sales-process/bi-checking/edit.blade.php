<x-app-layout>
    <x-slot name="header"><x-page-header title="Edit BI Checking" description="Perbarui hasil pemeriksaan dan keputusan bank." /></x-slot>
    <div class="module-page">@if (session('status') === 'bi-updated')<x-ui.toast message="BI Checking berhasil diperbarui." />@endif<x-form-section title="Data BI Checking"><form method="POST" action="{{ route('sales-process.bi-checking.update', $biChecking) }}" class="module-process-form col-span-2">@csrf @method('PUT') @include('sales-process.bi-checking._form')<div class="mt-5 flex justify-end gap-3"><a href="{{ route('sales-process.bi-checking') }}" class="module-button module-button-secondary">Kembali</a><x-primary-button>Simpan perubahan</x-primary-button></div></form></x-form-section></div>
</x-app-layout>
