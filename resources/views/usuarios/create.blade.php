@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="user-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Seguridad / Alta</p>
            <h1 id="user-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nuevo usuario</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Crea la identidad de acceso y define desde el inicio qué funciones tendrá disponibles.</p>

            <form method="POST" action="{{ route('usuarios.store') }}" class="mt-8">
                @csrf
                @include('usuarios._form', ['user' => null, 'submitLabel' => 'Crear usuario'])
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="user-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-9v6m-3-3h6" /></svg></span>
            <h2 id="user-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Alta controlada</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">El usuario se crea activo. La contraseña nunca se muestra ni se registra en la auditoría.</p>
            <ul class="mt-6 grid gap-3 text-sm text-white">
                @foreach (['Usa una cuenta por persona', 'Asigna solo los roles necesarios', 'Entrega la clave por un canal seguro'] as $tip)
                    <li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
