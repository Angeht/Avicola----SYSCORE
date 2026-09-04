@extends('layouts.ticket', [
    'backHref' => route('cobranzas.show', $collection),
    'isCancelled' => $collection->estaAnulada(),
])

@section('title', 'Ticket de cobranza '.$collection->numero_cobranza)

@section('ticket-content')
    @php($money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.'))

    <section class="py-4 text-center">
        <p class="font-mono text-[9px] tracking-[0.16em] uppercase">Comprobante de cobranza</p>
        <h1 class="mt-1 font-mono text-sm font-bold">{{ $collection->numero_cobranza }}</h1>
        <p class="mt-1 text-[10px]">{{ $collection->fecha_pago->format('d/m/Y H:i:s') }}</p>
    </section>

    <dl class="border-y border-dashed border-ink-950 py-3 text-[10px] leading-5">
        <div class="flex justify-between gap-3"><dt>Cliente</dt><dd class="text-right font-semibold">{{ $collection->cliente?->nombres_razon_social ?? 'Cliente no identificado' }}</dd></div>
        @if ($collection->cliente?->nro_documento)<div class="flex justify-between gap-3"><dt>Documento</dt><dd class="font-mono">{{ $collection->cliente->nro_documento }}</dd></div>@endif
        <div class="flex justify-between gap-3"><dt>Tipo</dt><dd>{{ $collection->tipo === 'ABONO' ? 'Abono a cuenta' : 'Pago de venta' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Medio</dt><dd>{{ $collection->medioPago->nombre }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Recibido por</dt><dd class="text-right">{{ $collection->usuario->nombreCompleto() }}</dd></div>
    </dl>

    @if ($collection->aplicaciones->isNotEmpty())
        <div class="divide-y divide-dashed divide-steel-300">
            @foreach ($collection->aplicaciones as $application)
                <div class="flex justify-between gap-3 py-3 text-[10px]"><div><p class="font-mono font-bold">{{ $application->venta->numero_venta }}</p><p class="text-steel-500">{{ $application->venta->fecha_venta->format('d/m/Y') }}</p></div><p class="font-mono font-bold">{{ $money($application->monto_aplicado) }}</p></div>
            @endforeach
        </div>
    @endif

    <dl class="border-y-2 border-dashed border-ink-950 py-3 text-[11px] leading-6">
        <div class="flex justify-between"><dt>Total recibido</dt><dd class="font-mono text-base font-extrabold">{{ $money($collection->monto_total) }}</dd></div>
        <div class="flex justify-between"><dt>Aplicado</dt><dd class="font-mono">{{ $money($appliedAmount) }}</dd></div>
        @if ($roundingAmount > 0)<div class="flex justify-between"><dt>Redondeo</dt><dd class="font-mono">{{ $money($roundingAmount) }}</dd></div>@endif
        <div class="flex justify-between"><dt>Sin aplicar</dt><dd class="font-mono font-bold">{{ $money($unappliedAmount) }}</dd></div>
    </dl>

    @if ($collection->observacion)<p class="mt-3 text-[10px] leading-4"><strong>Observación:</strong> {{ $collection->observacion }}</p>@endif
    @if ($collection->estaAnulada())
        <div class="mt-4 text-[10px] leading-4"><p><strong>Anulada:</strong> {{ $collection->anulada_at->format('d/m/Y H:i:s') }}</p><p><strong>Responsable:</strong> {{ $collection->anuladaPor?->nombreCompleto() }}</p><p><strong>Motivo:</strong> {{ $collection->motivo_anulacion }}</p></div>
    @endif
@endsection
