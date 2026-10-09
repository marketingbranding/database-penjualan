<x-app-layout>
    <x-slot name="header"><div class="flex items-center justify-between gap-4"><h2 class="text-xl font-semibold leading-tight text-gray-800">Pengguna</h2>@can('users.create')<a href="{{ route('admin.users.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Tambah pengguna</a>@endcan</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('status') === 'users-branch-assigned')
            <div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('count') }} pengguna dipindahkan ke cabang terpilih.</div>
        @endif
        @if (session('status') === 'users-deleted')
            <div class="mb-6 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700">
                {{ session('count') }} pengguna dihapus
                @if (session('skipped'))
                    , {{ session('skipped') }} dilewati (diri sendiri / super admin terakhir)
                @endif
                .
            </div>
        @endif

        <form method="GET" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2"><label class="text-xs font-bold uppercase tracking-wider text-gray-500">Pencarian</label><input name="search" value="{{ $search }}" class="mt-2 block w-full rounded-md border-gray-300" placeholder="Nama atau email"></div>
            <div><label class="text-xs font-bold uppercase tracking-wider text-gray-500">Cabang</label><select name="branch_id" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div><label class="text-xs font-bold uppercase tracking-wider text-gray-500">Role global</label><select name="global_role_id" class="mt-2 block w-full rounded-md border-gray-300"><option value="">Semua role</option>@foreach($globalRoles as $role)<option value="{{ $role->id }}" @selected($globalRoleId === $role->id)>{{ $role->name }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2 lg:col-span-4"><button class="rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">Reset</a></div>
        </form>

        <form method="POST" action="{{ route('admin.users.bulk') }}" x-data="{ action: 'assign-branch' }" onsubmit="return confirm('Terapkan aksi massal pada pengguna terpilih?')">
            @csrf
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <label class="text-sm font-semibold text-gray-700">Aksi massal</label>
                <select name="action" x-model="action" class="rounded-md border-gray-300 text-sm"><option value="assign-branch">Pindahkan ke cabang</option><option value="delete">Hapus</option></select>
                <select name="branch_id" x-cloak x-show="action === 'assign-branch'" class="rounded-md border-gray-300 text-sm"><option value="">Pilih cabang tujuan</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <button type="submit" class="rounded-md bg-gray-950 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg"><div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="w-10 px-6 py-3"><input type="checkbox" @click="$event.target.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(c => c.checked = $event.target.checked)" class="rounded border-gray-300 text-indigo-600"></th><th class="px-6 py-3">Pengguna</th><th class="px-6 py-3">Role global</th><th class="px-6 py-3">Cabang</th><th class="px-6 py-3">Tim</th><th class="px-6 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $user)
                            <tr><td class="px-6 py-4"><input type="checkbox" name="ids[]" value="{{ $user->id }}" class="rounded border-gray-300 text-indigo-600"></td><td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $user->name }}</div><div class="text-gray-500">{{ $user->email }}</div></td><td class="px-6 py-4 text-gray-600">{{ $user->globalRoles->pluck('name')->join(', ') ?: '-' }}</td><td class="px-6 py-4 text-gray-600">{{ $user->branch?->name ?? '-' }}</td><td class="px-6 py-4 text-gray-600">{{ $user->teams->pluck('name')->join(', ') ?: '-' }}</td><td class="whitespace-nowrap px-6 py-4 text-right">@can('users.update')<a href="{{ route('admin.users.edit', $user) }}" class="font-semibold text-indigo-600 hover:text-indigo-900">Kelola akses</a>@endcan @if(Auth::user()->hasGlobalRole('super_admin') && ! $user->hasGlobalRole('super_admin') && ! Auth::user()->is($user))<form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="mt-2">@csrf<button class="font-semibold text-amber-700 hover:text-amber-900">Login sebagai</button></form>@endif</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Belum ada pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </form>
        <div class="mt-6">{{ $users->links() }}</div>
    </div></div>
</x-app-layout>
