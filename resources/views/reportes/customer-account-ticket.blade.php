@extends('layouts.ticket', [
    'backHref' => route('reportes.customer-account', ['cliente' => $cliente, 'hasta' => $cutoff]),
    'isCancelled' => false,
])

@section('title', 'Ticket de estado de cuenta de '.$cliente->nombres_razon_social)

@section('ticket-content')
    @php
        $money = static function (float|int|string|null $value): string {
            $amount = (float) ($value ?? 0);

            return ($amount < 0 ? '-S/ ' : 'S/ ').number_format(abs($amount), 2, ',', '.');
        };
        $balance = static fn (float|int|string|null $value): string => (float) $value < 0
            ? 'A favor '.$money(abs((float) $value))
            : $money($value);
        $statusLabel = match ($status) {
            'PENDIENTE' => 'Pendiente de pago',
            'PARCIAL' => 'Pago parcial',
            'SALDADA' => 'Cuenta saldada',
            'SALDO_FAVOR' => 'Saldo a favor',
            default => 'Sin movimientos',
        };
    @endphp

    <section class="py-4 text-center">
        <p class="font-mono text-[9px] tracking-[0.16em] uppercase">Estado de cuenta · Ciclo vigente</p>
        <h1 class="mt-1 font-mono text-sm font-bold">Corte {{ \Illuminate\Support\Carbon::parse($cutoff)->format('d/m/Y') }}</h1>
        <p class="mt-1 text-[10px]">Impreso {{ $generatedAt->format('d/m/Y H:i:s') }}</p>
    </section>

    <dl class="border-y border-dashed border-ink-950 py-3 text-[10px] leading-5">
        <div class="flex justify-between gap-3"><dt>Cliente</dt><dd class="text-right font-semibold">{{ $cliente->nombres_razon_social }}</dd></div>
        @if ($cliente->nro_documento)<div class="flex justify-between gap-3"><dt>Documento</dt><dd class="font-mono">{{ $cliente->nro_documento }}</dd></div>@endif
        @if ($cliente->telefono)<div class="flex justify-between gap-3"><dt>Teléfono</dt><dd class="font-mono">{{ $cliente->telefono }}</dd></div>@endif
        <div class="flex justify-between gap-3"><dt>Estado</dt><dd class="text-right font-semibold">{{ $statusLabel }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Movimientos</dt><dd class="font-mono">{{ $summary['sales_count'] + $summary['collections_count'] }}</dd></div>
    </dl>

    @if ($cycleReset)
        <p class="border-b border-dashed border-ink-950 py-3 text-[9px] leading-4">El historial anterior se cerró con saldo S/ 0,00. Este ticket comienza desde la venta siguiente.</p>
    @endif

    @if ($movements->isEmpty())
        <p class="py-5 text-center text-[10px] leading-4 text-steel-500">{{ $status === 'SALDADA' ? 'Cuenta saldada. El próximo ciclo comenzará con una nueva venta.' : 'No hay ventas ni abonos hasta la fecha de corte.' }}</p>
    @else
        <div class="divide-y divide-dashed divide-steel-300">
            @foreach ($movements as $movement)
                <article class="py-3 text-[10px]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono font-bold">{{ $movement->documento }}</p>
                            <p class="mt-0.5 text-steel-500">{{ \Illuminate\Support\Carbon::parse($movement->fecha_movimiento)->format('d/m/Y H:i') }} · {{ $movement->tipo === 'VENTA' ? 'Venta' : 'Abono' }}</p>
                        </div>
                        <p class="font-mono font-bold">{{ $movement->tipo === 'VENTA' ? '+ '.$money($movement->cargo) : '- '.$money($movement->abono) }}</p>
                    </div>
                    <p class="mt-1 leading-4 text-steel-500">{{ $movement->detalle }}</p>
                    <div class="mt-1 flex justify-between gap-3"><span>Saldo</span><strong class="font-mono">{{ $balance($movement->saldo_acumulado) }}</strong></div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($truncated)
        <p class="border-t border-dashed border-ink-950 py-3 text-[9px] leading-4">El ticket muestra los primeros 200 movimientos. Consulta Excel / CSV o PDF para revisar el historial completo.</p>
    @endif

    <dl class="border-y-2 border-dashed border-ink-950 py-3 text-[11px] leading-6">
        <div class="flex justify-between"><dt>Total ventas</dt><dd class="font-mono">{{ $money($summary['total_sales']) }}</dd></div>
        <div class="flex justify-between"><dt>Total abonado</dt><dd class="font-mono">{{ $money($summary['total_collections']) }}</dd></div>
        <div class="flex justify-between"><dt>Saldo a favor</dt><dd class="font-mono">{{ $money($summary['credit']) }}</dd></div>
        <div class="mt-1 flex justify-between border-t border-dashed border-ink-950 pt-1"><dt class="font-bold">Restante por pagar</dt><dd class="font-mono text-base font-extrabold">{{ $money($summary['remaining']) }}</dd></div>
    </dl>
@endsection
