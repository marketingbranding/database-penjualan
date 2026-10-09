@php
    $initialTargets = old('targets', $promotion?->targets->map(fn ($t) => [
        'type' => $t->branch_id ? 'branch' : ($t->project_id ? 'project' : 'block'),
        'target_id' => (string) ($t->branch_id ?? $t->project_id ?? $t->project_block_id),
    ])->values()->toArray() ?? [['type' => 'branch', 'target_id' => '']]);
@endphp

<div class="grid gap-6">
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-950">Informasi Promo</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div><x-input-label value="ID Promo"/><p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ $promotion?->promo_id ?? 'Dibuat otomatis saat disimpan' }}</p></div>
            <div><x-input-label for="name" value="Nama Promo"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $promotion?->name)" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
            <div><x-input-label for="start_date" value="Tanggal mulai"/><x-text-input id="start_date" name="start_date" type="date" lang="id-ID" class="mt-1 block w-full" :value="old('start_date', $promotion?->start_date?->format('Y-m-d'))" required/><x-input-error class="mt-2" :messages="$errors->get('start_date')"/></div>
            <div><x-input-label for="end_date" value="Tanggal selesai"/><x-text-input id="end_date" name="end_date" type="date" lang="id-ID" class="mt-1 block w-full" :value="old('end_date', $promotion?->end_date?->format('Y-m-d'))" required/><x-input-error class="mt-2" :messages="$errors->get('end_date')"/></div>
            <div class="md:col-span-2"><x-input-label for="description" value="Keterangan"/><textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $promotion?->description) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('description')"/></div>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" x-data="{ targets: @js($initialTargets) }">
        <div class="flex items-center justify-between gap-4"><h2 class="text-lg font-bold text-gray-950">Target Promo</h2><button type="button" @click="targets.push({ type: 'branch', target_id: '' })" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold">Tambah target</button></div>
        <p class="mt-1 text-sm text-gray-500">Tentukan cabang, proyek, atau blok yang mendapat promo ini.</p>
        <div class="mt-5 grid gap-3">
            <template x-for="(target, index) in targets" :key="index">
                <div class="grid gap-3 rounded-lg border border-gray-200 p-4 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
                    <div><x-input-label value="Jenis"/><select :name="`targets[${index}][type]`" x-model="target.type" @change="target.target_id = ''" class="mt-1 block w-full rounded-md border-gray-300"><option value="branch">Cabang</option><option value="project">Proyek</option><option value="block">Blok</option></select></div>
                    <div>
                        <x-input-label value="Target"/>
                        <template x-if="target.type === 'branch'"><select :name="`targets[${index}][target_id]`" x-model="target.target_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></template>
                        <template x-if="target.type === 'project'"><select :name="`targets[${index}][target_id]`" x-model="target.target_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Pilih proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></template>
                        <template x-if="target.type === 'block'"><select :name="`targets[${index}][target_id]`" x-model="target.target_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Pilih blok</option>@foreach($blocks as $block)<option value="{{ $block->id }}">{{ $block->project->name }} — Blok {{ $block->code }}</option>@endforeach</select></template>
                    </div>
                    <button type="button" @click="targets.splice(index, 1)" class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Hapus</button>
                </div>
            </template>
        </div>
        <x-input-error class="mt-3" :messages="$errors->get('targets')" />
    </section>
</div>
