@extends('layouts.app')

@section('title', 'Nuevo tipo de jaba')
@section('section', 'Jabas y taras')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="crate-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Jabas / Alta</p>
            <h1 id="crate-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nueva jaba</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Registra el recipiente y su peso vacío para que los pesajes calculen correctamente el peso neto.</p>
            <form method="POST" action="{{ route('tipos-jaba.store') }}" class="mt-8">
                @csrf
                @include('tipos-jaba._form', ['crateType' => null, 'submitLabel' => 'Guardar tipo de jaba'])
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="crate-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16l-2 12H6L4 7Z" /><path d="M8 7V4h8v3M8 11h8m-9 4h10" /></svg></span>
            <h2 id="crate-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Tara verificada</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Pesa una jaba vacía en la misma balanza usada para las operaciones.</p>
            <ol class="mt-6 grid gap-3 text-sm text-white">
                @foreach (['Limpia y vacía la jaba', 'Registra el peso con 3 decimales', 'Distingue tipos con nombres claros'] as $tip)
                    <li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="grid size-5 shrink-0 place-items-center bg-hazard font-mono text-[9px] font-bold text-ink-950">{{ $loop->iteration }}</span>{{ $tip }}</li>
                @endforeach
            </ol>
        </aside>
    </div>
@endsection
