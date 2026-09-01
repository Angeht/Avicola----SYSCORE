@extends('layouts.app')

@section('title', 'Editar tipo de jaba')
@section('section', 'Jabas y taras')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="crate-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Jabas / Calibración</p>
            <h1 id="crate-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar jaba</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Actualiza la tara y la identificación de <strong class="text-ink-950">{{ $crateType->nombre }}</strong>.</p>
            <form method="POST" action="{{ route('tipos-jaba.update', $crateType) }}" class="mt-8">
                @csrf
                @method('PUT')
                @include('tipos-jaba._form', ['crateType' => $crateType, 'submitLabel' => 'Actualizar tipo de jaba'])
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="crate-status-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Registro #{{ $crateType->id }}</p>
            <h2 id="crate-status-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Control de tara</h2>
            <dl class="mt-7 grid gap-5 border-t border-white/10 pt-5">
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado actual</dt><dd class="mt-2"><x-status-badge :active="$crateType->activo" /></dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Tara registrada</dt><dd class="mt-1 font-mono text-3xl font-bold text-white">{{ number_format((float) $crateType->tara_referencial_kg, 3) }} <span class="text-sm text-hazard">kg</span></dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Última actualización</dt><dd class="mt-1 text-sm text-white">{{ $crateType->updated_at->translatedFormat('d M Y · H:i') }}</dd></div>
            </dl>
            @if ((float) $crateType->tara_referencial_kg <= 0)
                <p class="mt-7 border-l-2 border-danger bg-danger/10 px-4 py-3 text-xs leading-5 text-white">Esta jaba aún no está lista para pesajes reales. Registra una tara mayor que cero.</p>
            @endif
        </aside>
    </div>
@endsection
