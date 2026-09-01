@extends('layouts.app')

@section('title', 'Editar usuario')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="user-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Seguridad / Edición</p>
            <h1 id="user-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar usuario</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Actualiza la identidad y los roles de <strong class="text-ink-950">{{ $user->nombreCompleto() }}</strong>.</p>

            <form method="POST" action="{{ route('usuarios.update', $user) }}" class="mt-8">
                @csrf
                @method('PUT')
                @include('usuarios._form', ['submitLabel' => 'Actualizar usuario'])
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="user-status-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Usuario #{{ $user->id }}</p>
            <h2 id="user-status-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Control de acceso</h2>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5">
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado actual</dt><dd class="mt-2"><x-status-badge :active="$user->activo" /></dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Último acceso</dt><dd class="mt-1 text-sm text-white">{{ $user->ultimo_acceso?->translatedFormat('d M Y · H:i') ?: 'Nunca ingresó' }}</dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Roles actuales</dt><dd class="mt-1 text-sm text-white">{{ $user->roles->pluck('nombre')->join(' · ') ?: 'Sin roles' }}</dd></div>
                @if ($user->esAdministrador())
                    <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">PIN administrativo</dt><dd class="mt-2"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ filled($user->pin_autorizacion_hash) ? 'border-signal/30 bg-signal/10 text-signal' : 'border-hazard/40 bg-hazard/10 text-hazard' }}">{{ filled($user->pin_autorizacion_hash) ? 'Configurado' : 'Pendiente' }}</span></dd></div>
                @endif
            </dl>
            <div class="mt-7 grid gap-3">
                @if ($user->esAdministrador())
                    <a href="{{ route('usuarios.pin-autorizacion.edit', $user) }}" class="inline-flex min-h-11 w-full items-center justify-center border border-danger/50 bg-danger/10 px-4 font-display text-xs font-bold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">{{ filled($user->pin_autorizacion_hash) ? 'Cambiar PIN administrativo' : 'Configurar PIN administrativo' }}</a>
                @endif
                <a href="{{ route('usuarios.password.edit', $user) }}" class="inline-flex min-h-11 w-full items-center justify-center border border-hazard/50 bg-hazard/10 px-4 font-display text-xs font-bold tracking-wider text-hazard uppercase transition hover:bg-hazard hover:text-ink-950">Restablecer contraseña</a>
            </div>
        </aside>
    </div>
@endsection
