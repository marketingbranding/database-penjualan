<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-medium text-orange">Pengaturan akses</p><h1 class="mt-1 text-xl font-semibold tracking-tight text-ink">Tambah pengguna</h1></div></x-slot>
    <div class="space-y-6 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto w-full max-w-3xl"><div class="workspace-card p-6"><form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-6">@csrf
        <div><x-input-label for="name" value="Nama"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
        <div><x-input-label for="email" value="Email"/><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required/><x-input-error class="mt-2" :messages="$errors->get('email')"/></div>
        <div><x-input-label for="branch_id" value="Cabang penugasan"/><select id="branch_id" name="branch_id" class="workspace-control mt-1 block w-full py-2.5 text-sm"><option value="">Belum ditentukan</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('branch_id')"/></div>
        <div><x-input-label for="password" value="Password"/><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('password')"/></div>
        <div><x-input-label for="password_confirmation" value="Konfirmasi password"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required/></div>
        <div class="flex justify-end"><x-primary-button>Buat pengguna</x-primary-button></div>
    </form></div></div></div>
</x-app-layout>
