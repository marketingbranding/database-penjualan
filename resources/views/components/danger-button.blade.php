<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center rounded-2xl border border-danger/30 bg-danger/10 px-4 py-2 text-sm font-semibold text-danger transition hover:bg-danger hover:text-inverse-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger']) }}>
    {{ $slot }}
</button>
