@extends('layouts.app')

@section('title', 'Nuevo rol')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="role-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Seguridad / Roles</p>
            <h1 id="role-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nuevo rol</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Agrupa permisos bajo una responsabilidad clara para asignarlos después a los usuarios.</p>
            <form method="POST" action="{{ route('roles.store') }}" class="mt-8">@csrf @include('roles._form', ['role' => null, 'submitLabel' => 'Crear rol'])</form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="role-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5h16v14H4V5Zm4 4h8M8 13h5" /><circle cx="17" cy="15" r="2" /></svg></span>
            <h2 id="role-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Mínimo privilegio</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Crea roles por función laboral, no por persona. Así las altas y cambios se mantienen predecibles.</p>
            <ul class="mt-6 grid gap-3 text-sm text-white">@foreach (['Un propósito por rol', 'Solo permisos necesarios', 'Revisa el alcance antes de asignar'] as $tip)<li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>@endforeach</ul>
        </aside>
    </div>
@endsection
