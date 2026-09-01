@extends('layouts.ticket', [
    'backHref' => route('ventas.show', $sale),
    'isCancelled' => $sale->estaAnulada(),
])

@section('title', 'Ticket de venta '.$sale->numero_venta)

@section('ticket-content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value, int $decimals = 3): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    @endphp

    <section class="py-4 text-center">
        <p class="font-mono text-[9px] tracking-[0.16em] uppercase">Ticket de venta</p>
        <h1 class="mt-1 font-mono text-sm font-bold">{{ $sale->numero_venta }}</h1>
        <p class="mt-1 text-[10px]">{{ $sale->fecha_venta->format('d/m/Y H:i:s') }}</p>
    </section>

    <dl class="border-y border-dashed border-ink-950 py-3 text-[10px] leading-5">
        <div class="flex justify-between gap-3"><dt>Cliente</dt><dd class="text-right font-semibold">{{ $sale->cliente?->nombres_razon_social ?? 'Cliente no identificado' }}</dd></div>
        @if ($sale->cliente?->nro_documento)<div class="flex justify-between gap-3"><dt>Documento</dt><dd class="font-mono">{{ $sale->cliente->nro_documento }}</dd></div>@endif
        <div class="flex justify-between gap-3"><dt>Atendido por</dt><dd class="text-right">{{ $sale->usuario->nombreCompleto() }}</dd></div>
    </dl>

    <div class="divide-y divide-dashed divide-steel-300">
        @foreach ($sale->detalles as $detail)
            @php($detailTotal = $detailTotals->get($detail->id))
            <article class="py-3 text-[10px]">
                <p class="font-bold uppercase">{{ $detail->precioVersion->precioDia->producto->nombre }}</p>
                <div class="mt-1 flex items-end justify-between gap-3">
                    <p>{{ $quantity($detailTotal?->peso_neto_kg) }} kg × {{ $money($detail->precio_aplicado_kg) }}</p>
                    <p class="font-mono font-bold">{{ $money($detailTotal?->total_detalle) }}</p>
                </div>
                @if (! $detail->precioVersion->precioDia->producto->seVendeSoloPorPeso())
                    <p class="mt-1 text-steel-500">{{ number_format((int) ($detailTotal?->cantidad_pollos ?? 0), 0, ',', '.') }} ave(s)</p>
                @endif
            </article>
        @endforeach
    </div>

    <dl class="border-y-2 border-dashed border-ink-950 py-3 text-[11px] leading-6">
        <div class="flex justify-between"><dt>Total</dt><dd class="font-mono text-base font-extrabold">{{ $money($totals->total_venta) }}</dd></div>
        <div class="flex justify-between"><dt>Pagado</dt><dd class="font-mono">{{ $money($balance->total_pagado) }}</dd></div>
        <div class="flex justify-between"><dt>Saldo</dt><dd class="font-mono font-bold">{{ $money($balance->saldo_pendiente) }}</dd></div>
    </dl>

    @if ($sale->estaAnulada())
        <div class="mt-4 text-[10px] leading-4"><p><strong>Anulada:</strong> {{ $sale->anulada_at->format('d/m/Y H:i:s') }}</p><p><strong>Responsable:</strong> {{ $sale->anuladaPor?->nombreCompleto() }}</p><p><strong>Motivo:</strong> {{ $sale->motivo_anulacion }}</p></div>
    @endif
@endsection
