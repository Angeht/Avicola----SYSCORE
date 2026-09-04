@extends('layouts.app')

@section('title', 'Detalle de carga')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $money = fn (float|int|string|null $value, int $decimals = 2): string => 'S/ '.number_format((float) ($value ?? 0), $decimals, ',', '.');
        $unitPrice = static function (float|int|string|null $value): string {
            $formatted = number_format((float) ($value ?? 0), 4, ',', '.');

            return 'S/ '.(preg_replace('/0{1,2}$/', '', $formatted) ?? $formatted);
        };
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
        $isCancelled = $load->estaAnulada();
        $hasActiveAdjustments = $load->ajustesProveedor->contains(fn ($adjustment): bool => ! $adjustment->estaAnulado());
        $canCancel = ! $isCancelled
            && ! $hasActivePayments
            && ! $hasActiveAdjustments
            && $authenticatedUser?->tienePermiso('CARGAS_ANULAR');
        $canAddWeighings = ! $isCancelled
            && ! $hasActivePayments
            && ! $hasActiveAdjustments
            && $authenticatedUser?->tienePermiso('CARGAS_REGISTRAR');
        $hasActiveReturns = $load->ajustesProveedor->contains(fn ($adjustment): bool => ! $adjustment->estaAnulado() && $adjustment->tipo === 'DEVOLUCION');
        $canEditWeighings = ! $isCancelled
            && ! $hasActiveReturns
            && $authenticatedUser?->tienePermiso('CARGAS_REGISTRAR');
        $canAdjust = ! $isCancelled
            && $balance->saldo_pendiente > 0
            && $authenticatedUser?->tienePermiso('PROVEEDORES_AJUSTAR');
        $paymentLabel = match ($balance->estado_pago) {
            'ANULADA' => 'Carga anulada',
            'SIN_PESAJES' => 'Pendiente de pesajes',
            'SALDADA' => 'Carga saldada',
            'PAGO_ATRASADO' => 'Pago atrasado',
            'PARCIAL' => 'Pago parcial',
            default => 'Pago pendiente hoy',
        };
        $paymentClass = match ($balance->estado_pago) {
            'ANULADA' => 'border-danger/30 bg-danger-soft text-danger',
            'SIN_PESAJES' => 'border-steel-300 bg-canvas text-steel-500',
            'SALDADA' => 'border-signal/30 bg-signal-soft text-signal',
            'PAGO_ATRASADO' => 'border-danger/30 bg-danger-soft text-danger',
            default => 'border-hazard/40 bg-hazard-soft text-ink-950',
        };
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('cargas-proveedor.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a cargas</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Recepción / {{ $load->numero_carga }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Detalle de carga</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $load->producto->nombre }} recibido de {{ $load->proveedor->nombre_razon_social }} el {{ $load->fecha_carga->translatedFormat('d \d\e F \d\e Y') }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
            @if ($canAddWeighings)
                <a href="{{ route('cargas-proveedor.pesajes.create', $load) }}" class="inline-flex min-h-12 items-center justify-center bg-hazard px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Registrar pesajes</a>
            @endif
            @if ($canAdjust)
                <a href="{{ route('cargas-proveedor.ajustes.create', $load) }}" class="inline-flex min-h-12 items-center justify-center border border-signal px-5 font-display text-sm font-bold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Agregar ajuste</a>
            @endif
            @if ($canCancel)
                <a href="{{ route('cargas-proveedor.anulacion.create', $load) }}" class="inline-flex min-h-12 items-center justify-center border border-danger px-5 font-display text-sm font-bold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Anular carga</a>
            @endif
            <span class="inline-flex min-h-12 items-center justify-center border px-5 font-mono text-[10px] font-semibold tracking-wider uppercase {{ $paymentClass }}">{{ $paymentLabel }}</span>
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la carga">
        <div class="border-l-4 border-signal bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Peso neto recibido</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $quantity($summary->peso_neto_kg, 3) }} kg</p><p class="mt-1 text-xs text-steel-500">Bruto {{ $quantity($summary->peso_bruto_kg, 3) }} kg</p></div>
        <div class="border-l-4 border-hazard bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Aves recibidas</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $quantity($summary->cantidad_pollos) }}</p><p class="mt-1 text-xs text-steel-500">En {{ $load->pesajes->count() }} pesaje(s)</p></div>
        <div class="border-l-4 border-steel-300 bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Costo por kg</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $unitPrice($load->costo_kg) }}</p><p class="mt-1 text-xs text-steel-500">Valor autorizado para el cálculo</p></div>
        <div class="industrial-hatch border-l-4 {{ $isCancelled ? 'border-danger' : 'border-hazard' }} bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider {{ $isCancelled ? 'text-danger' : 'text-hazard' }} uppercase">Costo total calculado</p><p class="mt-2 font-display text-3xl font-extrabold {{ $isCancelled ? 'line-through opacity-60' : '' }}">{{ $money($load->costo_total) }}</p><p class="mt-1 text-xs text-steel-300">{{ $load->pesajes->isEmpty() ? 'Pendiente de registrar pesajes' : 'Saldo '.$money($balance->saldo_pendiente) }}</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.55fr)]">
        <section class="reveal-up reveal-up-delay-2 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="weighing-detail-title">
            <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6">
                <div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Balanza / Evidencia</p><h2 id="weighing-detail-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Lotes de pesaje</h2></div>
                <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $load->pesajes->count() }} registros</span>
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1040px] border-collapse text-left" aria-label="Pesajes de la carga">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Lote</th><th scope="col" class="px-6 py-3 font-semibold">Acción</th><th scope="col" class="px-6 py-3 font-semibold">Jabas / Tara</th><th scope="col" class="px-6 py-3 text-right font-semibold">Aves</th><th scope="col" class="px-6 py-3 text-right font-semibold">Peso bruto</th><th scope="col" class="px-6 py-3 text-right font-semibold">Tara total</th><th scope="col" class="px-6 py-3 text-right font-semibold">Peso neto</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($load->pesajes as $weighing)
                            <tr>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">Pesaje #{{ $loop->iteration }}</p><p class="mt-1 text-xs text-steel-500">{{ $weighing->observacion ?: 'Sin observaciones' }}</p>@if ($weighing->editado_at)<p class="mt-1 font-mono text-[8px] text-danger uppercase">Editado {{ $weighing->editado_at->format('d/m/Y H:i') }}</p>@endif</td>
                                <td class="px-6 py-4">@if ($canEditWeighings)<a href="{{ route('cargas-proveedor.pesajes.autorizacion.create', [$load, $weighing]) }}" class="inline-flex min-h-9 items-center border border-danger/50 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Editar</a>@else<span class="text-xs text-steel-500">—</span>@endif</td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $quantity($weighing->cantidad_jabas) }} jabas</p><p class="mt-1 text-xs text-steel-500">{{ $weighing->tipoJaba?->nombre ?? 'Sin jabas' }} · {{ $quantity($weighing->tara_unitaria_aplicada_kg, 3) }} kg/u</p></td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $quantity($weighing->cantidad_pollos) }}</td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $quantity($weighing->peso_bruto_kg, 3) }} kg</td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-danger">− {{ $quantity($weighing->tara_total_kg, 3) }} kg</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold text-signal">{{ $quantity($weighing->peso_neto_kg, 3) }} kg</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-ink-950 bg-canvas font-mono text-xs font-semibold text-ink-950"><tr><th colspan="3" class="px-6 py-4 text-left font-display text-sm tracking-wider uppercase">Total de carga</th><td class="px-6 py-4 text-right">{{ $quantity($summary->cantidad_pollos) }}</td><td class="px-6 py-4 text-right">{{ $quantity($summary->peso_bruto_kg, 3) }} kg</td><td class="px-6 py-4 text-right text-danger">− {{ $quantity($summary->tara_total_kg, 3) }} kg</td><td class="px-6 py-4 text-right text-signal">{{ $quantity($summary->peso_neto_kg, 3) }} kg</td></tr></tfoot>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($load->pesajes as $weighing)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-ink-950">Pesaje #{{ $loop->iteration }}</p><p class="mt-1 text-xs text-steel-500">{{ $weighing->tipoJaba?->nombre ?? 'Sin jabas' }}</p></div><span class="font-display text-2xl font-extrabold text-signal">{{ $quantity($weighing->peso_neto_kg, 3) }} kg</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-y border-line py-4"><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Aves</dt><dd class="mt-1 font-semibold text-ink-950">{{ $quantity($weighing->cantidad_pollos) }}</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Jabas</dt><dd class="mt-1 font-semibold text-ink-950">{{ $quantity($weighing->cantidad_jabas) }}</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Peso bruto</dt><dd class="mt-1 font-semibold text-ink-950">{{ $quantity($weighing->peso_bruto_kg, 3) }} kg</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Tara total</dt><dd class="mt-1 font-semibold text-danger">{{ $quantity($weighing->tara_total_kg, 3) }} kg</dd></div></dl>
                        @if ($weighing->observacion)<p class="mt-3 text-sm leading-5 text-steel-500">{{ $weighing->observacion }}</p>@endif
                        @if ($weighing->editado_at)<p class="mt-3 font-mono text-[8px] text-danger uppercase">Editado {{ $weighing->editado_at->format('d/m/Y H:i') }} · autorizado por {{ $weighing->autorizadoPor?->nombreCompleto() ?? 'administrador' }}</p>@endif
                        @if ($canEditWeighings)<a href="{{ route('cargas-proveedor.pesajes.autorizacion.create', [$load, $weighing]) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-danger/50 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Editar con autorización</a>@endif
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="grid content-start gap-6">
            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper shadow-panel" aria-labelledby="load-trace-title">
                <div class="border-b border-line px-5 py-5"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-hazard uppercase">Auditoría / Origen</p><h2 id="load-trace-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Trazabilidad</h2></div>
                <dl class="grid gap-5 p-5 text-sm">
                    <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Número interno</dt><dd class="mt-1 font-mono font-semibold text-ink-950">{{ $load->numero_carga }}</dd></div>
                    <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Proveedor</dt><dd class="mt-1 font-semibold text-ink-950">{{ $load->proveedor->nombre_razon_social }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $load->proveedor->nro_documento ?: 'Sin documento' }}{{ $load->proveedor->telefono ? ' · '.$load->proveedor->telefono : '' }}</dd></div>
                    <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Responsable de recepción</dt><dd class="mt-1 font-semibold text-ink-950">{{ $load->recibidoPor->nombreCompleto() }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $load->recibidoPor->usuario }} · {{ $load->created_at->format('d/m/Y H:i:s') }}</dd></div>
                    <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Observación</dt><dd class="mt-1 leading-5 text-ink-700">{{ $load->observacion ?: 'Recepción registrada sin observaciones.' }}</dd></div>
                </dl>
            </section>

            @if ($isCancelled)
                <section class="border-l-4 border-danger bg-danger-soft p-5" aria-labelledby="load-cancellation-title">
                    <p class="font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Anulación registrada</p>
                    <h2 id="load-cancellation-title" class="mt-2 font-display text-xl font-bold text-ink-950 uppercase">{{ $load->anulada_at->format('d/m/Y · H:i:s') }}</h2>
                    <p class="mt-2 text-sm text-ink-700">Anulada por {{ $load->anuladaPor?->nombreCompleto() ?? 'Usuario no disponible' }}.</p>
                    @if ($load->anulacionAutorizadaPor)
                        <p class="mt-1 text-sm text-ink-700">Autorizada con PIN por {{ $load->anulacionAutorizadaPor->nombreCompleto() }}.</p>
                    @endif
                    <p class="mt-4 border-t border-danger/20 pt-4 text-sm leading-6 text-ink-700">{{ $load->motivo_anulacion }}</p>
                </section>
            @elseif ($hasActivePayments && $authenticatedUser?->tienePermiso('CARGAS_ANULAR'))
                <section class="border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Anulación bloqueada</p><p class="mt-2 text-sm leading-6 text-ink-700">Anula primero todos los pagos vigentes de esta carga para poder corregir el stock y la deuda.</p></section>
            @endif

            <section class="industrial-hatch panel-cut reveal-up reveal-up-delay-3 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="payment-state-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Cuenta por pagar</p>
                <h2 id="payment-state-title" class="mt-2 font-display text-2xl font-bold uppercase">{{ $paymentLabel }}</h2>
                <div class="mt-5 grid grid-cols-3 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Pagado</p><p class="mt-2 font-display text-xl font-bold">{{ $money($balance->total_pagado) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-signal uppercase">Ajustado</p><p class="mt-2 font-display text-xl font-bold text-signal">{{ $money($balance->total_ajustado) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-hazard uppercase">Pendiente</p><p class="mt-2 font-display text-xl font-bold text-hazard">{{ $money($balance->saldo_pendiente) }}</p></div></div>
                @if (! $isCancelled && (float) $balance->saldo_pendiente > 0 && $authenticatedUser?->tienePermiso('PROVEEDORES_PAGAR'))
                    <a href="{{ route('pagos-proveedor.create', ['carga' => $load->id]) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center bg-hazard font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-white">Registrar abono</a>
                @else
                    <p class="mt-4 text-sm leading-6 text-steel-300">{{ (float) $balance->saldo_pendiente <= 0 ? 'La carga no tiene deuda pendiente.' : 'Consulta el historial para revisar los pagos aplicados.' }}</p>
                @endif
            </section>
        </aside>
    </div>

    <section class="reveal-up reveal-up-delay-3 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="load-payments-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Tesorería / Historial</p><h2 id="load-payments-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Pagos de la carga</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $load->pagosProveedor->count() }} movimientos</span></div>

        @if ($load->pagosProveedor->isEmpty())
            <x-empty-state title="Aún no hay pagos" description="Los abonos parciales o el pago total de esta carga aparecerán aquí." :action-href="! $isCancelled && (float) $balance->saldo_pendiente > 0 && $authenticatedUser?->tienePermiso('PROVEEDORES_PAGAR') ? route('pagos-proveedor.create', ['carga' => $load->id]) : null" action-label="Registrar primer abono"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h5" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[900px] border-collapse text-left" aria-label="Pagos aplicados a la carga">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Pago</th><th scope="col" class="px-6 py-3 font-semibold">Responsable</th><th scope="col" class="px-6 py-3 font-semibold">Medio</th><th scope="col" class="px-6 py-3 text-right font-semibold">Monto</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($load->pagosProveedor as $payment)
                            <tr><td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $payment->numero_pago }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->pagado_at->format('d/m/Y · H:i') }}</p></td><td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $payment->pagadoPor->nombreCompleto() }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->pagadoPor->usuario }}</p></td><td class="px-6 py-4 font-semibold text-ink-700">{{ $payment->medioPago->nombre }}</td><td class="px-6 py-4 text-right font-display text-xl font-bold {{ $payment->estaAnulado() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($payment->monto) }}</td><td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $payment->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $payment->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></td><td class="px-6 py-4 text-right">@if ($authenticatedUser?->tieneAlgunPermiso(['PROVEEDORES_PAGAR', 'PROVEEDORES_PAGO_ANULAR']))<a href="{{ route('pagos-proveedor.show', $payment) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a>@else<span class="text-xs text-steel-500">—</span>@endif</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($load->pagosProveedor as $payment)
                    <article class="p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-mono text-xs font-semibold text-ink-950">{{ $payment->numero_pago }}</p><p class="mt-1 text-xs text-steel-500">{{ $payment->pagado_at->format('d/m/Y · H:i') }} · {{ $payment->medioPago->nombre }}</p></div><span class="shrink-0 border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $payment->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $payment->estaAnulado() ? 'Anulado' : 'Vigente' }}</span></div><p class="mt-4 border-y border-line py-4 font-display text-3xl font-extrabold {{ $payment->estaAnulado() ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($payment->monto) }}</p>@if ($authenticatedUser?->tieneAlgunPermiso(['PROVEEDORES_PAGAR', 'PROVEEDORES_PAGO_ANULAR']))<a href="{{ route('pagos-proveedor.show', $payment) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver detalle</a>@endif</article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="reveal-up reveal-up-delay-3 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="provider-adjustments-title">
        <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Proveedor / Historial</p><h2 id="provider-adjustments-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Descuentos y devoluciones</h2></div>@if ($canAdjust)<a href="{{ route('cargas-proveedor.ajustes.create', $load) }}" class="inline-flex min-h-10 items-center justify-center border border-signal px-4 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Agregar ajuste</a>@endif</div>
        @if ($load->ajustesProveedor->isEmpty())
            <x-empty-state title="Sin ajustes comerciales" description="Los descuentos y devoluciones reconocidos por el proveedor aparecerán aquí." />
        @else
            <div class="divide-y divide-line">@foreach ($load->ajustesProveedor as $adjustment)<article class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6"><div><div class="flex flex-wrap items-center gap-2"><span class="border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $adjustment->estaAnulado() ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $adjustment->estaAnulado() ? 'Anulado' : $adjustment->tipo }}</span><span class="font-mono text-xs font-semibold text-ink-950">{{ $adjustment->numero_ajuste }}</span></div><p class="mt-2 text-sm text-ink-700">{{ $adjustment->motivo }}</p><p class="mt-1 text-xs text-steel-500">{{ $adjustment->fecha_ajuste->format('d/m/Y H:i') }} · {{ $adjustment->usuario->nombreCompleto() }}@if ($adjustment->ajusteMercaderia) · {{ $quantity($adjustment->ajusteMercaderia->cantidad_pollos) }} aves / {{ $quantity($adjustment->ajusteMercaderia->peso_kg, 3) }} kg @endif</p></div><div class="text-left sm:text-right"><p class="font-display text-2xl font-bold {{ $adjustment->estaAnulado() ? 'text-steel-500 line-through' : 'text-signal' }}">− {{ $money($adjustment->monto) }}</p>@if (! $adjustment->estaAnulado() && $authenticatedUser?->tienePermiso('PROVEEDORES_AJUSTAR'))<a href="{{ route('cargas-proveedor.ajustes.anulacion.create', [$load, $adjustment]) }}" class="mt-2 inline-flex font-mono text-[9px] font-semibold tracking-wider text-danger uppercase underline underline-offset-4">Anular</a>@endif</div></article>@endforeach</div>
        @endif
    </section>
@endsection
