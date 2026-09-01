@extends('layouts.app')

@section('title', 'Roles y permisos')
@section('section', 'Usuarios y roles')

@section('content')
    <x-catalog-header
        eyebrow="Seguridad / Autorización"
        title="Roles y permisos"
        description="Define responsabilidades reutilizables y controla exactamente qué acciones puede ejecutar cada grupo."
        :count="$activeCount + $inactiveCount"
        count-label="Roles totales"
        :create-href="route('roles.create')"
        create-label="Nuevo rol"
    />

    <nav class="mt-5 flex gap-2 border-b border-line" aria-label="Administración de seguridad">
        <a href="{{ route('usuarios.index') }}" class="border-b-2 border-transparent px-4 py-3 font-mono text-[10px] font-semibold tracking-wider text-steel-500 uppercase transition hover:border-steel-300 hover:text-ink-950">Usuarios</a>
        <a href="{{ route('roles.index') }}" aria-current="page" class="border-b-2 border-signal px-4 py-3 font-mono text-[10px] font-semibold tracking-wider text-ink-950 uppercase">Roles y permisos</a>
    </nav>

    <section class="mt-6 grid grid-cols-2 gap-3 sm:max-w-2xl sm:grid-cols-3" aria-label="Resumen de roles">
        <div class="border-l-4 border-signal bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $activeCount }}</span><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Roles activos</p></div>
        <div class="border-l-4 border-steel-300 bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $inactiveCount }}</span><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Inactivos</p></div>
        <div class="col-span-2 border-l-4 border-hazard bg-paper px-4 py-3 shadow-sm sm:col-span-1"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $permissionCount }}</span><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Permisos</p></div>
    </section>

    <x-catalog-toolbar :action="route('roles.index')" :search="$search" :status="$status" placeholder="Buscar por rol, descripción o permiso" />

    @error('rol')<div class="mt-4 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $message }}</div>@enderror

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="roles-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Matriz / Autorización</p><h2 id="roles-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Roles configurados</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $roles->total() }} encontrados</span></div>

        @if ($roles->isEmpty())
            <x-empty-state title="No encontramos roles" description="Ajusta los filtros o crea un rol con los permisos necesarios." :action-href="route('roles.create')" action-label="Crear rol"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5h16v14H4V5Zm4 4h8M8 13h5" /><circle cx="17" cy="15" r="2" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[940px] border-collapse text-left" aria-label="Roles configurados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Rol</th><th scope="col" class="px-6 py-3 text-center font-semibold">Usuarios</th><th scope="col" class="px-6 py-3 text-center font-semibold">Permisos</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($roles as $role)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><div class="flex items-start gap-3"><span class="mt-1 size-2 shrink-0 {{ $role->nombre === 'ADMINISTRADOR' ? 'bg-hazard' : 'bg-signal' }}"></span><div><p class="font-display font-bold tracking-wide text-ink-950 uppercase">{{ $role->nombre }}</p><p class="mt-1 max-w-xl text-xs leading-5 text-steel-500">{{ $role->descripcion ?: 'Sin descripción operativa.' }}</p></div></div></td>
                                <td class="px-6 py-4 text-center font-mono text-sm font-semibold text-ink-700">{{ $role->usuarios_count }}</td>
                                <td class="px-6 py-4 text-center font-mono text-sm font-semibold text-ink-700">{{ $role->nombre === 'ADMINISTRADOR' ? $permissionCount : $role->permisos_count }}</td>
                                <td class="px-6 py-4"><x-status-badge :active="$role->activo" /></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><a href="{{ route('roles.edit', $role) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Editar permisos</a>@if ($role->nombre === 'ADMINISTRADOR')<span class="inline-flex min-h-9 items-center border border-hazard/30 bg-hazard-soft px-3 font-mono text-[8px] font-semibold text-ink-700 uppercase">Protegido</span>@elseif ($role->activo)<form method="POST" action="{{ route('roles.activacion.destroy', $role) }}" data-confirm="¿Desactivar el rol {{ $role->nombre }}? Sus usuarios conservarán la asignación, pero perderán estos permisos.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center border border-danger/25 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Desactivar</button></form>@else<form method="POST" action="{{ route('roles.activacion.store', $role) }}" data-confirm="¿Activar nuevamente el rol {{ $role->nombre }}?">@csrf<button type="submit" class="inline-flex min-h-9 items-center border border-signal/30 px-3 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Activar</button></form>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($roles as $role)
                    <article class="p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-display font-bold tracking-wide text-ink-950 uppercase">{{ $role->nombre }}</p><p class="mt-1 text-xs leading-5 text-steel-500">{{ $role->descripcion ?: 'Sin descripción operativa.' }}</p></div><x-status-badge :active="$role->activo" /></div><dl class="mt-4 grid grid-cols-2 gap-3 border-y border-line py-3 text-center"><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Usuarios</dt><dd class="mt-1 font-display text-2xl font-bold text-ink-950">{{ $role->usuarios_count }}</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Permisos</dt><dd class="mt-1 font-display text-2xl font-bold text-ink-950">{{ $role->nombre === 'ADMINISTRADOR' ? $permissionCount : $role->permisos_count }}</dd></div></dl><div class="mt-4 grid grid-cols-2 gap-2"><a href="{{ route('roles.edit', $role) }}" class="inline-flex min-h-10 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Editar</a>@if ($role->nombre === 'ADMINISTRADOR')<span class="inline-flex min-h-10 items-center justify-center border border-hazard/30 bg-hazard-soft font-mono text-[8px] font-semibold text-ink-700 uppercase">Protegido</span>@else<form method="POST" action="{{ $role->activo ? route('roles.activacion.destroy', $role) : route('roles.activacion.store', $role) }}" data-confirm="¿{{ $role->activo ? 'Desactivar' : 'Activar' }} el rol {{ $role->nombre }}?">@csrf @if ($role->activo) @method('DELETE') @endif<button type="submit" class="min-h-10 w-full border font-mono text-[9px] font-semibold tracking-wider uppercase {{ $role->activo ? 'border-danger/30 text-danger' : 'border-signal/30 text-signal' }}">{{ $role->activo ? 'Desactivar' : 'Activar' }}</button></form>@endif</div></article>
                @endforeach
            </div>

            <x-catalog-pagination :paginator="$roles" />
        @endif
    </section>
@endsection
