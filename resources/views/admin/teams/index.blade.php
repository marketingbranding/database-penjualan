<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Tim</h2>
            @can('teams.create')
                <a href="{{ route('admin.teams.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Tambah tim</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr><th class="px-6 py-3">Tim</th><th class="px-6 py-3">Induk</th><th class="px-6 py-3">Anggota</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($teams as $team)
                                <tr>
                                    <td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $team->name }}</div><div class="text-gray-500">{{ $team->code }}</div></td>
                                    <td class="px-6 py-4 text-gray-600">{{ $team->parent?->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $team->users_count }}</td>
                                    <td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $team->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $team->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="px-6 py-4 text-right">@can('teams.update')<a href="{{ route('admin.teams.edit', $team) }}" class="font-semibold text-indigo-600 hover:text-indigo-900">Edit</a>@endcan</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">Belum ada tim.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-6">{{ $teams->links() }}</div>
        </div>
    </div>
</x-app-layout>
