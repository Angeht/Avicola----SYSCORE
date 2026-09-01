@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'neutral',
])

@php
    $toneClasses = match ($tone) {
        'hazard' => 'bg-hazard text-ink-950 border-hazard',
        'signal' => 'bg-signal text-white border-signal',
        'danger' => 'bg-danger text-white border-danger',
        default => 'bg-ink-900 text-white border-ink-900',
    };
@endphp

<article {{ $attributes->class(['panel-cut corner-frame border border-line bg-paper p-5 shadow-panel']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="font-mono text-[10px] font-semibold tracking-[0.18em] text-steel-500 uppercase">{{ $label }}</p>
            <p class="mt-4 truncate font-display text-4xl leading-none font-bold tracking-tight text-ink-950">{{ $value }}</p>
        </div>
        <span class="grid size-10 shrink-0 place-items-center border {{ $toneClasses }}">
            {{ $icon ?? '' }}
        </span>
    </div>
    @if ($meta)
        <p class="mt-5 border-t border-line pt-3 text-xs font-medium text-steel-500">{{ $meta }}</p>
    @endif
</article>
