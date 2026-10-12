<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center rounded-2xl border border-transparent bg-lime px-4 py-2 text-sm font-semibold text-ink transition hover:bg-lime-dark hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-dark active:bg-lime-dark']) }}>
    {{ $slot }}
</button>
