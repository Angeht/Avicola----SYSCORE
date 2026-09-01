@extends('layouts.app')

@section('title', 'Editar rol')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="role-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Seguridad / Edición</p>
            <h1 id="role-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar rol</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Los cambios de permisos afectarán de inmediato a todos los usuarios asignados a <strong class="text-ink-950">{{ $role->nombre }}</strong>.</p>
            <form method="POST" action="{{ route('roles.update', $role) }}" class="mt-8">@csrf @method('PUT') @include('roles._form', ['submitLabel' => 'Actualizar rol'])</form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="role-status-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Rol #{{ $role->id }}</p>
            <h2 id="role-status-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Alcance actual</h2>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5"><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado</dt><dd class="mt-2"><x-status-badge :active="$role->activo" /></dd></div><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Permisos</dt><dd class="mt-1 font-display text-3xl font-bold text-white">{{ $role->permisos->count() }}</dd></div><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Actualizado</dt><dd class="mt-1 text-sm text-white">{{ $role->updated_at->translatedFormat('d M Y · H:i') }}</dd></div></dl>
        </aside>
    </div>
@endsection
