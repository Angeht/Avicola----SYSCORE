@extends('layouts.app')

@section('title', 'Apertura y cierre del día')
@section('section', 'Apertura y cierre del día')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Operación / Tesorería</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Apertura y cierre del día</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Controla el efectivo inicial y el resultado completo de cada jornada, incluyendo Yape, transferencias y demás medios de pago.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
            <div class="flex min-h-12 items-center gap-3 border border-line bg-paper px-4 shadow-sm">
                <span class="font-display text-3xl font-extrabold text-ink-950">{{ $sessions->total() }}</span>
                <span class="font-mono text-[9px] leading-4 tracking-wider text-steel-500 uppercase">Sesiones mostradas</span>
            </div>
            <a href="{{ $openSession ? route('caja.show', $openSession) : route('caja.create') }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">
                {{ $openSession ? 'Ver jornada activa' : 'Apertura del día' }}
                <span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950" aria-hidden="true">{{ $openSession ? '→' : '+' }}</span>
            </a>
        </div>
    </header>

    @if ($openSession)
        <section class="reveal-up reveal-up-delay-1 industrial-hatch panel-cut mt-6 grid gap-6 bg-ink-950 p-6 text-white shadow-panel lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center sm:p-8" aria-labelledby="open-cash-title">
            <div>
                <div class="flex items-center gap-3"><span class="size-2 animate-pulse bg-hazard"></span><p class="font-mono text-[9px] font-semibold tracking-[0.2em] text-hazard uppercase">Jornada operativa · #{{ $openSession->id }}</p></div>
                <h2 id="open-cash-title" class="mt-3 font-display text-3xl font-extrabold uppercase sm:text-4xl">Jornada en curso</h2>
                <p class="mt-2 text-sm text-steel-300">Abierta {{ $openSession->apertura_at->diffForHumans() }} con {{ $money($openSession->monto_apertura) }}.</p>
                <div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10 sm:grid-cols-4">
                    <div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Ingresos efectivo</p><p class="mt-2 font-display text-xl font-bold">{{ $money($openSummary?->ingresos_efectivo) }}</p></div>
                    <div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Egresos efectivo</p><p class="mt-2 font-display text-xl font-bold">{{ $money($openSummary?->egresos_proveedor_efectivo) }}</p></div>
                    <div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Efectivo esperado</p><p class="mt-2 font-display text-xl font-bold text-hazard">{{ $money($openSummary?->efectivo_esperado) }}</p></div>
                    <div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Resultado general</p><p class="mt-2 font-display text-xl font-bold">{{ $money((float) ($openSummary?->efectivo_esperado ?? 0) + (float) ($openSummary?->neto_otros_medios ?? 0)) }}</p></div>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:w-52 lg:grid-cols-1">
                <a href="{{ route('caja.show', $openSession) }}" class="inline-flex min-h-12 items-center justify-center border border-white/20 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:border-white hover:bg-white hover:text-ink-950">Ver detalle</a>
                <a href="{{ route('caja.cierre.create', $openSession) }}" class="inline-flex min-h-12 items-center justify-center bg-hazard px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Cierre del día</a>
            </div>
        </section>
    @else
        <section class="reveal-up reveal-up-delay-1 mt-6 flex flex-col gap-5 border-l-4 border-danger bg-paper p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6" aria-labelledby="cash-attention-title">
            <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center bg-danger-soft text-danger"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span><div><h2 id="cash-attention-title" class="font-display text-2xl font-bold text-ink-950 uppercase">Apertura del día pendiente</h2><p class="mt-1 text-sm text-steel-500">Confirma el efectivo inicial antes de procesar movimientos de efectivo.</p></div></div>
            <a href="{{ route('caja.create') }}" class="inline-flex min-h-11 items-center justify-center bg-danger px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-950">Registrar apertura</a>
        </section>
    @endif

    <form method="GET" action="{{ route('caja.index') }}" class="reveal-up reveal-up-delay-2 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm sm:grid-cols-[minmax(0,240px)_auto] sm:items-end" aria-label="Filtrar jornadas">
        <div>
            <label for="cash-session-date" class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Fecha de jornada</label>
            <input id="cash-session-date" name="fecha" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 sm:flex-none">Filtrar</button>
            @if ($date !== today()->toDateString())
                <a href="{{ route('caja.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Restablecer fecha actual">×</a>
            @endif
        </div>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="cash-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6">
            <div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Control / Historial</p><h2 id="cash-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Jornadas anteriores</h2></div>
            <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $sessions->total() }} registros</span>
        </div>

        @if ($sessions->isEmpty())
            <x-empty-state title="Aún no hay jornadas" description="La primera jornada aparecerá aquí después de registrar la apertura del día." :action-href="route('caja.create')" action-label="Registrar apertura">
                <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></x-slot:icon>
            </x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[900px] border-collapse text-left" aria-label="Historial de jornadas">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Jornada</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Apertura</th><th scope="col" class="px-6 py-3 text-right font-semibold">Esperado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Diferencia</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($sessions as $cashSession)
                            @php($sessionSummary = $summaries->get($cashSession->id))
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $cashSession->fecha_operacion->format('d/m/Y') }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">Jornada #{{ $cashSession->id }} · {{ $cashSession->apertura_at->format('H:i') }}</p></td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[9px] font-semibold tracking-wider uppercase {{ $cashSession->estaAbierta() ? 'border-signal/30 bg-signal-soft text-signal' : 'border-line bg-canvas text-steel-500' }}">{{ $cashSession->estaAbierta() ? 'Abierta' : 'Cerrada' }}</span></td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $money($cashSession->monto_apertura) }}</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold text-ink-950">{{ $money($sessionSummary?->efectivo_esperado) }}</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold {{ (float) ($sessionSummary?->diferencia_efectivo ?? 0) === 0.0 ? 'text-signal' : 'text-danger' }}">{{ $cashSession->estaAbierta() ? '—' : $money($sessionSummary?->diferencia_efectivo) }}</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('caja.show', $cashSession) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($sessions as $cashSession)
                    @php($sessionSummary = $summaries->get($cashSession->id))
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-ink-950">{{ $cashSession->fecha_operacion->format('d/m/Y') }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">Jornada #{{ $cashSession->id }} · {{ $cashSession->apertura_at->format('H:i') }}</p></div><span class="inline-flex border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $cashSession->estaAbierta() ? 'border-signal/30 bg-signal-soft text-signal' : 'border-line bg-canvas text-steel-500' }}">{{ $cashSession->estaAbierta() ? 'Abierta' : 'Cerrada' }}</span></div>
                        <div class="mt-4 grid grid-cols-2 gap-3 border-y border-line py-4"><div><p class="font-mono text-[8px] text-steel-500 uppercase">Efectivo esperado</p><p class="mt-1 font-semibold text-ink-950">{{ $money($sessionSummary?->efectivo_esperado) }}</p></div><div><p class="font-mono text-[8px] text-steel-500 uppercase">Diferencia</p><p class="mt-1 font-semibold {{ (float) ($sessionSummary?->diferencia_efectivo ?? 0) === 0.0 ? 'text-signal' : 'text-danger' }}">{{ $cashSession->estaAbierta() ? 'Pendiente' : $money($sessionSummary?->diferencia_efectivo) }}</p></div></div>
                        <a href="{{ route('caja.show', $cashSession) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver detalle</a>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$sessions" />
        @endif
    </section>
@endsection
