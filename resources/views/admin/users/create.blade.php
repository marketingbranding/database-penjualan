<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">Tambah pengguna</h2></x-slot>
    <div class="py-10"><div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8"><div class="bg-white p-6 shadow-sm sm:rounded-lg"><form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-6">@csrf
        <div><x-input-label for="name" value="Nama"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
        <div><x-input-label for="email" value="Email"/><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required/><x-input-error class="mt-2" :messages="$errors->get('email')"/></div>
        <div><x-input-label for="password" value="Password"/><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required/><x-input-error class="mt-2" :messages="$errors->get('password')"/></div>
        <div><x-input-label for="password_confirmation" value="Konfirmasi password"/><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required/></div>
        <div class="flex justify-end"><x-primary-button>Buat pengguna</x-primary-button></div>
    </form></div></div></div>
</x-app-layout>
