@props([
    'compact' => false,
    'inverse' => false,
    'name' => config('app.name', 'Avícola'),
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span @class([
        'relative grid size-11 shrink-0 place-items-center overflow-hidden border-2',
        'border-hazard bg-hazard text-ink-950' => ! $inverse,
        'border-hazard bg-ink-950 text-hazard' => $inverse,
    ]) aria-hidden="true">
        <svg viewBox="0 0 40 40" class="size-8" fill="none">
            <path d="M9 26.5c0-6.2 4.9-11.2 11-11.2h2.2c4.9 0 8.8 4 8.8 8.8v1.4H9v1Z" fill="currentColor" />
            <path d="M15.2 15.8c.2-4 2.5-6.6 6.2-6.6 2.8 0 5 1.5 6 4-1.8-.3-3.4.2-4.6 1.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" />
            <path d="M11 27v4m8-4v4m-10 0h4m4 0h4" stroke="currentColor" stroke-width="2.2" stroke-linecap="square" />
            <circle cx="23.4" cy="12.4" r="1" fill="currentColor" />
            <path d="M28.2 14.4 33 16l-4.8 1.5" fill="currentColor" />
        </svg>
        <span class="absolute right-0 bottom-0 h-2.5 w-2.5 bg-ink-950"></span>
    </span>

    @unless ($compact)
        <span class="min-w-0">
            <span @class([
                'block truncate font-display text-xl leading-none font-extrabold tracking-[0.08em] uppercase',
                'text-ink-950' => ! $inverse,
                'text-paper' => $inverse,
            ])>{{ $name }}</span>
            <span @class([
                'mt-1 block font-mono text-[9px] tracking-[0.2em] uppercase',
                'text-steel-500' => ! $inverse,
                'text-steel-300' => $inverse,
            ])>Control operativo</span>
        </span>
    @endunless
</div>
