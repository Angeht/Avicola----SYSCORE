@extends('layouts.app')

@section('title', 'Panel operativo')
@section('section', 'Panel operativo')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    @endphp

    <header class="reveal-up flex flex-col gap-5 border-b border-line pb-7 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Pulso operativo / Hoy</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">
                Resumen de planta
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">
                Ventas, disponibilidad y alertas consolidadas para la jornada actual.
            </p>
        </div>
        <div class="flex items-center gap-3 border-l-4 border-hazard bg-paper px-4 py-3 shadow-sm">
            <span class="font-display text-3xl font-extrabold text-ink-950">{{ now()->format('d') }}</span>
            <span class="font-mono text-[9px] leading-4 tracking-wider text-steel-500 uppercase">
                {{ now()->translatedFormat('M Y') }}<br>{{ now()->translatedFormat('l') }}
            </span>
        </div>
    </header>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores del día">
        <x-stat-card class="reveal-up reveal-up-delay-1" label="Venta del día" :value="$money($salesSummary?->total_ventas)" :meta="$quantity($salesSummary?->cantidad_ventas).' operaciones registradas'" tone="hazard">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 18V9m5 9V5m5 13v-7m5 7V3" /></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card class="reveal-up reveal-up-delay-1" label="Peso vendido" :value="$quantity($salesSummary?->kg_vendidos, 3).' kg'" :meta="$quantity($salesSummary?->pollos_vendidos).' aves despachadas'" tone="signal">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14l-1 13H6L5 7Z" /><path d="M9 7a3 3 0 0 1 6 0m-3 4v5" /></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card class="reveal-up reveal-up-delay-2" label="Stock disponible" :value="$quantity($stockKilograms, 3).' kg'" :meta="$quantity($stockBirds).' aves en saldo'">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /></svg>
            </x-slot:icon>
        </x-stat-card>

        <x-stat-card class="reveal-up reveal-up-delay-2" label="Precio vigente" :value="$currentPrice ? $money($currentPrice->precio_kg) : 'Sin definir'" :meta="$currentPrice ? 'Por kilogramo · versión '.$currentPrice->precio_version_id : 'Registra el precio para iniciar ventas'" :tone="$currentPrice ? 'hazard' : 'danger'">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12 12 20 4 12V4h8l8 8Z" /><circle cx="8.5" cy="8.5" r="1.2" /></svg>
            </x-slot:icon>
        </x-stat-card>
    </section>

    <section class="reveal-up reveal-up-delay-2 mt-6 border border-line bg-paper shadow-panel" aria-labelledby="cash-title">
        <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center {{ $openCashSession ? 'bg-signal text-white' : 'bg-danger-soft text-danger' }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg>
                </span>
                <div>
                    <p id="cash-title" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-steel-500 uppercase">Estado de la jornada</p>
                    <p class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">
                        {{ $openCashSession ? 'Apertura registrada' : 'Apertura pendiente' }}
                    </p>
                    <p class="mt-1 text-sm text-steel-500">
                        @if ($openCashSession)
                            Iniciada {{ Illuminate\Support\Carbon::parse($openCashSession->apertura_at)->diffForHumans() }} con {{ $money($openCashSession->monto_apertura) }}.
                        @else
                            Registra la apertura del día antes de procesar movimientos de efectivo.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-col gap-2 self-start sm:flex-row sm:self-center">
                <span class="inline-flex min-h-9 items-center justify-center border px-3 font-mono text-[9px] font-semibold tracking-wider uppercase {{ $openCashSession ? 'border-signal/30 bg-signal-soft text-signal' : 'border-danger/25 bg-danger-soft text-danger' }}">
                    {{ $openCashSession ? 'Operativa' : 'Requiere atención' }}
                </span>
                @if ($authenticatedUser?->tienePermiso('CAJA_ABRIR_CERRAR'))
                    <a href="{{ $openCashSession ? route('caja.show', $openCashSession->id) : route('caja.create') }}" class="inline-flex min-h-9 items-center justify-center bg-ink-950 px-4 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">
                        {{ $openCashSession ? 'Ver jornada' : 'Apertura del día' }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.55fr)]">
        <section class="reveal-up reveal-up-delay-2 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="stock-title">
            <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6">
                <div>
                    <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Inventario / Actual</p>
                    <h2 id="stock-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Saldo de mercadería</h2>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $stockBalances->count() }} productos</span>
                    @if ($authenticatedUser?->tienePermiso('VENTAS_REGISTRAR'))
                        <a href="{{ route('ventas.create') }}" class="inline-flex min-h-9 items-center bg-hazard px-3 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Nueva venta</a>
                    @endif
                    @if ($authenticatedUser?->tienePermiso('CARGAS_REGISTRAR'))
                        <a href="{{ route('cargas-proveedor.create') }}" class="inline-flex min-h-9 items-center bg-ink-950 px-3 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Nueva carga</a>
                    @endif
                    @if ($authenticatedUser?->tienePermiso('COBRANZAS_REGISTRAR'))
                        <a href="{{ route('cobranzas.create') }}" class="inline-flex min-h-9 items-center border border-signal bg-signal-soft px-3 font-display text-xs font-bold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Nueva cobranza</a>
                    @endif
                    @if ($authenticatedUser?->tienePermiso('MERCADERIA_AJUSTAR'))
                        <a href="{{ route('mercaderia.create') }}" class="inline-flex min-h-9 items-center border border-danger/30 bg-danger-soft px-3 font-display text-xs font-bold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Nuevo ajuste</a>
                    @endif
                    @if ($authenticatedUser?->tienePermiso('MERCADERIA_CONCILIAR'))
                        <a href="{{ route('conciliaciones-mercaderia.create') }}" class="inline-flex min-h-9 items-center border border-hazard/40 bg-hazard-soft px-3 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard">Conciliar stock</a>
                    @endif
                </div>
            </div>

            @if ($stockBalances->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto grid size-12 place-items-center border border-line bg-canvas text-steel-500">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /></svg>
                    </span>
                    <p class="mt-4 font-display text-xl font-bold text-ink-950 uppercase">Sin existencias registradas</p>
                    <p class="mt-2 text-sm text-steel-500">Los saldos aparecerán después de registrar cargas de mercadería.</p>
                    @if ($authenticatedUser?->tienePermiso('CARGAS_REGISTRAR'))
                        <a href="{{ route('cargas-proveedor.create') }}" class="mt-5 inline-flex min-h-10 items-center justify-center bg-ink-950 px-5 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Registrar primera carga</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-left">
                        <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-semibold">Producto</th>
                                <th scope="col" class="px-6 py-3 text-right font-semibold">Aves</th>
                                <th scope="col" class="px-6 py-3 text-right font-semibold">Kilogramos</th>
                                <th scope="col" class="px-6 py-3 text-right font-semibold">Control</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($stockBalances as $balance)
                                <tr class="transition hover:bg-hazard-soft/25">
                                    <td class="px-6 py-4 font-semibold text-ink-950">{{ $balance->producto }}</td>
                                    <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $quantity($balance->pollos_disponibles) }}</td>
                                    <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $quantity($balance->kg_disponibles, 3) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="inline-flex px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $balance->requiere_revision ? 'bg-danger-soft text-danger' : 'bg-signal-soft text-signal' }}">
                                            {{ $balance->requiere_revision ? 'Revisar' : 'Conforme' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid content-start gap-6">
            <section class="reveal-up reveal-up-delay-3 border border-line bg-paper shadow-panel" aria-labelledby="alerts-title">
                <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5">
                    <div>
                        <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-danger uppercase">Seguimiento</p>
                        <h2 id="alerts-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Alertas operativas</h2>
                    </div>
                    <span class="grid size-8 place-items-center bg-danger-soft font-display font-bold text-danger">{{ $alerts->count() }}</span>
                </div>

                @if ($alerts->isEmpty())
                    <div class="px-5 py-9 text-center">
                        <span class="mx-auto grid size-10 place-items-center bg-signal-soft text-signal">✓</span>
                        <p class="mt-3 font-semibold text-ink-950">Todo bajo control</p>
                        <p class="mt-1 text-sm text-steel-500">No hay alertas pendientes.</p>
                    </div>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($alerts as $alert)
                            <li class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1.5 size-2 shrink-0 bg-danger"></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink-950">{{ $alert->mensaje }}</p>
                                        <p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">
                                            {{ str_replace('_', ' ', $alert->tipo_alerta) }} · {{ $alert->fecha ? Illuminate\Support\Carbon::parse($alert->fecha)->translatedFormat('d M') : 'Sin fecha' }}
                                        </p>
                                    </div>
                                    @if ($alert->monto !== null)
                                        <span class="ml-auto shrink-0 font-mono text-xs font-semibold text-danger">{{ $money($alert->monto) }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="reveal-up reveal-up-delay-3 grid grid-cols-2 border border-line bg-ink-950 text-white shadow-panel" aria-label="Directorio activo">
                <div class="border-r border-white/10 p-5">
                    <span class="font-display text-4xl font-extrabold text-hazard">{{ $activeClients }}</span>
                    <p class="mt-2 font-mono text-[9px] tracking-wider text-steel-300 uppercase">Clientes activos</p>
                </div>
                <div class="p-5">
                    <span class="font-display text-4xl font-extrabold text-white">{{ $activeSuppliers }}</span>
                    <p class="mt-2 font-mono text-[9px] tracking-wider text-steel-300 uppercase">Proveedores activos</p>
                </div>
            </section>
        </div>
    </div>
@endsection
