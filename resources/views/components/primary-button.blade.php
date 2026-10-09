<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg border border-transparent bg-marison-terracotta-600 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-marison-terracotta-700 focus:outline-none focus:ring-2 focus:ring-marison-terracotta-600 focus:ring-offset-2 active:bg-marison-terracotta-700']) }}>
    {{ $slot }}
</button>
