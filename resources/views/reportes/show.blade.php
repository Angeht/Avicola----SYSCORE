<div>
    <!-- It is not the man who has too little, but the man who craves more, that is poor. - Seneca -->
</div>
@extends('layouts.app')

@section('title', $definition['title'])
@section('section', 'Reportes')

@section('content')
    @php
        $formatValue = static function (mixed $value, string $format): string {
            return match ($format) {
                'date' => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—',
                'integer' => number_format((int) $value, 0, ',', '.'),
                'decimal3' => number_format((float) $value, 3, ',', '.'),
                'money' => 'S/ '.number_format((float) $value, 2, ',', '.'),
                'money4' => 'S/ '.number_format((float) $value, 4, ',', '.'),
                'status' => str((string) $value)->lower()->replace('_', ' ')->headline()->toString(),
                default => filled($value) ? (string) $value : '—',
            };
        };
    @endphp

    <header class="reveal-up border-b border-ink-950 pb-7">
        <a href="{{ route('reportes.index') }}" class="inline-flex items-center gap-2 font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase transition hover:text-ink-950">← Centro de reportes</a>
        <div class="mt-5 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div><p class="font-mono text-[10px] font-semibold tracking-[0.2em] text-signal uppercase">{{ $definition['eyebrow'] }}</p><h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-ink-950 uppercase sm:text-5xl">{{ $definition['title'] }}</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $definition['description'] }}</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reportes.csv', ['report' => $reportKey, ...$filters]) }}" class="inline-flex min-h-11 items-center gap-2 border border-signal bg-signal-soft px-4 font-display text-xs font-bold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white"><span aria-hidden="true">↓</span> Excel / CSV</a>
                <a href="{{ route('reportes.print', ['report' => $reportKey, ...$filters]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 bg-ink-950 px-4 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800"><span aria-hidden="true">▣</span> Imprimir / PDF</a>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('reportes.show', $reportKey) }}" class="reveal-up reveal-up-delay-1 mt-6 border border-line bg-paper p-5 shadow-panel" aria-label="Filtros del reporte">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @if ($reportKey === 'cuentas-cobrar')
                <input type="hidden" name="desde" value="2000-01-01">
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Corte al</span><input type="date" name="hasta" required value="{{ $filters['hasta'] }}" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"></label>
            @else
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Desde</span><input type="date" name="desde" required value="{{ $filters['desde'] }}" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"></label>
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Hasta</span><input type="date" name="hasta" required value="{{ $filters['hasta'] }}" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"></label>
            @endif

            @if (in_array('cliente', $definition['filters'], true))
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Cliente</span><select name="cliente_id" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"><option value="">Todos</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected((string) ($filters['cliente_id'] ?? '') === (string) $client->id)>{{ $client->nombres_razon_social }}</option>@endforeach</select></label>
            @endif
            @if (in_array('proveedor', $definition['filters'], true))
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Proveedor</span><select name="proveedor_id" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"><option value="">Todos</option>@foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected((string) ($filters['proveedor_id'] ?? '') === (string) $provider->id)>{{ $provider->nombre_razon_social }}</option>@endforeach</select></label>
            @endif
            @if (in_array('producto', $definition['filters'], true))
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Producto</span><select name="producto_id" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"><option value="">Todos</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['producto_id'] ?? '') === (string) $product->id)>{{ $product->nombre }}</option>@endforeach</select></label>
            @endif
            @if (in_array('usuario', $definition['filters'], true))
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Responsable</span><select name="usuario_id" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950"><option value="">Todos</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['usuario_id'] ?? '') === (string) $user->id)>{{ $user->nombreCompleto() }}</option>@endforeach</select></label>
            @endif
            @if (in_array('estado', $definition['filters'], true))
                <label><span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Estado</span><select name="estado" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">@foreach ($definition['states'] as $value => $label)<option value="{{ $value }}" @selected(($filters['estado'] ?? 'TODOS') === $value)>{{ $label }}</option>@endforeach</select></label>
            @endif

            <div class="flex items-end gap-2"><a href="{{ route('reportes.show', $reportKey) }}" class="inline-flex min-h-11 items-center justify-center border border-line px-4 font-display text-xs font-bold uppercase transition hover:border-ink-950">Limpiar</a><button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center bg-hazard px-5 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white">Actualizar</button></div>
        </div>
        @error('hasta')<p class="mt-3 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
    </form>

    @if ($reportKey === 'cuentas-cobrar')
        <div class="reveal-up reveal-up-delay-1 mt-4 border-l-4 border-signal bg-signal-soft px-4 py-3 text-sm leading-6 text-ink-700">
            Cada fila corresponde a un cliente y muestra sus ventas realizadas, la deuda total generada, todos sus abonos y el importe que todavía falta pagar.
        </div>
    @endif

    <section class="reveal-up reveal-up-delay-1 mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del reporte">
        @foreach ($summary as $item)
            <article class="border border-line bg-paper p-4 shadow-panel"><p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">{{ $item['label'] }}</p><p class="mt-2 font-display text-2xl font-bold text-ink-950">{{ $formatValue($item['value'], $item['format']) }}</p></article>
        @endforeach
    </section>

    <section class="reveal-up reveal-up-delay-2 mt-5 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="report-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6"><div><p class="font-mono text-[8px] font-semibold tracking-[0.18em] text-signal uppercase">{{ $reportKey === 'cuentas-cobrar' ? 'Corte al '.$filters['hasta'] : $filters['desde'].' / '.$filters['hasta'] }}</p><h2 id="report-results-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Resultados</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[8px] text-steel-500 uppercase">{{ $rows->total() }} filas</span></div>
        @if ($rows->isEmpty())
            <x-empty-state title="Sin datos en este periodo" description="Ajusta el rango de fechas o los filtros para ampliar la consulta." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-max border-collapse text-left">
                    <thead class="bg-canvas font-mono text-[8px] tracking-[0.13em] text-steel-500 uppercase"><tr>@foreach ($definition['columns'] as $column)<th class="whitespace-nowrap px-4 py-3 font-semibold">{{ $column['label'] }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-hazard-soft/20">
                                @foreach ($definition['columns'] as $field => $column)
                                    <td class="whitespace-nowrap px-4 py-3 text-sm {{ in_array($column['format'], ['integer', 'decimal3', 'money', 'money4'], true) ? 'text-right font-mono' : '' }}">
                                        @if ($reportKey === 'cuentas-cobrar' && $field === 'cliente')
                                            <p class="font-semibold text-ink-950">{{ $formatValue($row->{$field}, $column['format']) }}</p>
                                            <a href="{{ route('reportes.customer-account', ['cliente' => $row->cliente_id, 'hasta' => $filters['hasta']]) }}" class="mt-1 inline-flex font-mono text-[8px] font-semibold tracking-wider text-signal uppercase transition hover:text-ink-950">Ver detalle →</a>
                                        @elseif ($column['format'] === 'status')
                                            <span class="inline-flex border border-line bg-canvas px-2 py-1 font-mono text-[8px] font-semibold tracking-wider text-ink-700 uppercase">{{ $formatValue($row->{$field}, $column['format']) }}</span>
                                        @else
                                            {{ $formatValue($row->{$field}, $column['format']) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-catalog-pagination :paginator="$rows" />
        @endif
    </section>
@endsection
