@extends('layouts.app')

@section('title', 'Beneficiado')
@section('section', 'Mercadería · Beneficiado')

@section('content')
    @php
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
        $todayLoss = (float) ($todaySummary?->kg_origen ?? 0) - (float) ($todaySummary?->kg_resultantes ?? 0);
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Transformación / Trazabilidad por carga</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Beneficiado</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Convierte existencias de pollo vivo en kilogramos de producto beneficiado, conservando la carga de origen, la merma y el rendimiento.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mercaderia.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-5 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Volver a mercadería</a>
            <a href="{{ route('beneficiados.create') }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">Registrar beneficiado<span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950 transition group-hover:rotate-90" aria-hidden="true">+</span></a>
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de beneficiado de hoy">
        <x-stat-card label="Procesos de hoy" :value="$quantity($todaySummary?->cantidad_procesos)" meta="beneficiados vigentes" tone="signal" />
        <x-stat-card label="Aves procesadas" :value="$quantity($todaySummary?->pollos_procesados).' aves'" :meta="$quantity($todaySummary?->kg_origen, 3).' kg vivos'" tone="hazard" />
        <x-stat-card label="Producto obtenido" :value="$quantity($todaySummary?->kg_resultantes, 3).' kg'" meta="disponible para venta" tone="signal" />
        <x-stat-card label="Merma del proceso" :value="$quantity($todayLoss, 3).' kg'" meta="peso vivo menos resultado" tone="default" />
    </section>

    <form method="GET" action="{{ route('beneficiados.index') }}" class="reveal-up reveal-up-delay-1 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm xl:grid-cols-[minmax(0,1fr)_180px_190px_auto]">
        <div class="relative"><label for="beneficiary-search" class="sr-only">Buscar proceso</label><svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg><input id="beneficiary-search" name="buscar" type="search" value="{{ $search }}" placeholder="Proceso, carga, proveedor o producto..." class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div><label for="beneficiary-status" class="sr-only">Estado</label><select id="beneficiary-status" name="estado" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"><option value="vigentes" @selected($status === 'vigentes')>Solo vigentes</option><option value="anulados" @selected($status === 'anulados')>Solo anulados</option><option value="todos" @selected($status === 'todos')>Todos</option></select></div>
        <div><label for="beneficiary-date" class="sr-only">Fecha</label><input id="beneficiary-date" name="fecha" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div class="flex gap-2"><button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 xl:flex-none">Filtrar</button>@if ($search !== '' || $status !== 'vigentes' || $date !== today()->toDateString())<a href="{{ route('beneficiados.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Limpiar filtros">×</a>@endif</div>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="beneficiary-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-hazard uppercase">Lotes / Conversión</p><h2 id="beneficiary-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Procesos registrados</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $processes->total() }} registros</span></div>
        @if ($processes->isEmpty())
            <x-empty-state title="No hay beneficiados registrados" description="Cuando conviertas una carga de pollo vivo, el movimiento aparecerá aquí." :action-href="route('beneficiados.create')" action-label="Registrar primer beneficiado" />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1120px] border-collapse text-left" aria-label="Historial de procesos de beneficiado">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Proceso</th><th scope="col" class="px-6 py-3 font-semibold">Carga origen</th><th scope="col" class="px-6 py-3 font-semibold">Destino</th><th scope="col" class="px-6 py-3 text-right font-semibold">Peso vivo</th><th scope="col" class="px-6 py-3 text-right font-semibold">Resultado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Rendimiento</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($processes as $process)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $process->numero_proceso }}</p><p class="mt-1 text-xs text-steel-500">{{ $process->procesado_at->format('d/m/Y · H:i') }}</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $process->cargaProveedor->numero_carga }}</p><p class="mt-1 text-xs text-steel-500">{{ $process->cargaProveedor->producto->nombre }} · {{ $process->cargaProveedor->proveedor->nombre_razon_social }}</p></td>
                                <td class="px-6 py-4 font-semibold text-signal">{{ $process->productoDestino->nombre }}</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold {{ $process->estaAnulado() ? 'text-steel-500 line-through' : 'text-danger' }}">− {{ $quantity($process->peso_origen_kg, 3) }} kg</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold {{ $process->estaAnulado() ? 'text-steel-500 line-through' : 'text-signal' }}">+ {{ $quantity($process->peso_resultante_kg, 3) }} kg</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold text-ink-950">{{ $quantity($process->rendimientoPorcentaje(), 2) }}%</td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $process->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $process->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('beneficiados.show', $process) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-line md:hidden">
                @foreach ($processes as $process)
                    <article class="p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-mono text-xs font-semibold text-ink-950">{{ $process->numero_proceso }}</p><p class="mt-1 text-xs text-steel-500">{{ $process->procesado_at->format('d/m/Y · H:i') }}</p></div><span class="border px-2 py-1 font-mono text-[8px] font-semibold uppercase {{ $process->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $process->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></div><p class="mt-4 font-display text-xl font-bold text-ink-950 uppercase">{{ $process->cargaProveedor->numero_carga }} → {{ $process->productoDestino->nombre }}</p><p class="mt-1 text-xs text-steel-500">{{ $quantity($process->cantidad_pollos) }} aves · rendimiento {{ $quantity($process->rendimientoPorcentaje(), 2) }}%</p><div class="mt-4 grid grid-cols-2 gap-px border-y border-line bg-line"><div class="bg-paper py-4 pr-3"><p class="font-mono text-[8px] text-steel-500 uppercase">Peso vivo</p><p class="mt-1 font-display text-2xl font-extrabold text-danger">− {{ $quantity($process->peso_origen_kg, 3) }}</p></div><div class="bg-paper py-4 pl-3"><p class="font-mono text-[8px] text-steel-500 uppercase">Beneficiado</p><p class="mt-1 font-display text-2xl font-extrabold text-signal">+ {{ $quantity($process->peso_resultante_kg, 3) }}</p></div></div><a href="{{ route('beneficiados.show', $process) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver detalle</a></article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$processes" />
        @endif
    </section>
@endsection
