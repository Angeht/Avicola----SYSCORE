@extends('layouts.app')

@section('title', 'Usuarios')
@section('section', 'Usuarios y roles')

@section('content')
    <x-catalog-header
        eyebrow="Seguridad / Accesos"
        title="Usuarios"
        description="Administra identidades, estados, roles y credenciales de acceso al sistema."
        :count="$activeCount + $inactiveCount"
        count-label="Cuentas totales"
        :create-href="route('usuarios.create')"
        create-label="Nuevo usuario"
    />

    <nav class="mt-5 flex gap-2 border-b border-line" aria-label="Administración de seguridad">
        <a href="{{ route('usuarios.index') }}" aria-current="page" class="border-b-2 border-signal px-4 py-3 font-mono text-[10px] font-semibold tracking-wider text-ink-950 uppercase">Usuarios</a>
        <a href="{{ route('roles.index') }}" class="border-b-2 border-transparent px-4 py-3 font-mono text-[10px] font-semibold tracking-wider text-steel-500 uppercase transition hover:border-steel-300 hover:text-ink-950">Roles y permisos</a>
    </nav>

    <section class="mt-6 grid grid-cols-2 gap-3 sm:max-w-2xl sm:grid-cols-3" aria-label="Resumen de usuarios">
        <div class="border-l-4 border-signal bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $activeCount }}</span><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Activos</p></div>
        <div class="border-l-4 border-steel-300 bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $inactiveCount }}</span><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Inactivos</p></div>
        <div class="col-span-2 border-l-4 border-hazard bg-paper px-4 py-3 shadow-sm sm:col-span-1"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $administratorCount }}</span><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Administradores</p></div>
    </section>

    <x-catalog-toolbar :action="route('usuarios.index')" :search="$search" :status="$status" placeholder="Buscar por nombre, usuario o rol" />

    @error('usuario')<div class="mt-4 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $message }}</div>@enderror

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="users-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Directorio / Seguridad</p><h2 id="users-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Cuentas registradas</h2></div>
            <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $users->total() }} encontradas</span>
        </div>

        @if ($users->isEmpty())
            <x-empty-state title="No encontramos usuarios" description="Ajusta los filtros o registra una nueva cuenta operativa." :action-href="route('usuarios.create')" action-label="Crear usuario"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-9v6m-3-3h6" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[1120px] border-collapse text-left" aria-label="Usuarios registrados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Usuario</th><th scope="col" class="px-6 py-3 font-semibold">Roles</th><th scope="col" class="px-6 py-3 font-semibold">Último acceso</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $user)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid size-9 shrink-0 place-items-center bg-ink-950 font-display text-sm font-bold text-hazard">{{ $user->iniciales() }}</span><div><p class="font-semibold text-ink-950">{{ $user->nombreCompleto() }}</p><p class="mt-1 font-mono text-[9px] text-steel-500">{{ '@'.$user->usuario }}</p></div></div></td>
                                <td class="px-6 py-4"><div class="flex max-w-sm flex-wrap gap-1.5">@forelse ($user->roles as $role)<span class="border px-2 py-1 font-mono text-[8px] font-semibold uppercase {{ $role->activo ? 'border-signal/25 bg-signal-soft text-signal' : 'border-line bg-canvas text-steel-500 line-through' }}">{{ $role->nombre }}</span>@empty<span class="text-xs text-danger">Sin roles</span>@endforelse</div></td>
                                <td class="px-6 py-4 text-sm text-ink-700">{{ $user->ultimo_acceso?->translatedFormat('d M Y · H:i') ?: 'Nunca ingresó' }}</td>
                                <td class="px-6 py-4"><x-status-badge :active="$user->activo" /></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><a href="{{ route('usuarios.edit', $user) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Editar</a><a href="{{ route('usuarios.password.edit', $user) }}" class="inline-flex min-h-9 items-center border border-hazard/35 px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:bg-hazard">Clave</a>@if ($user->getKey() === $currentUserId)<span class="inline-flex min-h-9 items-center px-3 font-mono text-[8px] text-steel-500 uppercase">Sesión actual</span>@elseif ($user->activo)<form method="POST" action="{{ route('usuarios.activacion.destroy', $user) }}" data-confirm="¿Desactivar a {{ $user->usuario }}? Su sesión perderá acceso y su historial se conservará.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center border border-danger/25 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Desactivar</button></form>@else<form method="POST" action="{{ route('usuarios.activacion.store', $user) }}" data-confirm="¿Activar nuevamente a {{ $user->usuario }}?">@csrf<button type="submit" class="inline-flex min-h-9 items-center border border-signal/30 px-3 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Activar</button></form>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line lg:hidden">
                @foreach ($users as $user)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="flex min-w-0 items-center gap-3"><span class="grid size-10 shrink-0 place-items-center bg-ink-950 font-display font-bold text-hazard">{{ $user->iniciales() }}</span><div class="min-w-0"><p class="truncate font-semibold text-ink-950">{{ $user->nombreCompleto() }}</p><p class="mt-1 font-mono text-[9px] text-steel-500">{{ '@'.$user->usuario }}</p></div></div><x-status-badge :active="$user->activo" /></div>
                        <div class="mt-4 flex flex-wrap gap-1.5">@forelse ($user->roles as $role)<span class="border border-line bg-canvas px-2 py-1 font-mono text-[8px] text-ink-700 uppercase">{{ $role->nombre }}</span>@empty<span class="text-xs text-danger">Sin roles</span>@endforelse</div>
                        <p class="mt-4 border-t border-line pt-3 text-xs text-steel-500">Último acceso: <span class="text-ink-700">{{ $user->ultimo_acceso?->translatedFormat('d M Y · H:i') ?: 'Nunca' }}</span></p>
                        <div class="mt-4 grid grid-cols-2 gap-2"><a href="{{ route('usuarios.edit', $user) }}" class="inline-flex min-h-10 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Editar</a><a href="{{ route('usuarios.password.edit', $user) }}" class="inline-flex min-h-10 items-center justify-center border border-hazard/40 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Restablecer clave</a>@if ($user->getKey() !== $currentUserId)<form method="POST" action="{{ $user->activo ? route('usuarios.activacion.destroy', $user) : route('usuarios.activacion.store', $user) }}" class="col-span-2" data-confirm="¿{{ $user->activo ? 'Desactivar' : 'Activar' }} a {{ $user->usuario }}?">@csrf @if ($user->activo) @method('DELETE') @endif<button type="submit" class="min-h-10 w-full border font-mono text-[9px] font-semibold tracking-wider uppercase {{ $user->activo ? 'border-danger/30 text-danger' : 'border-signal/30 text-signal' }}">{{ $user->activo ? 'Desactivar usuario' : 'Activar usuario' }}</button></form>@endif</div>
                    </article>
                @endforeach
            </div>

            <x-catalog-pagination :paginator="$users" />
        @endif
    </section>
@endsection
