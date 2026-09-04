<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
</div>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $definition['title'] }} · {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light; font-family: Arial, sans-serif; color: #101310; }
            body { margin: 0; padding: 24px; background: #fff; font-size: 11px; }
            header { display: flex; justify-content: space-between; gap: 24px; align-items: end; border-bottom: 2px solid #101310; padding-bottom: 14px; }
            h1 { margin: 3px 0 0; font-size: 25px; text-transform: uppercase; }
            p { margin: 0; }
            .muted { color: #606860; }
            .label { font-size: 8px; letter-spacing: .14em; text-transform: uppercase; }
            .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 16px 0; }
            .summary article { border: 1px solid #d9d8ce; padding: 10px; }
            .summary strong { display: block; margin-top: 5px; font-size: 16px; }
            table { width: 100%; border-collapse: collapse; font-size: 9px; }
            th { background: #f2f0e7; text-align: left; text-transform: uppercase; letter-spacing: .08em; }
            th, td { border: 1px solid #d9d8ce; padding: 6px; vertical-align: top; }
            .notice { margin: 12px 0; border-left: 4px solid #f0b323; background: #fff1bd; padding: 9px; }
            .actions { display: flex; gap: 8px; margin-bottom: 14px; }
            button, a { border: 0; background: #101310; color: #fff; padding: 9px 13px; font-weight: bold; text-decoration: none; cursor: pointer; }
            @page { size: landscape; margin: 10mm; }
            @media print { body { padding: 0; } .actions { display: none; } thead { display: table-header-group; } tr { break-inside: avoid; } }
        </style>
    </head>
    <body>
        @php
            $formatUnitPrice = static function (mixed $value): string {
                $formatted = number_format((float) $value, 4, ',', '.');

                return 'S/ '.(preg_replace('/0{1,2}$/', '', $formatted) ?? $formatted);
            };
            $formatValue = static function (mixed $value, string $format) use ($formatUnitPrice): string {
                return match ($format) {
                    'date' => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—',
                    'integer' => number_format((int) $value, 0, ',', '.'),
                    'decimal3' => number_format((float) $value, 3, ',', '.'),
                    'money' => 'S/ '.number_format((float) $value, 2, ',', '.'),
                    'money4' => $formatUnitPrice($value),
                    'status' => str((string) $value)->lower()->replace('_', ' ')->headline()->toString(),
                    default => filled($value) ? (string) $value : '—',
                };
            };
        @endphp

        <div class="actions"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button><a href="{{ route('reportes.show', ['report' => $reportKey, ...$filters]) }}">Volver al reporte</a></div>
        <header><div><p class="label muted">{{ $definition['eyebrow'] }}</p><h1>{{ $definition['title'] }}</h1><p class="muted">{{ in_array($reportKey, ['cuentas-cobrar', 'deudas-proveedores'], true) ? 'Corte al '.\Illuminate\Support\Carbon::parse($filters['hasta'])->format('d/m/Y') : 'Del '.\Illuminate\Support\Carbon::parse($filters['desde'])->format('d/m/Y').' al '.\Illuminate\Support\Carbon::parse($filters['hasta'])->format('d/m/Y') }}</p></div><div style="text-align:right"><p><strong>{{ config('app.name') }}</strong></p><p class="muted">Generado {{ $generatedAt->format('d/m/Y H:i') }}</p></div></header>

        <section class="summary">@foreach ($summary as $item)<article><p class="label muted">{{ $item['label'] }}</p><strong>{{ $formatValue($item['value'], $item['format']) }}</strong></article>@endforeach</section>
        @if ($reportKey === 'cuentas-cobrar')<p class="notice">Una fila por cliente con sus ventas realizadas, deuda total, abonos acumulados y saldo restante por pagar.</p>@endif
        @if ($reportKey === 'deudas-proveedores')<p class="notice">Una fila por proveedor con sus cargas, pagos, ajustes y deuda acumulada al corte.</p>@endif
        @if ($truncated)<p class="notice">La vista imprimible muestra las primeras 1.000 filas. Usa la exportación Excel / CSV para obtener todos los registros.</p>@endif

        <table><thead><tr>@foreach ($definition['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>@forelse ($rows as $row)<tr>@foreach ($definition['columns'] as $field => $column)<td>{{ $formatValue($row->{$field}, $column['format']) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($definition['columns']) }}">No hay datos para los filtros seleccionados.</td></tr>@endforelse</tbody></table>
    </body>
</html>
