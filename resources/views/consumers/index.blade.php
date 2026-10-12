<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-orange">Database penjualan</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Data konsumen</h1>
                <p class="mt-1 text-sm text-muted">Kelola data konsumen dan reservasi aktif dalam satu ruang kerja.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('consumers.export')
                    <a href="{{ route('consumers.export', request()->query()) }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Export Excel</a>
                @endcan
                @can('create', App\Models\SalesCase::class)
                    <a href="{{ route('consumers.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Tambah konsumen</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-ui.toast message="Data konsumen berhasil diperbarui." />
        @endif

        <x-ui.card title="Filter konsumen" description="Cari berdasarkan konsumen, NIK, kavling, atau cakupan penjualan." padding="p-5">
            <form method="GET" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @if (Auth::user()->canAccessAllBranches())
                    <div>
                        <label for="branch_id" class="text-sm font-medium text-ink">Cabang</label>
                        <select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm">
                            <option value="">Semua cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="text-sm font-medium text-ink">Cabang</label>
                        <div class="workspace-control mt-2 flex min-h-11 items-center bg-canvas text-sm font-semibold text-muted">{{ Auth::user()->branch?->name ?? 'Belum diatur' }}</div>
                    </div>
                @endif
                <div>
                    <label for="project_id" class="text-sm font-medium text-ink">Proyek</label>
                    <select id="project_id" name="project_id" class="workspace-control mt-2 block w-full py-2.5 text-sm">
                        <option value="">Semua proyek</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_method" class="text-sm font-medium text-ink">Pembayaran</label>
                    <select id="payment_method" name="payment_method" class="workspace-control mt-2 block w-full py-2.5 text-sm">
                        <option value="">Semua metode</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->value }}" @selected($paymentMethod === $method)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="search" class="text-sm font-medium text-ink">Cari</label>
                    <input id="search" name="search" value="{{ $search }}" class="workspace-control mt-2 block w-full py-2.5 text-sm" placeholder="Nama, NIK, atau kavling">
                </div>
                <div class="flex items-end gap-2">
                    <button class="inline-flex min-h-10 flex-1 items-center justify-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark">Cari</button>
                    <a href="{{ route('consumers.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Reset</a>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Reservasi aktif" description="{{ number_format($salesCases->total()) }} konsumen ditemukan." padding="p-0">
            <x-slot:actions><span class="rounded-xl bg-lime-soft px-3 py-2 text-xs font-semibold text-lime-dark">Halaman {{ $salesCases->currentPage() }} / {{ $salesCases->lastPage() }}</span></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="px-6 py-4">Konsumen</th><th class="px-6 py-4">Kavling</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Lahir / umur</th><th class="px-6 py-4">Pekerjaan</th><th class="px-6 py-4">Kontak</th><th class="px-6 py-4">Metode</th><th class="px-6 py-4">Promo</th><th class="px-6 py-4"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($salesCases as $case)
                            <tr class="transition hover:bg-lime-soft/30">
                                <td class="px-6 py-4"><div class="font-semibold text-ink">{{ $case->consumer->nama_konsumen }}</div><div class="mt-1 text-xs text-muted">{{ $case->consumer->no_ktp }}</div></td>
                                <td class="whitespace-nowrap px-6 py-4"><div class="font-semibold text-ink">{{ $case->lot->project->name }}-{{ $case->lot->code }}</div><div class="mt-1 text-xs text-muted">{{ $case->lot->project->branch->name }}</div></td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-[9px] bg-lime-soft px-2.5 py-1 text-xs font-medium text-lime-dark">{{ $case->lot->status->label() }}</span></td>
                                <td class="whitespace-nowrap px-6 py-4 text-muted">{{ $case->consumer->tanggal_lahir->translatedFormat('d M Y') }}<div class="mt-1 text-xs">{{ $case->consumer->age }} tahun</div></td>
                                <td class="px-6 py-4 text-muted">{{ $case->consumer->pekerjaan }}</td>
                                <td class="px-6 py-4 text-muted">{{ $case->consumer->no_hp }}</td>
                                <td class="px-6 py-4"><span class="inline-flex whitespace-nowrap rounded-[9px] bg-orange/20 px-2.5 py-1 text-xs font-medium text-ink">{{ $case->payment_method->label() }}</span></td>
                                <td class="px-6 py-4 text-muted">@if($case->promotion)<span class="font-semibold text-ink">{{ $case->promotion->promo_id }}</span><div class="mt-1 text-xs">{{ $case->promotion->name }}</div>@else<span class="text-muted">-</span>@endif</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right"><a href="{{ route('consumers.show', $case) }}" class="font-semibold text-lime-dark hover:underline">Detail</a>@can('update', $case)<a href="{{ route('consumers.edit', $case) }}" class="ml-3 font-semibold text-orange hover:underline">Edit</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-14 text-center text-sm text-muted">Belum ada data konsumen sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($salesCases->hasPages())<div class="border-t border-line px-6 py-4">{{ $salesCases->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-app-layout>
