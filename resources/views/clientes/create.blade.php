@extends('layouts.app')

@section('title', 'Nuevo cliente')
@section('section', 'Catálogo de clientes')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="client-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Clientes / Alta</p>
            <h1 id="client-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nuevo cliente</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Registra los datos necesarios para identificar al comprador en ventas y cobranzas.</p>

            <form method="POST" action="{{ route('clientes.store') }}" class="mt-8">
                @csrf
                @include('clientes._form', ['client' => null, 'submitLabel' => 'Guardar cliente'])
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="client-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-9v6m-3-3h6" /></svg>
            </span>
            <h2 id="client-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Ficha comercial</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">El documento es opcional, pero si seleccionas un tipo debes ingresar también su número.</p>
            <ul class="mt-6 grid gap-3 text-sm text-white">
                @foreach (['Evita duplicar clientes', 'Usa un teléfono vigente', 'Añade referencias en observación'] as $tip)
                    <li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
