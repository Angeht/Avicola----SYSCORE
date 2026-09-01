@extends('layouts.app')

@section('title', 'Detalle de pago')
@section('section', 'Pagos a proveedores')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $isCancelled = $payment->estaAnulado();
        $cashSessionIsClosed = $payment->sesionCaja?->cierre_at !== null;
        $canCancel = ! $isCancelled
            && ! $cashSessionIsClosed
            && $authenticatedUser?->tienePermiso('PROVEEDORES_PAGO_ANULAR');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('pagos-proveedor.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a pagos</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / {{ $payment->numero_pago }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Detalle de pago</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Abono aplicado a {{ $payment->carga->numero_carga }} para {{ $payment->carga->proveedor->nombre_razon_social }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            @if ($canCancel)
                <a href="{{ route('pagos-proveedor.anulacion.create', $payment) }}" class="inline-flex min-h-12 items-center justify-center border border-danger px-5 font-display text-sm font-bold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Anular pago</a>
            @endif
            <span class="inline-flex min-h-12 items-center justify-center border px-5 font-mono text-[10px] font-semibold tracking-wider uppercase {{ $isCancelled ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $isCancelled ? 'Pago anulado' : 'Pago vigente' }}</span>
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del pago">
        <div class="border-l-4 {{ $isCancelled ? 'border-steel-300' : 'border-signal' }} bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Monto registrado</p><p class="mt-2 font-display text-3xl font-extrabold {{ $isCancelled ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($payment->monto) }}</p></div>
        <div class="border-l-4 border-hazard bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Total pagado vigente</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($balance->total_pagado) }}</p></div>
        <div class="border-l-4 border-danger bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-danger uppercase">Saldo de la carga</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($balance->saldo_pendiente) }}</p></div>
        <div class="industrial-hatch border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Medio de pago</p><p class="mt-2 font-display text-2xl font-extrabold uppercase">{{ $payment->medioPago->nombre }}</p><p class="mt-1 text-xs text-steel-300">{{ $payment->medioPago->es_efectivo ? 'Salida de efectivo' : 'Medio no efectivo' }}</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
        <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="payment-document-title">
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Comprobante / Auditoría</p><h2 id="payment-document-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Datos del movimiento</h2></div>
            <dl class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-line p-5 sm:border-r sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Número de pago</dt><dd class="mt-2 font-mono text-sm font-semibold text-ink-950">{{ $payment->numero_pago }}</dd></div>
                <div class="border-b border-line p-5 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Fecha y hora</dt><dd class="mt-2 font-semibold text-ink-950">{{ $payment->pagado_at->format('d/m/Y · H:i:s') }}</dd></div>
                <div class="border-b border-line p-5 sm:border-r sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Registrado por</dt><dd class="mt-2 font-semibold text-ink-950">{{ $payment->pagadoPor->nombreCompleto() }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $payment->pagadoPor->usuario }}</dd></div>
                <div class="border-b border-line p-5 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Sesión de caja</dt><dd class="mt-2 font-semibold text-ink-950">{{ $payment->sesionCaja ? 'Caja #'.$payment->sesionCaja->id : 'No vinculada' }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $payment->sesionCaja ? ($cashSessionIsClosed ? 'Sesión cerrada' : 'Sesión abierta') : 'Pago registrado sin sesión activa' }}</dd></div>
                <div class="p-5 sm:col-span-2 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Observación</dt><dd class="mt-2 text-sm leading-6 text-ink-700">{{ $payment->observacion ?: 'Pago registrado sin observaciones.' }}</dd></div>
            </dl>
        </section>

        <aside class="grid content-start gap-6">
            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper shadow-panel" aria-labelledby="payment-load-title">
                <div class="border-b border-line px-5 py-5"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-hazard uppercase">Origen / Cuenta</p><h2 id="payment-load-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Carga asociada</h2></div>
                <div class="p-5"><p class="font-mono text-xs font-semibold text-signal">{{ $payment->carga->numero_carga }}</p><p class="mt-3 font-display text-2xl font-bold text-ink-950 uppercase">{{ $payment->carga->proveedor->nombre_razon_social }}</p><p class="mt-2 text-sm text-steel-500">{{ $payment->carga->producto->nombre }} · recibida el {{ $payment->carga->fecha_carga->format('d/m/Y') }}</p><div class="mt-5 grid grid-cols-2 gap-3"><div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] text-steel-500 uppercase">Costo</p><p class="mt-2 font-display text-lg font-bold text-ink-950">{{ $money($payment->carga->costo_total) }}</p></div><div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] text-steel-500 uppercase">Pendiente</p><p class="mt-2 font-display text-lg font-bold text-danger">{{ $money($balance->saldo_pendiente) }}</p></div></div><a href="{{ route('cargas-proveedor.show', $payment->carga) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center border border-ink-950 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Ver carga completa</a></div>
            </section>

            @if ($isCancelled)
                <section class="reveal-up reveal-up-delay-2 border-l-4 border-danger bg-danger-soft p-5" aria-labelledby="cancellation-detail-title"><p class="font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Anulación registrada</p><h2 id="cancellation-detail-title" class="mt-2 font-display text-xl font-bold text-ink-950 uppercase">{{ $payment->anulada_at->format('d/m/Y · H:i:s') }}</h2><p class="mt-2 text-sm text-ink-700">Por {{ $payment->anuladaPor?->nombreCompleto() ?? 'Usuario no disponible' }}.</p><p class="mt-4 border-t border-danger/20 pt-4 text-sm leading-6 text-ink-700">{{ $payment->motivo_anulacion }}</p></section>
            @elseif ($cashSessionIsClosed)
                <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Movimiento conciliado</p><p class="mt-2 text-sm leading-6 text-ink-700">Este pago pertenece a una caja cerrada y ya no puede anularse para conservar la integridad del arqueo.</p></section>
            @endif
        </aside>
    </div>
@endsection
