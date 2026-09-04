@extends('layouts.app')

@section('title', 'Estado de cuenta de '.$cliente->nombres_razon_social)
@section('section', 'Reportes')

@section('content')
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
        $statusClass = match ($status) {
            'SALDADA' => 'border-signal/30 bg-signal-soft text-signal',
            'SALDO_FAVOR' => 'border-hazard/40 bg-hazard-soft text-ink-950',
            'PENDIENTE', 'PARCIAL' => 'border-danger/30 bg-danger-soft text-danger',
            default => 'border-line bg-canvas text-steel-500',
        };
    @endphp

    <header class="reveal-up border-b border-ink-950 pb-7">
        <a href="{{ route('reportes.show', ['report' => 'cuentas-cobrar', 'hasta' => $cutoff]) }}" class="inline-flex items-center gap-2 font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase transition hover:text-ink-950">← Deudas y abonos por cliente</a>
        <div class="mt-5 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-4xl">
                <p class="font-mono text-[10px] font-semibold tracking-[0.2em] text-signal uppercase">Finanzas / Cliente #{{ $cliente->id }}</p>
                <h1 class="mt-2 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Estado de cuenta</h1>
                <p class="mt-3 font-display text-2xl font-bold text-ink-700 uppercase">{{ $cliente->nombres_razon_social }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reportes.customer-account.csv', ['cliente' => $cliente, 'hasta' => $cutoff]) }}" class="inline-flex min-h-11 items-center gap-2 border border-signal bg-signal-soft px-4 font-display text-xs font-bold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white"><span aria-hidden="true">↓</span> Descargar Excel / CSV</a>
                <a href="{{ route('reportes.customer-account.print', ['cliente' => $cliente, 'hasta' => $cutoff]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 bg-ink-950 px-4 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800"><span aria-hidden="true">▣</span> Imprimir / PDF</a>
                <a href="{{ route('reportes.customer-account.ticket', ['cliente' => $cliente, 'hasta' => $cutoff]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 border border-ink-950 bg-paper px-4 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white"><span aria-hidden="true">▤</span> Imprimir ticket</a>
                <span class="inline-flex min-h-11 w-fit items-center border px-4 font-mono text-[9px] font-semibold tracking-wider uppercase {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
    </header>

    <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="reveal-up reveal-up-delay-1 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de la cuenta">
            <article class="border-l-4 border-ink-950 bg-paper p-5 shadow-panel">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Total de ventas</p>
                <p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($summary['total_sales']) }}</p>
                <p class="mt-2 text-xs text-steel-500">{{ $summary['sales_count'] }} {{ $summary['sales_count'] === 1 ? 'venta registrada' : 'ventas registradas' }}</p>
            </article>
            <article class="border-l-4 border-signal bg-paper p-5 shadow-panel">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Pagos y ajustes</p>
                <p class="mt-2 font-display text-3xl font-extrabold text-signal">{{ $money($summary['total_credits']) }}</p>
                <p class="mt-2 text-xs text-steel-500">{{ $summary['collections_count'] }} pago(s) · {{ $summary['adjustments_count'] }} ajuste(s)</p>
            </article>
            <article class="border-l-4 border-danger bg-paper p-5 shadow-panel">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Restante por pagar</p>
                <p class="mt-2 font-display text-3xl font-extrabold {{ $summary['remaining'] > 0 ? 'text-danger' : 'text-steel-500' }}">{{ $money($summary['remaining']) }}</p>
                <p class="mt-2 text-xs text-steel-500">Deuda pendiente al corte</p>
            </article>
            <article class="industrial-hatch border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-panel">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-hazard uppercase">Saldo a favor</p>
                <p class="mt-2 font-display text-3xl font-extrabold">{{ $money($summary['credit']) }}</p>
                <p class="mt-2 text-xs text-steel-300">Exceso de pagos sobre ventas</p>
            </article>
        </section>

        <form method="GET" action="{{ route('reportes.customer-account', $cliente) }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper p-5 shadow-panel" aria-label="Fecha de corte del estado de cuenta">
            <label for="hasta" class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Corte al</label>
            <div class="mt-2 flex gap-2">
                <input id="hasta" type="date" name="hasta" required value="{{ $cutoff }}" class="min-h-11 min-w-0 flex-1 border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-hazard px-4 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Actualizar</button>
            </div>
            @error('hasta')<p class="mt-3 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
            <p class="mt-3 text-xs leading-5 text-steel-500">Incluye ventas, pagos y ajustes comerciales válidos registrados hasta esta fecha.</p>
        </form>
    </div>

    <section class="reveal-up reveal-up-delay-1 mt-5 border border-line bg-paper shadow-panel" aria-labelledby="customer-data-title">
        <div class="grid gap-0 md:grid-cols-3">
            <div class="border-b border-line p-5 md:border-r md:border-b-0">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Documento</p>
                <p class="mt-2 font-semibold text-ink-950">{{ $cliente->nro_documento ?: 'No registrado' }}</p>
            </div>
            <div class="border-b border-line p-5 md:border-r md:border-b-0">
                <p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Teléfono</p>
                <p class="mt-2 font-semibold text-ink-950">{{ $cliente->telefono ?: 'No registrado' }}</p>
            </div>
            <div class="p-5">
                <p id="customer-data-title" class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Dirección</p>
                <p class="mt-2 font-semibold text-ink-950">{{ $cliente->direccion ?: 'No registrada' }}</p>
            </div>
        </div>
    </section>

    <section class="reveal-up reveal-up-delay-2 mt-5 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="account-movements-title">
        <div class="flex flex-col gap-3 border-b border-line px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6">
            <div>
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Ventas + pagos + ajustes / Corte {{ \Illuminate\Support\Carbon::parse($cutoff)->format('d/m/Y') }}</p>
                <h2 id="account-movements-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Movimientos de la cuenta</h2>
            </div>
            <span class="w-fit border border-line px-2.5 py-1 font-mono text-[8px] text-steel-500 uppercase">{{ $movements->total() }} movimientos</span>
        </div>

        @if ($movements->isEmpty())
            <x-empty-state title="Sin movimientos" description="Este cliente no tiene ventas ni pagos válidos hasta la fecha de corte seleccionada." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[920px] border-collapse text-left" aria-label="Movimientos detallados del cliente">
                    <thead class="bg-canvas font-mono text-[8px] tracking-[0.13em] text-steel-500 uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Fecha</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Movimiento</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Documento</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Detalle</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Venta (cargo)</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Abono / ajuste</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Saldo acumulado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($movements as $movement)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-ink-700">{{ \Illuminate\Support\Carbon::parse($movement->fecha_movimiento)->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4"><span class="inline-flex border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $movement->tipo === 'VENTA' ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $movement->tipo === 'VENTA' ? 'Venta' : ($movement->tipo === 'ABONO' ? 'Abono' : 'Ajuste') }}</span></td>
                                <td class="px-5 py-4">@if ($movement->tipo === 'AJUSTE')<span class="font-mono text-xs font-semibold text-ink-950">{{ $movement->documento }}</span>@else<a href="{{ $movement->tipo === 'VENTA' ? route('ventas.show', $movement->referencia_id) : route('cobranzas.show', $movement->referencia_id) }}" class="font-mono text-xs font-semibold text-ink-950 underline decoration-line underline-offset-4 transition hover:decoration-signal">{{ $movement->documento }}</a>@endif</td>
                                <td class="max-w-sm px-5 py-4 text-sm text-steel-500">{{ $movement->detalle }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-semibold text-ink-950">{{ (float) $movement->cargo > 0 ? $money($movement->cargo) : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-semibold text-signal">{{ (float) $movement->abono > 0 ? $money($movement->abono) : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-display text-lg font-bold {{ (float) $movement->saldo_acumulado > 0 ? 'text-danger' : 'text-ink-950' }}">{{ $balance($movement->saldo_acumulado) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($movements as $movement)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="inline-flex border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $movement->tipo === 'VENTA' ? 'border-danger/30 bg-danger-soft text-danger' : 'border-signal/30 bg-signal-soft text-signal' }}">{{ $movement->tipo === 'VENTA' ? 'Venta' : ($movement->tipo === 'ABONO' ? 'Abono' : 'Ajuste') }}</span>
                                <p class="mt-2 text-xs text-steel-500">{{ \Illuminate\Support\Carbon::parse($movement->fecha_movimiento)->format('d/m/Y H:i') }}</p>
                            </div>
                            <p class="text-right font-display text-xl font-bold {{ $movement->tipo === 'VENTA' ? 'text-ink-950' : 'text-signal' }}">{{ $money($movement->tipo === 'VENTA' ? $movement->cargo : $movement->abono) }}</p>
                        </div>
                        @if ($movement->tipo === 'AJUSTE')<span class="mt-4 inline-flex font-mono text-xs font-semibold text-ink-950">{{ $movement->documento }}</span>@else<a href="{{ $movement->tipo === 'VENTA' ? route('ventas.show', $movement->referencia_id) : route('cobranzas.show', $movement->referencia_id) }}" class="mt-4 inline-flex font-mono text-xs font-semibold text-ink-950 underline decoration-line underline-offset-4">{{ $movement->documento }}</a>@endif
                        <p class="mt-2 text-sm text-steel-500">{{ $movement->detalle }}</p>
                        <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
                            <span class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Saldo después del movimiento</span>
                            <strong class="font-display text-lg text-ink-950">{{ $balance($movement->saldo_acumulado) }}</strong>
                        </div>
                    </article>
                @endforeach
            </div>

            <x-catalog-pagination :paginator="$movements" />
        @endif
    </section>
@endsection
