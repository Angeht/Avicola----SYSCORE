@extends('layouts.app')

@section('title', 'Cobranzas')
@section('section', 'Cobranzas')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value): string => number_format((float) ($value ?? 0), 0, ',', '.');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Ingresos</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Cobranzas</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Registra pagos por cliente y consulta el avance de sus deudas, abonos y saldos pendientes.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
            <div class="flex min-h-12 items-center gap-3 border border-line bg-paper px-4 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $collections->total() }}</span><span class="font-mono text-[9px] leading-4 tracking-wider text-steel-500 uppercase">Movimientos<br>en historial</span></div>
            @if ($authenticatedUser?->tienePermiso('REPORTES_VER'))
                <a href="{{ route('reportes.show', 'cuentas-cobrar') }}" class="inline-flex min-h-12 items-center justify-center gap-3 border border-ink-950 px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Deudas por cliente <span aria-hidden="true">→</span></a>
            @endif
            @if ($authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR'))
                <a href="{{ route('cobranzas.create') }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">Registrar cobranza<span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950 transition group-hover:rotate-90" aria-hidden="true">+</span></a>
            @endif
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de cobranzas de hoy">
        <x-stat-card label="Cobranzas vigentes" :value="$quantity($todaySummary?->cantidad_cobranzas)" meta="Operaciones de hoy" tone="hazard"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h8" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Total cobrado" :value="$money($todaySummary?->total_cobrado)" meta="Ingresos vigentes" tone="signal"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v18m5-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H7" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Ingreso en efectivo" :value="$money($todaySummary?->total_efectivo)" meta="Sumado a caja" tone="hazard"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18v12H3V6Zm4 3h.01M17 15h.01" /><circle cx="12" cy="12" r="3" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Otros medios" :value="$money($todaySummary?->total_otros_medios)" :meta="$quantity($todaySummary?->cantidad_anuladas).' anuladas hoy'"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 7h8m-8 4h8m-8 4h5" /></svg></x-slot:icon></x-stat-card>
    </section>

    <form method="GET" action="{{ route('cobranzas.index') }}" class="reveal-up reveal-up-delay-1 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm xl:grid-cols-[minmax(0,1fr)_170px_170px_190px_auto]">
        <div class="relative"><label for="collection-search" class="sr-only">Buscar cobranza</label><svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg><input id="collection-search" name="buscar" type="search" value="{{ $search }}" placeholder="Cobranza, cliente o venta..." class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div><label for="collection-type" class="sr-only">Tipo</label><select id="collection-type" name="tipo" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"><option value="">Todos los tipos</option><option value="PAGO_VENTA" @selected($type === 'PAGO_VENTA')>Pago de venta</option><option value="ABONO" @selected($type === 'ABONO')>Abono</option></select></div>
        <div><label for="collection-status" class="sr-only">Estado</label><select id="collection-status" name="estado" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"><option value="vigentes" @selected($status === 'vigentes')>Solo vigentes</option><option value="anuladas" @selected($status === 'anuladas')>Solo anuladas</option><option value="todas" @selected($status === 'todas')>Todas</option></select></div>
        <div><label for="collection-date" class="sr-only">Fecha</label><input id="collection-date" name="fecha" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div class="flex gap-2"><button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 xl:flex-none">Filtrar</button>@if ($search !== '' || $status !== 'vigentes' || $date !== today()->toDateString() || $type !== null)<a href="{{ route('cobranzas.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Limpiar filtros">×</a>@endif</div>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="collection-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Control / Historial</p><h2 id="collection-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Ingresos registrados</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $collections->total() }} registros</span></div>

        @if ($collections->isEmpty())
            <x-empty-state title="No hay cobranzas registradas" description="Los pagos de ventas y abonos de clientes aparecerán en este historial." :action-href="$authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR') ? route('cobranzas.create') : null" action-label="Registrar primera cobranza"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h8" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1100px] border-collapse text-left" aria-label="Historial de cobranzas">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Cobranza</th><th scope="col" class="px-6 py-3 font-semibold">Cliente / Tipo</th><th scope="col" class="px-6 py-3 font-semibold">Medio</th><th scope="col" class="px-6 py-3 text-right font-semibold">Recibido</th><th scope="col" class="px-6 py-3 text-right font-semibold">Sin aplicar</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($collections as $collection)
                            @php($unapplied = max(0, (float) $collection->monto_total - (float) ($collection->monto_aplicado ?? 0)))
                            @php($canApply = $collection->tipo === 'ABONO' && $unapplied > 0 && ! $collection->estaAnulada() && $authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR'))
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $collection->numero_cobranza }}</p><p class="mt-1 text-xs text-steel-500">{{ $collection->fecha_pago->format('d/m/Y · H:i') }}</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $collection->cliente?->nombres_razon_social ?? 'Cliente anónimo' }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $collection->tipo === 'ABONO' ? 'Abono de cliente' : 'Pago de venta' }} · {{ $collection->aplicaciones_count }} venta(s)</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-700">{{ $collection->medioPago->nombre }}</p><p class="mt-1 text-xs text-steel-500">{{ $collection->sesion_caja_id ? 'Caja #'.$collection->sesion_caja_id : 'Sin sesión de caja' }}</p></td>
                                <td class="px-6 py-4 text-right font-display text-xl font-bold {{ $collection->estaAnulada() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($collection->monto_total) }}</td>
                                <td class="px-6 py-4 text-right font-display text-lg font-bold {{ $unapplied > 0 && ! $collection->estaAnulada() ? 'text-ink-950' : 'text-steel-500' }}">{{ $collection->estaAnulada() ? '—' : $money($unapplied) }}</td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $collection->estaAnulada() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $collection->estaAnulada() ? 'Anulada' : 'Vigente' }}</span></td>
                                <td class="px-6 py-4 text-right"><div class="flex justify-end gap-2">@if ($canApply)<a href="{{ route('cobranzas.aplicaciones.create', $collection) }}" class="inline-flex min-h-9 items-center bg-hazard px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Aplicar</a>@endif<a href="{{ route('cobranzas.show', $collection) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($collections as $collection)
                    @php($unapplied = max(0, (float) $collection->monto_total - (float) ($collection->monto_aplicado ?? 0)))
                    @php($canApply = $collection->tipo === 'ABONO' && $unapplied > 0 && ! $collection->estaAnulada() && $authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR'))
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="truncate font-mono text-xs font-semibold text-ink-950">{{ $collection->numero_cobranza }}</p><p class="mt-1 text-xs text-steel-500">{{ $collection->fecha_pago->format('d/m/Y · H:i') }}</p></div><span class="shrink-0 border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $collection->estaAnulada() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $collection->estaAnulada() ? 'Anulada' : 'Vigente' }}</span></div>
                        <p class="mt-4 font-semibold text-ink-950">{{ $collection->cliente?->nombres_razon_social ?? 'Cliente anónimo' }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $collection->tipo === 'ABONO' ? 'Abono' : 'Pago de venta' }} · {{ $collection->medioPago->nombre }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-px border-y border-line bg-line"><div class="bg-paper py-4 pr-3"><p class="font-mono text-[8px] text-steel-500 uppercase">Recibido</p><p class="mt-1 font-display text-2xl font-extrabold {{ $collection->estaAnulada() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($collection->monto_total) }}</p></div><div class="bg-paper py-4 pl-3"><p class="font-mono text-[8px] text-steel-500 uppercase">Sin aplicar</p><p class="mt-1 font-display text-2xl font-extrabold text-ink-950">{{ $collection->estaAnulada() ? '—' : $money($unapplied) }}</p></div></div>
                        <div class="mt-4 grid gap-2 {{ $canApply ? 'grid-cols-2' : '' }}">@if ($canApply)<a href="{{ route('cobranzas.aplicaciones.create', $collection) }}" class="inline-flex min-h-10 items-center justify-center bg-hazard font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Aplicar</a>@endif<a href="{{ route('cobranzas.show', $collection) }}" class="inline-flex min-h-10 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver detalle</a></div>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$collections" />
        @endif
    </section>
@endsection
