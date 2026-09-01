@extends('layouts.app')

@section('title', 'Pagos a proveedores')
@section('section', 'Pagos a proveedores')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value): string => number_format((float) ($value ?? 0), 0, ',', '.');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Egresos</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Pagos a proveedores</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Controla abonos, saldos y egresos de caja vinculados a cada carga recibida.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
            <div class="flex min-h-12 items-center gap-3 border border-line bg-paper px-4 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $payments->total() }}</span><span class="font-mono text-[9px] leading-4 tracking-wider text-steel-500 uppercase">Pagos en historial</span></div>
            @if ($authenticatedUser?->tienePermiso('PROVEEDORES_PAGAR'))
                <a href="{{ route('pagos-proveedor.create') }}" class="group inline-flex min-h-12 items-center justify-between gap-5 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">Registrar pago<span class="grid size-7 place-items-center bg-hazard text-lg leading-none text-ink-950 transition group-hover:rotate-90" aria-hidden="true">+</span></a>
            @endif
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de pagos de hoy">
        <x-stat-card label="Pagos vigentes" :value="$quantity($todaySummary?->cantidad_pagos)" meta="Operaciones de hoy" tone="hazard"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h5" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Total pagado" :value="$money($todaySummary?->total_pagado)" meta="Egresos vigentes" tone="signal"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v18m5-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H7" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Salida de efectivo" :value="$money($todaySummary?->total_efectivo)" meta="Descontado de caja" tone="danger"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18v12H3V6Zm4 3h.01M17 15h.01" /><circle cx="12" cy="12" r="3" /></svg></x-slot:icon></x-stat-card>
        <x-stat-card label="Otros medios" :value="$money($todaySummary?->total_otros_medios)" :meta="$quantity($todaySummary?->cantidad_anulados).' anulados hoy'"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 7h8m-8 4h8m-8 4h5" /></svg></x-slot:icon></x-stat-card>
    </section>

    <form method="GET" action="{{ route('pagos-proveedor.index') }}" class="reveal-up reveal-up-delay-1 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_190px_210px_auto]">
        <div class="relative"><label for="payment-search" class="sr-only">Buscar pago</label><svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg><input id="payment-search" name="buscar" type="search" value="{{ $search }}" placeholder="Pago, carga o proveedor..." class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div><label for="payment-status" class="sr-only">Estado del pago</label><select id="payment-status" name="estado" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"><option value="vigentes" @selected($status === 'vigentes')>Solo vigentes</option><option value="anulados" @selected($status === 'anulados')>Solo anulados</option><option value="todos" @selected($status === 'todos')>Todos</option></select></div>
        <div><label for="payment-date" class="sr-only">Fecha del pago</label><input id="payment-date" name="fecha" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"></div>
        <div class="flex gap-2"><button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 lg:flex-none">Filtrar</button>@if ($search !== '' || $status !== 'vigentes' || $date !== today()->toDateString())<a href="{{ route('pagos-proveedor.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Limpiar filtros">×</a>@endif</div>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="payment-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Control / Historial</p><h2 id="payment-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Movimientos registrados</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $payments->total() }} registros</span></div>

        @if ($payments->isEmpty())
            <x-empty-state title="No hay pagos registrados" description="Los pagos parciales y totales de las cargas aparecerán en este historial." :action-href="$authenticatedUser?->tienePermiso('PROVEEDORES_PAGAR') ? route('pagos-proveedor.create') : null" action-label="Registrar primer pago"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h5" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1080px] border-collapse text-left" aria-label="Historial de pagos a proveedores">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Pago</th><th scope="col" class="px-6 py-3 font-semibold">Carga / Proveedor</th><th scope="col" class="px-6 py-3 font-semibold">Medio</th><th scope="col" class="px-6 py-3 text-right font-semibold">Monto</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($payments as $payment)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $payment->numero_pago }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->pagado_at->format('d/m/Y · H:i') }}</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $payment->carga->proveedor->nombre_razon_social }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $payment->carga->numero_carga }}</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-700">{{ $payment->medioPago->nombre }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->sesion_caja_id ? 'Caja #'.$payment->sesion_caja_id : 'Sin sesión de caja' }}</p></td>
                                <td class="px-6 py-4 text-right font-display text-xl font-bold {{ $payment->estaAnulado() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($payment->monto) }}</td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $payment->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $payment->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('pagos-proveedor.show', $payment) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($payments as $payment)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="truncate font-mono text-xs font-semibold text-ink-950">{{ $payment->numero_pago }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->pagado_at->format('d/m/Y · H:i') }}</p></div><span class="shrink-0 border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $payment->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $payment->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></div>
                        <p class="mt-4 font-semibold text-ink-950">{{ $payment->carga->proveedor->nombre_razon_social }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $payment->carga->numero_carga }} · {{ $payment->medioPago->nombre }}</p>
                        <p class="mt-4 border-y border-line py-4 font-display text-3xl font-extrabold {{ $payment->estaAnulado() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($payment->monto) }}</p>
                        <a href="{{ route('pagos-proveedor.show', $payment) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver detalle</a>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$payments" />
        @endif
    </section>
@endsection
