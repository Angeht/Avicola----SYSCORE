@extends('layouts.app')

@section('title', 'Historial de precio')
@section('section', 'Precio del día')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 4, ',', '.');
        $currentVersion = $priceDay->versiones->first();
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('precios-dia.index', ['fecha' => $priceDay->fecha->toDateString()]) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver al tarifario</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Historial / {{ $priceDay->fecha->format('d.m.Y') }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">{{ $priceDay->producto->nombre }}</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Consulta cada versión registrada durante la jornada y el responsable de la modificación.</p>
        </div>
        @if ($priceDay->fecha->isToday() && $priceDay->producto->activo)
            <a href="{{ route('precios-dia.create', ['producto' => $priceDay->producto_id]) }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">
                Registrar cambio
                <span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950 transition group-hover:rotate-90" aria-hidden="true">+</span>
            </a>
        @endif
    </header>

    <section class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]" aria-label="Resumen del precio">
        <div class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Precio vigente</p>
            <div class="mt-3 flex items-end gap-3">
                <span class="font-display text-5xl font-extrabold sm:text-6xl">{{ $currentVersion ? $money($currentVersion->precio_kg) : 'Sin precio' }}</span>
                @if ($currentVersion)<span class="pb-2 text-sm text-steel-300">por kg</span>@endif
            </div>
            <p class="mt-4 text-sm text-steel-300">{{ $currentVersion ? 'Vigente desde las '.$currentVersion->vigente_desde->format('H:i:s') : 'No se encontró una versión vigente.' }}</p>
        </div>
        <div class="corner-frame border border-line bg-paper p-6 shadow-sm">
            <p class="font-mono text-[9px] tracking-[0.18em] text-signal uppercase">Control de versiones</p>
            <span class="mt-3 block font-display text-5xl font-extrabold text-ink-950">{{ $priceDay->versiones->count() }}</span>
            <p class="mt-2 text-sm text-steel-500">{{ $priceDay->versiones->count() === 1 ? 'Registro inmutable en la jornada.' : 'Cambios auditables conservados en la jornada.' }}</p>
        </div>
    </section>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="price-versions-title">
        <div class="border-b border-line px-5 py-5 sm:px-6">
            <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Auditoría / Versiones</p>
            <h2 id="price-versions-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Secuencia de cambios</h2>
        </div>

        <ol class="divide-y divide-line">
            @foreach ($priceDay->versiones as $index => $version)
                <li class="grid gap-4 p-5 sm:grid-cols-[80px_minmax(0,1fr)_auto] sm:items-center sm:px-6">
                    <div>
                        <span class="inline-flex border {{ $index === 0 ? 'border-signal/30 bg-signal-soft text-signal' : 'border-line bg-canvas text-steel-500' }} px-2.5 py-1 font-mono text-[9px] font-semibold tracking-wider uppercase">V{{ $priceDay->versiones->count() - $index }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-ink-950">{{ $version->registradoPor->nombreCompleto() }}</p>
                        <p class="mt-1 text-xs leading-5 text-steel-500">{{ $version->motivo_cambio ?: 'Registro inicial del precio para la jornada.' }}</p>
                        <p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $version->vigente_desde->format('d.m.Y · H:i:s') }} · {{ $version->registradoPor->usuario }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="font-display text-3xl font-extrabold text-ink-950">{{ $money($version->precio_kg) }}</p>
                        <p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">por kilogramo</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endsection
