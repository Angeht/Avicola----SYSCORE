@props([
    'title',
    'description',
    'actionHref' => null,
    'actionLabel' => null,
])

<div {{ $attributes->class(['px-6 py-14 text-center']) }}>
    <span class="mx-auto grid size-12 place-items-center border border-line bg-canvas text-steel-500">
        {{ $icon ?? '—' }}
    </span>
    <p class="mt-4 font-display text-xl font-bold text-ink-950 uppercase">{{ $title }}</p>
    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-steel-500">{{ $description }}</p>
    @if ($actionHref && $actionLabel)
        <a href="{{ $actionHref }}" class="mt-5 inline-flex min-h-10 items-center justify-center bg-ink-950 px-5 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">{{ $actionLabel }}</a>
    @endif
</div>
