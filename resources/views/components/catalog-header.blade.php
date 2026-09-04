@props([
    'eyebrow',
    'title',
    'description',
    'count',
    'countLabel',
    'createHref' => null,
    'createLabel',
    'secondaryHref' => null,
    'secondaryLabel' => null,
])

<header {{ $attributes->class(['reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between']) }}>
    <div class="max-w-3xl">
        <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">{{ $eyebrow }}</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">{{ $title }}</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $description }}</p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-stretch sm:justify-end">
        <div class="flex min-h-12 items-center gap-3 border border-line bg-paper px-4 shadow-sm">
            <span class="font-display text-3xl font-extrabold text-ink-950">{{ $count }}</span>
            <span class="font-mono text-[9px] leading-4 tracking-wider text-steel-500 uppercase">{{ $countLabel }}</span>
        </div>
        @if ($secondaryHref)
            <a href="{{ $secondaryHref }}" class="inline-flex min-h-12 items-center justify-center gap-3 border border-ink-950 px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">
                {{ $secondaryLabel }}
                <span aria-hidden="true">→</span>
            </a>
        @endif
        @if ($createHref)
            <a href="{{ $createHref }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">
                {{ $createLabel }}
                <span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950 transition group-hover:rotate-90" aria-hidden="true">+</span>
            </a>
        @endif
    </div>
</header>
