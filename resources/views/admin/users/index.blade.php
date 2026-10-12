<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-medium text-orange">Pengaturan akses</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Pengguna</h1><p class="mt-1 text-sm text-muted">Kelola cabang, tim, role, dan akses pengguna dari satu daftar.</p></div>
            @can('users.create')<a href="{{ route('admin.users.create') }}" class="inline-flex min-h-10 items-center rounded-2xl bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white">Tambah pengguna</a>@endcan
        </div>
    </x-slot>

    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status') === 'users-branch-assigned')<x-ui.toast message="{{ session('count') }} pengguna dipindahkan ke cabang terpilih." />@endif
        @if (session('status') === 'users-deleted')<x-ui.toast message="{{ session('count') }} pengguna dihapus{{ session('skipped') ? ', '.session('skipped').' dilewati (diri sendiri / super admin terakhir)' : '' }}." />@endif

        <x-ui.card title="Filter pengguna" description="Cari pengguna berdasarkan identitas, cabang, atau role global." padding="p-5">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2"><label for="search" class="text-sm font-medium text-ink">Pencarian</label><input id="search" name="search" value="{{ $search }}" class="workspace-control mt-2 block w-full py-2.5 text-sm" placeholder="Nama atau email"></div>
                <div><label for="branch_id" class="text-sm font-medium text-ink">Cabang</label><select id="branch_id" name="branch_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div><label for="global_role_id" class="text-sm font-medium text-ink">Role global</label><select id="global_role_id" name="global_role_id" class="workspace-control mt-2 block w-full py-2.5 text-sm"><option value="">Semua role</option>@foreach($globalRoles as $role)<option value="{{ $role->id }}" @selected($globalRoleId === $role->id)>{{ $role->name }}</option>@endforeach</select></div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4"><button class="inline-flex min-h-10 items-center rounded-2xl bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-lime-dark">Terapkan filter</button><a href="{{ route('admin.users.index') }}" class="inline-flex min-h-10 items-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas">Reset</a></div>
            </form>
        </x-ui.card>

        <form method="POST" action="{{ route('admin.users.bulk') }}" x-data="{ action: 'assign-branch' }" onsubmit="return confirm('Terapkan aksi massal pada pengguna terpilih?')">
            @csrf
            <x-ui.card title="Daftar pengguna" description="Pilih pengguna untuk aksi massal. Aksi hapus tetap mengikuti aturan permission." padding="p-0">
                <x-slot:actions><div class="flex flex-wrap items-center gap-2"><select name="action" x-model="action" class="workspace-control py-2 text-xs"><option value="assign-branch">Pindahkan ke cabang</option><option value="delete">Hapus</option></select><select name="branch_id" x-cloak x-show="action === 'assign-branch'" class="workspace-control py-2 text-xs"><option value="">Pilih cabang tujuan</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select><button type="submit" class="inline-flex min-h-9 items-center rounded-xl bg-ink px-3 py-2 text-xs font-semibold text-white transition hover:bg-lime-dark">Terapkan</button></div></x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-ink text-left text-xs font-medium text-white"><tr><th class="w-10 px-6 py-4"><input type="checkbox" @click="$event.target.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(c => c.checked = $event.target.checked)" class="size-4 rounded border-line text-lime-dark"></th><th class="px-6 py-4">Pengguna</th><th class="px-6 py-4">Role global</th><th class="px-6 py-4">Cabang</th><th class="px-6 py-4">Tim</th><th class="px-6 py-4"></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @forelse($users as $user)
                                <tr class="transition hover:bg-lime-soft/30"><td class="px-6 py-4"><input type="checkbox" name="ids[]" value="{{ $user->id }}" class="size-4 rounded border-line text-lime-dark"></td><td class="px-6 py-4"><div class="font-semibold text-ink">{{ $user->name }}</div><div class="text-xs text-muted">{{ $user->email }}</div></td><td class="px-6 py-4 text-muted">{{ $user->globalRoles->pluck('name')->join(', ') ?: '-' }}</td><td class="px-6 py-4 text-muted">{{ $user->branch?->name ?? '-' }}</td><td class="px-6 py-4 text-muted">{{ $user->teams->pluck('name')->join(', ') ?: '-' }}</td><td class="whitespace-nowrap px-6 py-4 text-right">@can('users.update')<a href="{{ route('admin.users.edit', $user) }}" class="font-semibold text-lime-dark hover:underline">Kelola akses</a>@endcan @if(Auth::user()->hasGlobalRole('super_admin') && ! $user->hasGlobalRole('super_admin') && ! Auth::user()->is($user))<form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="mt-2">@csrf<button class="font-semibold text-orange hover:underline">Login sebagai</button></form>@endif</td></tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-14 text-center text-sm text-muted">Belum ada pengguna.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())<div class="border-t border-line px-6 py-4">{{ $users->links() }}</div>@endif
            </x-ui.card>
        </form>
    </div>
</x-app-layout>
