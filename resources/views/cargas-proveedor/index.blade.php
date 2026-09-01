@extends('layouts.app')

@section('title', 'Cargas de proveedor')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
        $paymentLabel = fn (?string $status): string => match ($status) {
            'ANULADA' => 'Anulada',
            'SIN_PESAJES' => 'Sin pesajes',
            'SALDADA' => 'Saldada',
            'PARCIAL' => 'Pago parcial',
            'PAGO_ATRASADO' => 'Pago atrasado',
            default => 'Pendiente hoy',
        };
        $paymentClass = fn (?string $status): string => match ($status) {
            'ANULADA' => 'border-danger/30 bg-danger-soft text-danger',
            'SIN_PESAJES' => 'border-steel-300 bg-canvas text-steel-500',
            'SALDADA' => 'border-signal/30 bg-signal-soft text-signal',
            'PAGO_ATRASADO' => 'border-danger/30 bg-danger-soft text-danger',
            default => 'border-hazard/40 bg-hazard-soft text-ink-950',
        };
    @endphp

    <x-catalog-header
        eyebrow="Operación / Abastecimiento"
        title="Cargas de proveedor"
        description="Autoriza cada recepción con su costo por kg y registra después los pesajes que alimentan el inventario real."
        :count="$loads->total()"
        count-label="Cargas registradas"
        :create-href="auth()->user()?->tienePermiso('CARGAS_REGISTRAR') ? route('cargas-proveedor.create') : null"
        create-label="Nueva carga"
    />

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen de cargas de hoy">
        <x-stat-card label="Cargas de hoy" :value="$quantity($todaySummary?->cantidad_cargas)" meta="Recepciones registradas" tone="hazard">
            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8h12v10H3V8Zm12 3h3l3 3v4h-6v-7Z" /><circle cx="7" cy="19" r="2" /><circle cx="18" cy="19" r="2" /></svg></x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Aves recibidas" :value="$quantity($todaySummary?->cantidad_pollos)" meta="Unidades ingresadas" tone="signal">
            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 16c-2.5-1.5-3-5 0-7 0-3 2-5 5-5s5 2 5 5c3 2 2.5 5.5 0 7H7Z" /><path d="M10 16v4m4-4v4M9 20h2m2 0h2" /></svg></x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Peso neto" :value="$quantity($todaySummary?->peso_neto_kg, 3).' kg'" meta="Ingreso neto de mercadería">
            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14l-1 13H6L5 7Z" /><path d="M9 7a3 3 0 0 1 6 0m-3 4v5" /></svg></x-slot:icon>
        </x-stat-card>
        <x-stat-card label="Costo recibido" :value="$money($todaySummary?->costo_total)" meta="Compromiso con proveedores" tone="danger">
            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 13h8m-4-4v8" /></svg></x-slot:icon>
        </x-stat-card>
    </section>

    <form method="GET" action="{{ route('cargas-proveedor.index') }}" class="reveal-up reveal-up-delay-1 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_210px_auto]">
        <div class="relative">
            <label for="load-search" class="sr-only">Buscar carga</label>
            <svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
            <input id="load-search" name="buscar" type="search" value="{{ $search }}" placeholder="Número, proveedor o producto..." class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40">
        </div>
        <div>
            <label for="load-date" class="sr-only">Filtrar por fecha</label>
            <input id="load-date" name="fecha" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 lg:flex-none">Filtrar</button>
            @if ($search !== '' || $date !== today()->toDateString())
                <a href="{{ route('cargas-proveedor.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Limpiar filtros">×</a>
            @endif
        </div>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="loads-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6">
            <div>
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Inventario / Entradas</p>
                <h2 id="loads-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Historial de recepciones</h2>
            </div>
            <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $loads->total() }} registros</span>
        </div>

        @if ($loads->isEmpty())
            <x-empty-state title="No hay cargas registradas" description="Registra la primera recepción para comenzar a construir el saldo de mercadería." :action-href="route('cargas-proveedor.create')" action-label="Registrar carga">
                <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 8h12v10H3V8Zm12 3h3l3 3v4h-6v-7Z" /><circle cx="7" cy="19" r="2" /><circle cx="18" cy="19" r="2" /></svg></x-slot:icon>
            </x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1120px] border-collapse text-left" aria-label="Historial de cargas de proveedor">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">Carga</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Proveedor / Producto</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Aves</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Peso neto</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Costo</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Pago</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($loads as $load)
                            @php($loadSummary = $summaries->get($load->id))
                            @php($loadBalance = $balances->get($load->id))
                            <tr class="transition hover:bg-hazard-soft/20 {{ $load->estaAnulada() ? 'bg-danger-soft/20' : '' }}">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $load->numero_carga }}</p><p class="mt-1 text-xs text-steel-500">{{ $load->fecha_carga->format('d/m/Y') }} · {{ $load->pesajes_count }} pesaje(s)</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $load->proveedor->nombre_razon_social }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $load->producto->nombre }}</p></td>
                                <td class="px-6 py-4 text-right font-mono text-xs text-ink-700">{{ $quantity($loadSummary?->cantidad_pollos) }}</td>
                                <td class="px-6 py-4 text-right"><p class="font-mono text-xs font-semibold text-ink-950">{{ $quantity($loadSummary?->peso_neto_kg, 3) }} kg</p><p class="mt-1 text-[10px] text-steel-500">Bruto {{ $quantity($loadSummary?->peso_bruto_kg, 3) }} kg</p></td>
                                <td class="px-6 py-4 text-right"><p class="font-mono text-xs font-semibold text-ink-950">{{ $money($load->costo_total) }}</p><p class="mt-1 text-[10px] text-steel-500">{{ $money($load->costo_kg) }} / kg</p></td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $paymentClass($loadBalance?->estado_pago) }}">{{ $paymentLabel($loadBalance?->estado_pago) }}</span><p class="mt-1.5 text-[10px] text-steel-500">Saldo {{ $money($loadBalance?->saldo_pendiente) }}</p></td>
                                <td class="px-6 py-4 text-right">
                                    @if ($load->pesajes_count === 0 && ! $load->estaAnulada() && auth()->user()?->tienePermiso('CARGAS_REGISTRAR'))
                                        <a href="{{ route('cargas-proveedor.pesajes.create', $load) }}" class="inline-flex min-h-9 items-center bg-hazard px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Registrar pesajes</a>
                                    @else
                                        <a href="{{ route('cargas-proveedor.show', $load) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Ver detalle</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($loads as $load)
                    @php($loadSummary = $summaries->get($load->id))
                    @php($loadBalance = $balances->get($load->id))
                    <article class="p-5 {{ $load->estaAnulada() ? 'bg-danger-soft/20' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0"><p class="truncate font-mono text-xs font-semibold text-ink-950">{{ $load->numero_carga }}</p><p class="mt-1 text-xs text-steel-500">{{ $load->fecha_carga->format('d/m/Y') }} · {{ $load->pesajes_count }} pesaje(s)</p></div>
                            <span class="shrink-0 border px-2 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $paymentClass($loadBalance?->estado_pago) }}">{{ $paymentLabel($loadBalance?->estado_pago) }}</span>
                        </div>
                        <p class="mt-4 font-semibold text-ink-950">{{ $load->proveedor->nombre_razon_social }}</p>
                        <p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $load->producto->nombre }}</p>
                        <dl class="mt-4 grid grid-cols-3 gap-3 border-y border-line py-4">
                            <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Aves</dt><dd class="mt-1 font-semibold text-ink-950">{{ $quantity($loadSummary?->cantidad_pollos) }}</dd></div>
                            <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Peso neto</dt><dd class="mt-1 font-semibold text-ink-950">{{ $quantity($loadSummary?->peso_neto_kg, 3) }} kg</dd></div>
                            <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Costo</dt><dd class="mt-1 font-semibold text-ink-950">{{ $money($load->costo_total) }}</dd></div>
                        </dl>
                        @if ($load->pesajes_count === 0 && ! $load->estaAnulada() && auth()->user()?->tienePermiso('CARGAS_REGISTRAR'))
                            <a href="{{ route('cargas-proveedor.pesajes.create', $load) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center bg-hazard font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Registrar pesajes</a>
                        @else
                            <a href="{{ route('cargas-proveedor.show', $load) }}" class="mt-4 inline-flex min-h-10 w-full items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase transition active:bg-ink-950 active:text-white">Ver detalle</a>
                        @endif
                    </article>
                @endforeach
            </div>

            <x-catalog-pagination :paginator="$loads" />
        @endif
    </section>
@endsection
