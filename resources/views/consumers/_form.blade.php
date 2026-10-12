@php
    $consumer = $salesCase?->consumer;
    $currentLot = $salesCase?->lot;
    $projectOptions = $projects->map(fn ($project) => [
        'id' => $project->id,
        'branch_id' => $project->branch_id,
        'name' => $project->name,
    ])->values();
    $lotOptions = $projects->flatMap(fn ($project) => $project->lots->map(fn ($lot) => [
        'id' => $lot->id,
        'project_id' => $project->id,
        'branch_id' => $project->branch_id,
        'block_id' => $lot->project_block_id,
        'label' => $project->name.'-'.$lot->code,
    ]))->values();
    $promotionOptions = $promotions->map(fn ($promotion) => [
        'id' => $promotion->id,
        'label' => $promotion->promo_id.' — '.$promotion->name,
        'branches' => $promotion->targets->whereNotNull('branch_id')->pluck('branch_id')->all(),
        'projects' => $promotion->targets->whereNotNull('project_id')->pluck('project_id')->all(),
        'blocks' => $promotion->targets->whereNotNull('project_block_id')->pluck('project_block_id')->all(),
    ])->values();
    $initialBranch = old('branch_id', $currentLot?->project?->branch_id ?? $assignedBranch?->id);
    $initialProject = old('project_id', $currentLot?->project_id);
    $initialLot = old('lot_id', $currentLot?->id);
    $initialPromotion = old('promotion_id', $salesCase?->promotion_id);
@endphp

<div
    class="grid gap-6"
    x-data="{
        branch: @js((string) $initialBranch),
        project: @js((string) $initialProject),
        lot: @js((string) $initialLot),
        promotion: @js((string) $initialPromotion),
        birthDate: @js(old('tanggal_lahir', $consumer?->tanggal_lahir?->format('Y-m-d'))),
        projects: @js($projectOptions),
        lots: @js($lotOptions),
        promotions: @js($promotionOptions),
        get filteredProjects() { return this.projects.filter(item => String(item.branch_id) === String(this.branch)); },
        get filteredLots() { return this.lots.filter(item => String(item.project_id) === String(this.project)); },
        get selectedLot() { return this.lots.find(item => String(item.id) === String(this.lot)); },
        get filteredPromotions() {
            if (!this.selectedLot) return [];
            return this.promotions.filter(p =>
                p.branches.some(id => Number(id) === Number(this.selectedLot.branch_id)) ||
                p.projects.some(id => Number(id) === Number(this.selectedLot.project_id)) ||
                p.blocks.some(id => Number(id) === Number(this.selectedLot.block_id)));
        },
        get age() {
            if (!this.birthDate) return null;
            const birth = new Date(this.birthDate + 'T00:00:00');
            const today = new Date();
            let value = today.getFullYear() - birth.getFullYear();
            if (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate())) value--;
            return value;
        }
    }"
>
    <section class="workspace-card p-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Kavling & Pembayaran</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-3">
            <div>
                <x-input-label value="Cabang" />
                @if (Auth::user()->canAccessAllBranches())
                    <select name="branch_id" x-model="branch" @change="project = ''; lot = ''; promotion = ''" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>
                        <option value="">Pilih cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="workspace-control mt-1 flex min-h-11 items-center bg-canvas px-3 py-2 text-sm font-semibold text-muted">{{ $assignedBranch?->name }}</div>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
            </div>
            <div>
                <x-input-label for="project_id" value="Proyek" />
                <select id="project_id" name="project_id" x-model="project" @change="lot = ''; promotion = ''" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>
                    <option value="">Pilih proyek</option>
                    <template x-for="item in filteredProjects" :key="item.id"><option :value="String(item.id)" x-text="item.name"></option></template>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('project_id')" />
            </div>
            <div>
                <x-input-label for="lot_id" value="Kavling" />
                <select id="lot_id" name="lot_id" x-model="lot" @change="promotion = ''" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>
                    <option value="">Pilih kavling</option>
                    <template x-for="item in filteredLots" :key="item.id"><option :value="String(item.id)" x-text="item.label"></option></template>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('lot_id')" />
            </div>
            <div>
                <x-input-label for="metode_pembayaran" value="Metode pembayaran" />
                <select id="metode_pembayaran" name="metode_pembayaran" class="workspace-control mt-1 block w-full py-2.5 text-sm" required>
                    @foreach ($paymentMethods as $method)<option value="{{ $method->value }}" @selected(old('metode_pembayaran', $salesCase?->payment_method?->value) === $method->value)>{{ $method->label() }}</option>@endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('metode_pembayaran')" />
            </div>
            <div>
                <x-input-label for="promotion_id" value="Promo" />
                <select id="promotion_id" name="promotion_id" x-model="promotion" class="workspace-control mt-1 block w-full py-2.5 text-sm">
                    <option value="">Tanpa promo</option>
                    <template x-for="item in filteredPromotions" :key="item.id"><option :value="String(item.id)" x-text="item.label"></option></template>
                </select>
                <p class="mt-1 text-xs text-muted">Pilih kavling untuk melihat promo yang berlaku.</p>
                <x-input-error class="mt-2" :messages="$errors->get('promotion_id')" />
            </div>
        </div>
    </section>

    <section class="workspace-card p-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Identitas konsumen</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div><x-input-label for="no_ktp" value="NIK"/><x-text-input id="no_ktp" name="no_ktp" inputmode="numeric" maxlength="16" class="mt-1 block w-full" :value="old('no_ktp', $consumer?->no_ktp)" required/><x-input-error class="mt-2" :messages="$errors->get('no_ktp')"/></div>
            <div><x-input-label for="nama_konsumen" value="Nama sesuai KTP"/><x-text-input id="nama_konsumen" name="nama_konsumen" class="mt-1 block w-full" :value="old('nama_konsumen', $consumer?->nama_konsumen)" required/><x-input-error class="mt-2" :messages="$errors->get('nama_konsumen')"/></div>
            <div><x-input-label for="tanggal_lahir" value="Tanggal lahir"/><x-text-input id="tanggal_lahir" name="tanggal_lahir" type="date" lang="id-ID" x-model="birthDate" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('tanggal_lahir')"/></div>
            <div><x-input-label value="Umur"/><div class="workspace-control mt-1 flex min-h-11 items-center bg-canvas px-3 py-2 text-sm text-muted"><span x-text="age === null ? '-' : age + ' tahun'"></span></div></div>
            <div><x-input-label for="pekerjaan" value="Pekerjaan"/><x-text-input id="pekerjaan" name="pekerjaan" class="mt-1 block w-full" :value="old('pekerjaan', $consumer?->pekerjaan)" required/><x-input-error class="mt-2" :messages="$errors->get('pekerjaan')"/></div>
            <div><x-input-label for="detail_pekerjaan" value="Detail pekerjaan"/><x-text-input id="detail_pekerjaan" name="detail_pekerjaan" class="mt-1 block w-full" :value="old('detail_pekerjaan', $consumer?->detail_pekerjaan)"/><x-input-error class="mt-2" :messages="$errors->get('detail_pekerjaan')"/></div>
        </div>
    </section>

    <section class="workspace-card p-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Alamat & Kontak</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2"><x-input-label for="alamat" value="Alamat"/><textarea id="alamat" name="alamat" rows="3" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('alamat', $consumer?->alamat) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('alamat')"/></div>
            <div><x-input-label for="kelurahan" value="Kelurahan"/><x-text-input id="kelurahan" name="kelurahan" class="mt-1 block w-full" :value="old('kelurahan', $consumer?->kelurahan)" required/><x-input-error class="mt-2" :messages="$errors->get('kelurahan')"/></div>
            <div><x-input-label for="kecamatan" value="Kecamatan"/><x-text-input id="kecamatan" name="kecamatan" class="mt-1 block w-full" :value="old('kecamatan', $consumer?->kecamatan)" required/><x-input-error class="mt-2" :messages="$errors->get('kecamatan')"/></div>
            <div><x-input-label for="kabupaten_kota" value="Kabupaten/Kota"/><x-text-input id="kabupaten_kota" name="kabupaten_kota" class="mt-1 block w-full" :value="old('kabupaten_kota', $consumer?->kabupaten_kota)" required/><x-input-error class="mt-2" :messages="$errors->get('kabupaten_kota')"/></div>
            <div><x-input-label for="no_hp" value="Nomor HP"/><x-text-input id="no_hp" name="no_hp" class="mt-1 block w-full" :value="old('no_hp', $consumer?->no_hp)" required/><x-input-error class="mt-2" :messages="$errors->get('no_hp')"/></div>
            <div><x-input-label for="nama_kontak_darurat" value="Nama kontak darurat"/><x-text-input id="nama_kontak_darurat" name="nama_kontak_darurat" class="mt-1 block w-full" :value="old('nama_kontak_darurat', $consumer?->nama_kontak_darurat)" required/><x-input-error class="mt-2" :messages="$errors->get('nama_kontak_darurat')"/></div>
            <div><x-input-label for="no_hp_kontak_darurat" value="Nomor HP kontak darurat"/><x-text-input id="no_hp_kontak_darurat" name="no_hp_kontak_darurat" class="mt-1 block w-full" :value="old('no_hp_kontak_darurat', $consumer?->no_hp_kontak_darurat)" required/><x-input-error class="mt-2" :messages="$errors->get('no_hp_kontak_darurat')"/></div>
            <div class="md:col-span-2"><x-input-label for="keterangan" value="Keterangan"/><textarea id="keterangan" name="keterangan" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('keterangan', $salesCase?->notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('keterangan')"/></div>
        </div>
    </section>
</div>
