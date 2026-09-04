<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Estado de cuenta · {{ $proveedor->nombre_razon_social }} · {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; color: #101310; }
            body { margin: 0; padding: 24px; background: #fff; font-size: 11px; }
            header { display: flex; justify-content: space-between; gap: 24px; align-items: end; border-bottom: 2px solid #101310; padding-bottom: 14px; }
            h1 { margin: 3px 0 0; font-size: 25px; text-transform: uppercase; }
            p { margin: 0; }
            .muted { color: #606860; }
            .label { font-size: 8px; letter-spacing: .14em; text-transform: uppercase; }
            .provider { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 12px; }
            .provider div, .summary article { border: 1px solid #d9d8ce; padding: 10px; }
            .provider strong, .summary strong { display: block; margin-top: 5px; font-size: 14px; }
            .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 8px 0 16px; }
            .summary strong { font-size: 16px; }
            table { width: 100%; border-collapse: collapse; font-size: 9px; }
            th { background: #f2f0e7; text-align: left; text-transform: uppercase; letter-spacing: .08em; }
            th, td { border: 1px solid #d9d8ce; padding: 6px; vertical-align: top; }
            .right { text-align: right; }
            .notice { margin: 12px 0; border-left: 4px solid #f0b323; background: #fff1bd; padding: 9px; }
            .actions { display: flex; gap: 8px; margin-bottom: 14px; }
            button, a { border: 0; background: #101310; color: #fff; padding: 9px 13px; font-weight: bold; text-decoration: none; cursor: pointer; }
            @page { size: landscape; margin: 10mm; }
            @media print { body { padding: 0; } .actions { display: none; } thead { display: table-header-group; } tr { break-inside: avoid; } }
        </style>
    </head>
    <body>
        @php
            $money = static function (float|int|string|null $value): string {
                $amount = (float) ($value ?? 0);

                return ($amount < 0 ? '-S/ ' : 'S/ ').number_format(abs($amount), 2, ',', '.');
            };
            $statusLabel = match ($status) {
                'PENDIENTE' => 'Pendiente de pago',
                'PARCIAL' => 'Pago parcial',
                'SALDADA' => 'Cuenta saldada',
                default => 'Sin movimientos',
            };
        @endphp

        <div class="actions">
            <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
            <a href="{{ route('reportes.supplier-account', ['proveedor' => $proveedor, 'hasta' => $cutoff]) }}">Volver al estado de cuenta</a>
        </div>

        <header>
            <div>
                <p class="label muted">Finanzas / Estado de cuenta del proveedor</p>
                <h1>{{ $proveedor->nombre_razon_social }}</h1>
                <p class="muted">Corte al {{ \Illuminate\Support\Carbon::parse($cutoff)->format('d/m/Y') }} · {{ $statusLabel }}</p>
            </div>
            <div style="text-align: right">
                <p><strong>{{ config('app.name') }}</strong></p>
                <p class="muted">Generado {{ $generatedAt->format('d/m/Y H:i') }}</p>
            </div>
        </header>

        <section class="provider" aria-label="Datos del proveedor">
            <div><p class="label muted">Documento</p><strong>{{ $proveedor->nro_documento ?: 'No registrado' }}</strong></div>
            <div><p class="label muted">Teléfono</p><strong>{{ $proveedor->telefono ?: 'No registrado' }}</strong></div>
            <div><p class="label muted">Cuenta / CCI</p><strong>{{ $proveedor->numero_cuenta ?: 'No registrada' }}</strong></div>
            <div><p class="label muted">Dirección</p><strong>{{ $proveedor->direccion ?: 'No registrada' }}</strong></div>
        </section>

        <section class="summary" aria-label="Resumen del estado de cuenta">
            <article><p class="label muted">Total de cargas</p><strong>{{ $money($summary['total_loads']) }}</strong></article>
            <article><p class="label muted">Total pagado</p><strong>{{ $money($summary['total_payments']) }}</strong></article>
            <article><p class="label muted">Total ajustado</p><strong>{{ $money($summary['total_adjustments']) }}</strong></article>
            <article><p class="label muted">Deuda acumulada</p><strong>{{ $money($summary['remaining']) }}</strong></article>
        </section>

        @if ($truncated)
            <p class="notice">La vista imprimible muestra los primeros 1.000 movimientos. Usa la descarga Excel / CSV para obtener el historial completo.</p>
        @endif

        <table aria-label="Movimientos del estado de cuenta del proveedor">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Movimiento</th>
                    <th>Documento</th>
                    <th>Carga</th>
                    <th>Detalle</th>
                    <th class="right">Carga (cargo)</th>
                    <th class="right">Abono / ajuste</th>
                    <th class="right">Saldo acumulado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($movement->fecha_movimiento)->format('d/m/Y H:i') }}</td>
                        <td>{{ $movement->tipo === 'CARGA' ? 'Carga' : ($movement->tipo === 'ABONO' ? 'Abono' : 'Ajuste') }}</td>
                        <td>{{ $movement->documento }}</td>
                        <td>{{ $movement->numero_carga }}</td>
                        <td>{{ $movement->detalle }}</td>
                        <td class="right">{{ (float) $movement->cargo > 0 ? $money($movement->cargo) : '—' }}</td>
                        <td class="right">{{ (float) $movement->abono > 0 ? $money($movement->abono) : '—' }}</td>
                        <td class="right">{{ $money($movement->saldo_acumulado) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No hay movimientos hasta la fecha de corte seleccionada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>
