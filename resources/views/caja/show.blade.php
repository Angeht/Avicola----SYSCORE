@extends('layouts.app')

@section('title', 'Detalle de la jornada')
@section('section', 'Apertura y cierre del día')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $difference = (float) ($summary?->diferencia_efectivo ?? 0);
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('caja.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a jornadas</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Jornada #{{ $cashSession->id }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">{{ $cashSession->estaAbierta() ? 'Apertura del día activa' : 'Cierre del día completado' }}</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Jornada de {{ $cashSession->usuario->nombreCompleto() }} iniciada el {{ $cashSession->apertura_at->format('d/m/Y') }} a las {{ $cashSession->apertura_at->format('H:i:s') }}.</p>
        </div>
        @if ($cashSession->estaAbierta())
            <a href="{{ route('caja.cierre.create', $cashSession) }}" class="inline-flex min-h-12 items-center justify-center bg-hazard px-6 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Ir al cierre del día</a>
        @else
            <span class="inline-flex min-h-12 items-center border border-signal/30 bg-signal-soft px-5 font-mono text-[10px] font-semibold tracking-wider text-signal uppercase">Arqueo completado</span>
        @endif
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la jornada">
        <div class="border-l-4 border-steel-300 bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Fondo de apertura</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($summary?->monto_apertura) }}</p></div>
        <div class="border-l-4 border-hazard bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Efectivo {{ $cashSession->estaAbierta() ? 'esperado' : 'contado' }}</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($cashSession->estaAbierta() ? $summary?->efectivo_esperado : $summary?->monto_contado_efectivo) }}</p></div>
        <div class="border-l-4 border-signal bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Yape y otros netos</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($summary?->neto_otros_medios) }}</p></div>
        <div class="border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Resultado {{ $cashSession->estaAbierta() ? 'general' : 'final' }} del día</p><p class="mt-2 font-display text-3xl font-extrabold">{{ $money($cashSession->estaAbierta() ? $summary?->resultado_general_sistema : $summary?->resultado_general_cierre) }}</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
        <section class="reveal-up reveal-up-delay-2 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="cash-movements-title">
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Conciliación / Todos los canales</p><h2 id="cash-movements-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Movimientos por medio de pago</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] border-collapse text-left" aria-label="Movimientos de la jornada por medio de pago">
                    <thead class="bg-canvas font-mono text-[8px] tracking-wider text-steel-500 uppercase"><tr><th scope="col" class="px-5 py-3 font-semibold sm:px-6">Medio</th><th scope="col" class="px-5 py-3 text-right font-semibold">Ingresos</th><th scope="col" class="px-5 py-3 text-right font-semibold">Egresos</th><th scope="col" class="px-5 py-3 text-right font-semibold sm:px-6">Neto</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($paymentMethodBreakdown as $method)
                            <tr><td class="px-5 py-4 sm:px-6"><p class="font-semibold text-ink-950">{{ $method->nombre }}</p><p class="mt-1 font-mono text-[8px] text-steel-500 uppercase">{{ $method->es_efectivo ? 'Efectivo físico' : 'Digital / bancario' }}</p></td><td class="px-5 py-4 text-right font-mono text-xs font-semibold text-signal">+ {{ $money($method->ingresos) }}</td><td class="px-5 py-4 text-right font-mono text-xs font-semibold text-danger">− {{ $money($method->egresos) }}</td><td class="px-5 py-4 text-right font-mono text-xs font-bold text-ink-950 sm:px-6">{{ $money($method->neto) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="reveal-up reveal-up-delay-2 border border-line bg-paper shadow-panel" aria-labelledby="cash-audit-title">
            <div class="border-b border-line px-5 py-5"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-hazard uppercase">Arqueo / Estado</p><h2 id="cash-audit-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Control de cierre</h2></div>
            <div class="p-5">
                @if ($cashSession->estaAbierta())
                    <span class="inline-flex border border-hazard/40 bg-hazard-soft px-2.5 py-1 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Pendiente</span>
                    <p class="mt-4 text-sm leading-6 text-steel-500">Cuenta el efectivo físico y registra el cierre cuando finalice la operación.</p>
                    <a href="{{ route('caja.cierre.create', $cashSession) }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center bg-ink-950 font-display text-sm font-bold tracking-wider text-white uppercase">Ir al cierre del día</a>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] text-steel-500 uppercase">Contado</p><p class="mt-2 font-display text-xl font-bold text-ink-950">{{ $money($summary?->monto_contado_efectivo) }}</p></div>
                        <div class="border {{ $difference === 0.0 ? 'border-signal/30 bg-signal-soft' : 'border-danger/30 bg-danger-soft' }} p-4"><p class="font-mono text-[8px] {{ $difference === 0.0 ? 'text-signal' : 'text-danger' }} uppercase">Diferencia</p><p class="mt-2 font-display text-xl font-bold {{ $difference === 0.0 ? 'text-signal' : 'text-danger' }}">{{ $money($difference) }}</p></div>
                    </div>
                    <dl class="mt-5 grid gap-4 text-sm">
                        <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Cierre registrado</dt><dd class="mt-1 font-semibold text-ink-950">{{ $cashSession->cierre_at->format('d/m/Y · H:i:s') }}</dd></div>
                        <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Responsable</dt><dd class="mt-1 font-semibold text-ink-950">{{ $cashSession->cerradaPor?->nombreCompleto() ?? 'No disponible' }}</dd></div>
                        <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Observación</dt><dd class="mt-1 leading-5 text-ink-700">{{ $cashSession->observacion_cierre ?: 'Cierre conforme, sin observaciones.' }}</dd></div>
                    </dl>
                @endif
            </div>
        </aside>
    </div>
@endsection
