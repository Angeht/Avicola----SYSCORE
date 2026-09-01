@extends('layouts.app')

@section('title', 'Detalle de cobranza')
@section('section', 'Cobranzas')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $isCancelled = $collection->estaAnulada();
        $cashSessionIsClosed = $collection->sesionCaja?->cierre_at !== null;
        $canCancel = ! $isCancelled
            && ! $cashSessionIsClosed
            && $authenticatedUser?->tienePermiso('COBRANZAS_ANULAR');
        $canApply = ! $isCancelled
            && $collection->tipo === 'ABONO'
            && $collection->cliente_id !== null
            && $unappliedAmount > 0
            && $authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR');
        $canViewCustomerDebt = $collection->cliente_id !== null
            && $authenticatedUser?->tienePermiso('REPORTES_VER');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('cobranzas.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a cobranzas</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / {{ $collection->numero_cobranza }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Detalle de cobranza</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $collection->tipo === 'ABONO' ? 'Abono recibido de' : 'Pago de venta recibido de' }} {{ $collection->cliente?->nombres_razon_social ?? 'un cliente anónimo' }}.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row">
            @if ($canViewCustomerDebt)
                <a href="{{ route('reportes.customer-account', $collection->cliente_id) }}" class="inline-flex min-h-12 items-center justify-center border border-signal bg-signal-soft px-5 font-display text-sm font-bold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Ver estado de cuenta</a>
            @endif
            <a href="{{ route('cobranzas.ticket', $collection) }}" target="_blank" rel="noopener" class="inline-flex min-h-12 items-center justify-center border border-ink-950 px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Imprimir ticket</a>
            @if ($canApply)
                <a href="{{ route('cobranzas.aplicaciones.create', $collection) }}" class="inline-flex min-h-12 items-center justify-center bg-hazard px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Aplicar saldo</a>
            @endif
            @if ($canCancel)
                <a href="{{ route('cobranzas.anulacion.create', $collection) }}" class="inline-flex min-h-12 items-center justify-center border border-danger px-5 font-display text-sm font-bold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Anular cobranza</a>
            @endif
            <span class="inline-flex min-h-12 items-center justify-center border px-5 font-mono text-[10px] font-semibold tracking-wider uppercase {{ $isCancelled ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $isCancelled ? 'Cobranza anulada' : 'Cobranza vigente' }}</span>
        </div>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la cobranza">
        <div class="border-l-4 {{ $isCancelled ? 'border-steel-300' : 'border-signal' }} bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Monto recibido</p><p class="mt-2 font-display text-3xl font-extrabold {{ $isCancelled ? 'text-steel-500 line-through' : 'text-ink-950' }}">{{ $money($collection->monto_total) }}</p></div>
        <div class="border-l-4 border-signal bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Monto aplicado</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($appliedAmount) }}</p></div>
        <div class="border-l-4 border-hazard bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Sin aplicar</p><p class="mt-2 font-display text-3xl font-extrabold {{ $unappliedAmount > 0 && ! $isCancelled ? 'text-ink-950' : 'text-steel-500' }}">{{ $isCancelled ? '—' : $money($unappliedAmount) }}</p></div>
        <div class="industrial-hatch border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Medio de pago</p><p class="mt-2 font-display text-2xl font-extrabold uppercase">{{ $collection->medioPago->nombre }}</p><p class="mt-1 text-xs text-steel-300">{{ $collection->medioPago->es_efectivo ? 'Ingreso de efectivo' : 'Medio no efectivo' }}</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
        <div class="grid content-start gap-6">
            <section class="reveal-up reveal-up-delay-1 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="collection-applications-title">
                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Distribución / Cuentas</p><h2 id="collection-applications-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Ventas aplicadas</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $collection->aplicaciones->count() }} ventas</span></div>

                @if ($collection->aplicaciones->isEmpty())
                    <div class="p-6 sm:p-8"><div class="border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-display text-xl font-bold text-ink-950 uppercase">Abono pendiente de aplicar</p><p class="mt-2 text-sm leading-6 text-ink-700">El importe completo permanece como saldo sin aplicar del cliente. La base lo mantiene identificado para una asignación posterior.</p></div></div>
                @else
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="w-full min-w-[720px] border-collapse text-left" aria-label="Aplicaciones de la cobranza">
                            <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Venta</th><th scope="col" class="px-6 py-3 font-semibold">Cliente</th><th scope="col" class="px-6 py-3 text-right font-semibold">Aplicado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Saldo actual</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($collection->aplicaciones as $application)
                                    @php($balance = $saleBalances->get($application->venta_id))
                                    <tr><td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $application->venta->numero_venta }}</p><p class="mt-1 text-xs text-steel-500">{{ $application->venta->fecha_venta->format('d/m/Y') }}</p></td><td class="px-6 py-4 font-semibold text-ink-700">{{ $application->venta->cliente?->nombres_razon_social ?? 'Cliente anónimo' }}</td><td class="px-6 py-4 text-right font-display text-xl font-bold text-signal">{{ $money($application->monto_aplicado) }}</td><td class="px-6 py-4 text-right"><p class="font-display text-lg font-bold text-ink-950">{{ $money($balance?->saldo_pendiente) }}</p><p class="mt-1 font-mono text-[8px] text-steel-500 uppercase">{{ $balance?->estado_pago ?? 'Sin estado' }}</p></td><td class="px-6 py-4 text-right"><a href="{{ route('ventas.show', $application->venta) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver venta</a></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-line sm:hidden">
                        @foreach ($collection->aplicaciones as $application)
                            @php($balance = $saleBalances->get($application->venta_id))
                            <article class="p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-mono text-xs font-semibold text-ink-950">{{ $application->venta->numero_venta }}</p><p class="mt-1 text-xs text-steel-500">{{ $application->venta->cliente?->nombres_razon_social ?? 'Cliente anónimo' }}</p></div><p class="font-display text-xl font-bold text-signal">{{ $money($application->monto_aplicado) }}</p></div><div class="mt-4 flex items-center justify-between border-y border-line py-3 text-sm"><span class="text-steel-500">Saldo actual</span><strong class="text-ink-950">{{ $money($balance?->saldo_pendiente) }}</strong></div><a href="{{ route('ventas.show', $application->venta) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Ver venta</a></article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="collection-document-title">
                <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Comprobante / Auditoría</p><h2 id="collection-document-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Datos del movimiento</h2></div>
                <dl class="grid gap-0 sm:grid-cols-2"><div class="border-b border-line p-5 sm:border-r sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Número</dt><dd class="mt-2 font-mono text-sm font-semibold text-ink-950">{{ $collection->numero_cobranza }}</dd></div><div class="border-b border-line p-5 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Fecha y hora</dt><dd class="mt-2 font-semibold text-ink-950">{{ $collection->fecha_pago->format('d/m/Y · H:i:s') }}</dd></div><div class="border-b border-line p-5 sm:border-r sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Registrada por</dt><dd class="mt-2 font-semibold text-ink-950">{{ $collection->usuario->nombreCompleto() }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $collection->usuario->usuario }}</dd></div><div class="border-b border-line p-5 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Sesión de caja</dt><dd class="mt-2 font-semibold text-ink-950">{{ $collection->sesionCaja ? 'Caja #'.$collection->sesionCaja->id : 'No vinculada' }}</dd><dd class="mt-1 text-xs text-steel-500">{{ $collection->sesionCaja ? ($cashSessionIsClosed ? 'Sesión cerrada' : 'Sesión abierta') : 'Ingreso registrado sin sesión activa' }}</dd></div><div class="p-5 sm:col-span-2 sm:p-6"><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Observación</dt><dd class="mt-2 text-sm leading-6 text-ink-700">{{ $collection->observacion ?: 'Cobranza registrada sin observaciones.' }}</dd></div></dl>
            </section>
        </div>

        <aside class="grid content-start gap-6">
            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper shadow-panel" aria-labelledby="collection-client-title"><div class="border-b border-line px-5 py-5"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-hazard uppercase">Origen / Cliente</p><h2 id="collection-client-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">{{ $collection->cliente ? 'Cuenta identificada' : 'Venta anónima' }}</h2></div><div class="p-5"><p class="font-display text-2xl font-bold text-ink-950 uppercase">{{ $collection->cliente?->nombres_razon_social ?? 'Cliente anónimo' }}</p>@if ($collection->cliente)<p class="mt-2 text-sm text-steel-500">{{ $collection->cliente->nro_documento ?: 'Sin documento' }}{{ $collection->cliente->telefono ? ' · '.$collection->cliente->telefono : '' }}</p><p class="mt-1 text-sm text-steel-500">{{ $collection->cliente->direccion ?: 'Sin dirección registrada' }}</p>@if ($authenticatedUser?->tieneAlgunPermiso(['VENTAS_REGISTRAR', 'COBRANZAS_REGISTRAR']))<a href="{{ route('clientes.edit', $collection->cliente) }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center border border-ink-950 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Ver cliente</a>@endif @else<p class="mt-2 text-sm leading-6 text-steel-500">La cobranza se vinculó exclusivamente a una venta sin cliente registrado.</p>@endif</div></section>

            @if ($collection->sesionCaja && $cashSummary)
                <section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Impacto en caja #{{ $collection->sesionCaja->id }}</p><div class="mt-4 grid grid-cols-2 gap-3"><div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] text-steel-500 uppercase">Ingresos efectivo</p><p class="mt-2 font-display text-lg font-bold text-ink-950">{{ $money($cashSummary->ingresos_efectivo) }}</p></div><div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] text-steel-500 uppercase">Efectivo esperado</p><p class="mt-2 font-display text-lg font-bold text-signal">{{ $money($cashSummary->efectivo_esperado) }}</p></div></div></section>
            @endif

            @if ($isCancelled)
                <section class="reveal-up reveal-up-delay-2 border-l-4 border-danger bg-danger-soft p-5" aria-labelledby="collection-cancellation-title"><p class="font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Anulación registrada</p><h2 id="collection-cancellation-title" class="mt-2 font-display text-xl font-bold text-ink-950 uppercase">{{ $collection->anulada_at->format('d/m/Y · H:i:s') }}</h2><p class="mt-2 text-sm text-ink-700">Por {{ $collection->anuladaPor?->nombreCompleto() ?? 'Usuario no disponible' }}.</p><p class="mt-4 border-t border-danger/20 pt-4 text-sm leading-6 text-ink-700">{{ $collection->motivo_anulacion }}</p></section>
            @elseif ($cashSessionIsClosed)
                <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Movimiento conciliado</p><p class="mt-2 text-sm leading-6 text-ink-700">La cobranza pertenece a una caja cerrada y ya no puede anularse para conservar la integridad del arqueo.</p></section>
            @elseif ($unappliedAmount > 0)
                <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Saldo disponible</p><p class="mt-2 text-sm leading-6 text-ink-700">Quedan {{ $money($unappliedAmount) }} sin aplicar. Puedes distribuirlos posteriormente sin registrar otro ingreso.</p>@if ($canApply)<a href="{{ route('cobranzas.aplicaciones.create', $collection) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center bg-ink-950 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Distribuir abono</a>@endif</section>
            @endif
        </aside>
    </div>
@endsection
