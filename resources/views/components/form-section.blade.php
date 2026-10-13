@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'module-form-section']) }}>
    @if ($title || $description)
        <header>
            @if ($title)
                <h2>{{ $title }}</h2>
            @endif
            @if ($description)
                <p>{{ $description }}</p>
            @endif
        </header>
    @endif
    <div class="module-form-section-fields">{{ $slot }}</div>
</section>
