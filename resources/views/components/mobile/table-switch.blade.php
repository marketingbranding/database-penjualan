@props([
    'storageKey',
])

<div class="mobile-view-switch" role="group" aria-label="Mode tampilan">
    <button type="button" @click="mobileTableView = 'cards'" :aria-pressed="(mobileTableView === 'cards').toString()">Kartu</button>
    <button type="button" @click="mobileTableView = 'table'" :aria-pressed="(mobileTableView === 'table').toString()">Tabel</button>
</div>
