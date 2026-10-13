<x-app-layout>
    <x-slot name="header"><x-page-header :title="$title" :description="$description" /></x-slot>
    <div class="module-page"><section class="module-placeholder-card"><div class="module-placeholder-eyebrow">Wadah modul siap</div><div class="module-placeholder-content"><h2>{{ $title }}</h2><p>{{ $description }}</p><p>Form, tabel, dan alur kerja akan ditambahkan setelah struktur data modul ini ditetapkan.</p></div></section></div>
</x-app-layout>
