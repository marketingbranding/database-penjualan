@php
    $selectedCaseId = old('sales_case_id', $psjbUtj?->sales_case_id);
    $caseOptions = $salesCases->map(fn ($case) => [
        'id' => $case->id,
        'label' => $case->consumer->nama_konsumen.' — '.$case->lot->project->name.'-'.$case->lot->code,
        'consumer' => $case->consumer->nama_konsumen,
        'nik' => $case->consumer->no_ktp,
        'lot' => $case->lot->project->name.'-'.$case->lot->code,
        'payment' => $case->payment_method->label(),
        'promotion' => $case->promotion?->promo_id.' — '.$case->promotion?->name,
    ])->values();
    $selectedCase = $caseOptions->firstWhere('id', (int) $selectedCaseId);
@endphp

<div class="grid gap-6" x-data="{
    selectedCaseId: @js((string) $selectedCaseId),
    cases: @js($caseOptions),
    dpAllIn: @js((string) old('dp_all_in', $psjbUtj?->dp_all_in ?? 0)),
    dpCount: @js((string) old('dp_installment_count', $psjbUtj?->dp_installment_count ?? '')),
    landArea: @js((string) old('land_area_m2', $psjbUtj?->land_area_m2 ?? 0)),
    landPrice: @js((string) old('land_price_per_m2', $psjbUtj?->land_price_per_m2 ?? 0)),
    get selectedCase() { return this.cases.find(item => String(item.id) === String(this.selectedCaseId)); },
    get installmentAmount() { return this.dpCount > 0 ? Number(this.dpAllIn || 0) / Number(this.dpCount) : 0; },
    get landTotal() { return Number(this.landArea || 0) * Number(this.landPrice || 0); },
}">
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-950">Referensi Data Konsumen</h2>
        @if(!$psjbUtj)
            <div class="mt-5"><x-input-label for="sales_case_id" value="Konsumen / kavling"/><select id="sales_case_id" name="sales_case_id" x-model="selectedCaseId" class="mt-1 block w-full rounded-md border-gray-300" required><option value="">Pilih data konsumen</option>@foreach($caseOptions as $case)<option value="{{ $case['id'] }}">{{ $case['label'] }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('sales_case_id')"/></div>
        @else
            <div class="mt-5 rounded-lg bg-gray-50 p-4"><p class="text-sm font-semibold text-gray-950">{{ $psjbUtj->salesCase->consumer->nama_konsumen }} — {{ $psjbUtj->salesCase->lot->project->name }}-{{ $psjbUtj->salesCase->lot->code }}</p><p class="mt-1 text-xs text-gray-500">ID Konsumen: {{ $psjbUtj->salesCase->consumer->no_ktp }} · Promo: {{ $psjbUtj->salesCase->promotion?->promo_id ?? 'Tanpa promo' }}</p></div>
        @endif
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-show="selectedCase"><div><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Konsumen</p><p class="mt-1 font-semibold" x-text="selectedCase?.consumer"></p></div><div><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Kavling</p><p class="mt-1 font-semibold" x-text="selectedCase?.lot"></p></div><div><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Pembayaran</p><p class="mt-1 font-semibold" x-text="selectedCase?.payment"></p></div><div><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Promo</p><p class="mt-1 font-semibold" x-text="selectedCase?.promotion || 'Tanpa promo'"></p></div></div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold text-gray-950">Data PSJB / UTJ</h2><div class="mt-5 grid gap-5 md:grid-cols-3">
        <div><x-input-label for="psjb_date" value="Tanggal PSJB"/><x-text-input id="psjb_date" name="psjb_date" type="date" lang="id-ID" class="mt-1 block w-full" :value="old('psjb_date', $psjbUtj?->psjb_date?->format('Y-m-d') ?? today()->format('Y-m-d'))" required/><x-input-error class="mt-2" :messages="$errors->get('psjb_date')"/></div>
        <div><x-input-label for="process_stage" value="Tahap proses saat ini"/><select id="process_stage" name="process_stage" class="mt-1 block w-full rounded-md border-gray-300"><option value="psjb_utj" @selected(old('process_stage', $psjbUtj?->process_stage ?? 'psjb_utj') === 'psjb_utj')>PSJB / UTJ</option><option value="bi_checking" @selected(old('process_stage', $psjbUtj?->process_stage) === 'bi_checking')>BI Checking</option><option value="pemberkasan" @selected(old('process_stage', $psjbUtj?->process_stage) === 'pemberkasan')>Pemberkasan</option><option value="proses_bank" @selected(old('process_stage', $psjbUtj?->process_stage) === 'proses_bank')>Proses Bank</option><option value="ppjb" @selected(old('process_stage', $psjbUtj?->process_stage) === 'ppjb')>PPJB</option></select><x-input-error class="mt-2" :messages="$errors->get('process_stage')"/></div>
        <div class="flex items-end"><label class="inline-flex items-center gap-2"><input type="hidden" name="sp3k_issued" value="0"><input type="checkbox" name="sp3k_issued" value="1" @checked(old('sp3k_issued', $psjbUtj?->sp3k_issued ?? false)) class="rounded border-gray-300 text-amber-600"><span class="text-sm font-semibold text-gray-700">SP3K sudah terbit</span></label></div>
        <div><x-input-label for="coordinator_id" value="Nama koordinator"/><select id="coordinator_id" name="coordinator_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Pilih koordinator</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) old('coordinator_id', $psjbUtj?->coordinator_id) === (string) $user->id)>{{ $user->name }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('coordinator_id')"/></div>
        <div><x-input-label for="sales_id" value="Nama sales"/><select id="sales_id" name="sales_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">Pilih sales</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) old('sales_id', $psjbUtj?->sales_id) === (string) $user->id)>{{ $user->name }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('sales_id')"/></div>
        <div><x-input-label for="unit_price" value="Harga unit"/><x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price', $psjbUtj?->unit_price ?? 0)" required/><x-input-error class="mt-2" :messages="$errors->get('unit_price')"/></div>
        <div><x-input-label for="utj_date" value="Tanggal UTJ"/><x-text-input id="utj_date" name="utj_date" type="date" lang="id-ID" class="mt-1 block w-full" :value="old('utj_date', $psjbUtj?->utj_date?->format('Y-m-d'))"/><x-input-error class="mt-2" :messages="$errors->get('utj_date')"/></div>
        <div><x-input-label for="utj_amount" value="Nominal UTJ"/><x-text-input id="utj_amount" name="utj_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('utj_amount', $psjbUtj?->utj_amount ?? 0)" required/><x-input-error class="mt-2" :messages="$errors->get('utj_amount')"/></div>
        <div><x-input-label for="dp_all_in" value="DP All In"/><x-text-input id="dp_all_in" name="dp_all_in" type="number" step="0.01" min="0" x-model="dpAllIn" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('dp_all_in')"/></div>
        <div><x-input-label for="dp_installment_count" value="Jumlah cicilan DP"/><x-text-input id="dp_installment_count" name="dp_installment_count" type="number" min="1" max="120" x-model="dpCount" class="mt-1 block w-full"/><x-input-error class="mt-2" :messages="$errors->get('dp_installment_count')"/></div>
        <div><x-input-label value="Nominal cicilan DP"/><div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold">Rp <span x-text="Number(installmentAmount).toLocaleString('id-ID', { maximumFractionDigits: 2 })"></span></div></div>
        <div><x-input-label for="payment_detail" value="Detail pembayaran"/><x-text-input id="payment_detail" name="payment_detail" class="mt-1 block w-full" :value="old('payment_detail', $psjbUtj?->payment_detail)"/><x-input-error class="mt-2" :messages="$errors->get('payment_detail')"/></div>
    </div></section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold text-gray-950">Data Kavling & Integrasi</h2><div class="mt-5 grid gap-5 md:grid-cols-3">
        <div><x-input-label for="land_area_m2" value="Luas tanah (m²)"/><x-text-input id="land_area_m2" name="land_area_m2" type="number" step="0.01" min="0" x-model="landArea" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('land_area_m2')"/></div>
        <div><x-input-label for="land_price_per_m2" value="Harga tanah / m²"/><x-text-input id="land_price_per_m2" name="land_price_per_m2" type="number" step="0.01" min="0" x-model="landPrice" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('land_price_per_m2')"/></div>
        <div><x-input-label value="Harga tanah total"/><div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold">Rp <span x-text="Number(landTotal).toLocaleString('id-ID', { maximumFractionDigits: 2 })"></span></div></div>
        <div><x-input-label for="external_transaction_id" value="ID transaksi V2"/><x-text-input id="external_transaction_id" name="external_transaction_id" class="mt-1 block w-full" :value="old('external_transaction_id', $psjbUtj?->external_transaction_id)"/><x-input-error class="mt-2" :messages="$errors->get('external_transaction_id')"/></div>
        <div><x-input-label for="external_status" value="Link status V2"/><x-text-input id="external_status" name="external_status" class="mt-1 block w-full" :value="old('external_status', $psjbUtj?->external_status)"/></div>
        <div><x-input-label for="external_method" value="Link method V2"/><x-text-input id="external_method" name="external_method" class="mt-1 block w-full" :value="old('external_method', $psjbUtj?->external_method)"/></div>
    </div></section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-bold text-gray-950">Status Proses</h2><div class="mt-5 grid gap-5 md:grid-cols-3">
        <div><x-input-label value="Status konsumen"/><div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold">{{ $psjbUtj?->salesCase?->consumer_status ?? 'LANJUT' }}</div><p class="mt-1 text-xs text-gray-500">Ubah melalui aksi status di luar form.</p></div>
        <div><x-input-label for="consumer_status_date" value="Tanggal status"/><x-text-input id="consumer_status_date" name="consumer_status_date" type="date" lang="id-ID" class="mt-1 block w-full" :value="old('consumer_status_date', $psjbUtj?->consumer_status_date?->format('Y-m-d'))"/></div>
        <div><x-input-label for="filing_status" value="Status pemberkasan"/><x-text-input id="filing_status" name="filing_status" class="mt-1 block w-full" placeholder="Internal / Done" :value="old('filing_status', $psjbUtj?->filing_status)"/></div>
        <div><x-input-label for="delay_category" value="Kategori keterlambatan"/><x-text-input id="delay_category" name="delay_category" class="mt-1 block w-full" :value="old('delay_category', $psjbUtj?->delay_category)"/></div>
        <div><x-input-label for="ongoing_delay_category" value="Kategori keterlambatan ongoing"/><x-text-input id="ongoing_delay_category" name="ongoing_delay_category" class="mt-1 block w-full" :value="old('ongoing_delay_category', $psjbUtj?->ongoing_delay_category)"/></div>
        <div class="md:col-span-3"><x-input-label for="filing_delay_reason" value="Keterangan terlambat pemberkasan"/><textarea id="filing_delay_reason" name="filing_delay_reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('filing_delay_reason', $psjbUtj?->filing_delay_reason) }}</textarea></div>
        <div class="md:col-span-3"><x-input-label for="notes" value="Keterangan"/><textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes', $psjbUtj?->notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('notes')"/></div>
    </div></section>
</div>
