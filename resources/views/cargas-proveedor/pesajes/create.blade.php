@extends('layouts.app')

@section('title', 'Registrar pesajes')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $oldWeighings = old('pesajes', [[
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => '',
            'peso_bruto_kg' => '',
            'tara_unitaria_aplicada_kg' => '0.000',
            'observacion' => null,
        ]]);
        $hasUnconfiguredCrateTare = $crateTypes->contains(
            fn ($crateType): bool => (float) $crateType->tara_referencial_kg === 0.0,
        );
        $money = fn (float|int|string|null $value, int $decimals = 2): string => 'S/ '.number_format((float) ($value ?? 0), $decimals, ',', '.');
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <a href="{{ route('cargas-proveedor.show', $load) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver al detalle</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Balanza / {{ $load->numero_carga }}</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar pesajes</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">La carga ya está autorizada. Agrega las pesadas recibidas y el costo total se recalculará usando todo el peso neto acumulado.</p>
        </div>
        <span class="inline-flex min-h-12 items-center justify-center border border-signal/30 bg-signal-soft px-5 font-mono text-[10px] font-semibold tracking-wider text-signal uppercase">Paso 02 habilitado</span>
    </header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Datos autorizados de la carga">
        <div class="border-l-4 border-hazard bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Proveedor</p><p class="mt-2 font-display text-xl font-extrabold text-ink-950 uppercase">{{ $load->proveedor->nombre_razon_social }}</p><p class="mt-1 text-xs text-steel-500">{{ $load->producto->nombre }}</p></div>
        <div class="border-l-4 border-steel-300 bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Fecha de carga</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $load->fecha_carga->format('d/m/Y') }}</p><p class="mt-1 text-xs text-steel-500">Número {{ $load->numero_carga }}</p></div>
        <div class="border-l-4 border-signal bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Costo autorizado por kg</p><p class="mt-2 font-display text-3xl font-extrabold text-ink-950">{{ $money($load->costo_kg, 4) }}</p><p class="mt-1 text-xs text-steel-500">Se aplicará al peso neto total</p></div>
        <div class="industrial-hatch border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Acumulado actual</p><p class="mt-2 font-display text-3xl font-extrabold">{{ $quantity($summary->peso_neto_kg, 3) }} kg</p><p class="mt-1 text-xs text-steel-300">Costo actual {{ $money($load->costo_total) }}</p></div>
    </section>

    <form method="POST" action="{{ route('cargas-proveedor.pesajes.store', $load) }}" data-load-form data-cost-per-kg="{{ $load->costo_kg }}" data-existing-net-weight="{{ $summary->peso_neto_kg }}" class="mt-6 grid gap-6" novalidate>
        @csrf

        <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="weighings-title">
            <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Entrada / Nuevo bloque</p>
                    <h2 id="weighings-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Pesajes de la carga</h2>
                    <p class="mt-1 text-xs text-steel-500">Usa un lote por cada pesada. Puedes volver después para agregar más mientras la carga no tenga pagos.</p>
                </div>
                <button type="button" data-add-weighing class="inline-flex min-h-11 items-center justify-center gap-3 bg-hazard px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">
                    Agregar pesaje <span aria-hidden="true">+</span>
                </button>
            </div>

            @if ($hasUnconfiguredCrateTare)
                <p class="mx-5 mt-5 border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-sm text-ink-700 sm:mx-6">Hay tipos de jaba con tara referencial en cero. Al seleccionarlos, ingresa manualmente la tara unitaria realmente aplicada.</p>
            @endif

            @error('pesajes')
                <p class="mx-5 mt-5 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger sm:mx-6">{{ $message }}</p>
            @enderror

            <div data-weighings class="grid gap-4 p-5 sm:p-6">
                @foreach ($oldWeighings as $index => $weighing)
                    <x-load-weighing-row :index="$index" :weighing="$weighing" :crate-types="$crateTypes" />
                @endforeach

                <div data-weighings-empty @class([
                    'hidden' => count($oldWeighings) > 0,
                    'border border-dashed border-line bg-canvas px-5 py-8 text-center',
                ])>
                    <p class="font-display text-lg font-bold text-ink-950 uppercase">No hay pesajes en este bloque</p>
                    <p class="mt-2 text-sm text-steel-500">Usa “Agregar pesaje” para registrar una nueva pesada.</p>
                </div>
            </div>

            <template data-weighing-template>
                <x-load-weighing-row index="__INDEX__" :weighing="[]" :crate-types="$crateTypes" :show-errors="false" />
            </template>

            <div class="grid gap-px border-t border-line bg-line sm:grid-cols-2 xl:grid-cols-4" aria-live="polite">
                <div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Nuevos pesajes</p><p data-total-weighings class="mt-1 font-display text-2xl font-extrabold text-ink-950">1</p></div>
                <div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Nuevas aves</p><p data-total-birds class="mt-1 font-display text-2xl font-extrabold text-ink-950">0</p></div>
                <div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Peso neto de este envío</p><p data-total-net-weight class="mt-1 font-display text-2xl font-extrabold text-ink-950">0,000 kg</p></div>
                <div class="bg-ink-950 p-4 text-white sm:px-6"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Costo total estimado</p><p data-total-cost class="mt-1 font-display text-2xl font-extrabold">{{ $money($load->costo_total) }}</p></div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('cargas-proveedor.show', $load) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a>
            <button type="submit" data-save-weighings class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Guardar pesajes y calcular</button>
        </div>
    </form>
@endsection
