@props([
    'href',
    'active' => false,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group relative flex min-h-11 items-center gap-3 border-l-2 px-3 py-2.5 text-sm font-semibold transition duration-200',
        'border-hazard bg-white/8 text-white' => $active,
        'border-transparent text-steel-300 hover:border-steel-500 hover:bg-white/5 hover:text-white' => ! $active,
    ]) }}
>
    <span @class([
        'grid size-8 shrink-0 place-items-center border transition',
        'border-hazard/50 bg-hazard/10 text-hazard' => $active,
        'border-white/10 bg-white/3 text-steel-300 group-hover:border-white/20 group-hover:text-white' => ! $active,
    ])>
        {{ $icon ?? '' }}
    </span>
    <span class="truncate">{{ $slot }}</span>
    @if ($active)
        <span class="ml-auto h-1.5 w-1.5 bg-hazard" aria-hidden="true"></span>
    @endif
</a>
