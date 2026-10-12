<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-10 items-center justify-center rounded-2xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink transition hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-dark disabled:opacity-50']) }}>
    {{ $slot }}
</button>
