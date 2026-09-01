@props(['active'])

<span {{ $attributes->class([
    'inline-flex items-center gap-2 px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase',
    'bg-signal-soft text-signal' => $active,
    'bg-steel-300/30 text-steel-500' => ! $active,
]) }}>
    <span @class(['size-1.5', 'bg-signal' => $active, 'bg-steel-500' => ! $active]) aria-hidden="true"></span>
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
