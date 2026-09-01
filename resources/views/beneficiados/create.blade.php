@extends('layouts.app')

@section('title', 'Registrar beneficiado')
@section('section', 'Mercadería · Beneficiado')

@section('content')
    @php
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
        $selectedLoadId = old('carga_proveedor_id', $preselectedLoadId);
        $canRegister = $loads->isNotEmpty() && $destinationProducts->isNotEmpty();
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Transformación / Nueva operación</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar beneficiado</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Selecciona la carga viva, registra lo enviado al proceso y los kilogramos obtenidos. El sistema actualizará ambos productos en una sola operación.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_390px]">
        <form method="POST" action="{{ route('beneficiados.store') }}" data-beneficiary-form class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf

            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Carga / Conversión</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Datos del proceso</h2></div>

            <div class="grid gap-6 p-5 sm:p-6">
                <x-form-field name="carga_proveedor_id" label="Carga de origen" hint="Solo cargas vivas con saldo disponible" required>
                    <select id="carga_proveedor_id" name="carga_proveedor_id" data-beneficiary-load class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                        <option value="">Selecciona una carga</option>
                        @foreach ($loads as $load)
                            <option value="{{ $load->id }}" data-load-number="{{ $load->numero_carga }}" data-product="{{ $load->producto }}" data-provider="{{ $load->proveedor }}" data-available-birds="{{ $load->pollos_disponibles }}" data-available-kilograms="{{ $load->kg_disponibles }}" @selected((string) $selectedLoadId === (string) $load->id)>{{ $load->numero_carga }} · {{ $load->producto }} · {{ $quantity($load->pollos_disponibles) }} aves / {{ $quantity($load->kg_disponibles, 3) }} kg</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field name="producto_destino_id" label="Producto resultante" hint="Ingresará al stock disponible para venta" required>
                    <select id="producto_destino_id" name="producto_destino_id" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                        <option value="">Selecciona el producto beneficiado</option>
                        @foreach ($destinationProducts as $product)
                            <option value="{{ $product->id }}" @selected((string) old('producto_destino_id') === (string) $product->id)>{{ $product->nombre }} · venta por kg</option>
                        @endforeach
                    </select>
                </x-form-field>

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-form-field name="cantidad_pollos" label="Aves procesadas" hint="Descuenta del vivo" required><div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><input id="cantidad_pollos" name="cantidad_pollos" data-beneficiary-birds value="{{ old('cantidad_pollos') }}" type="number" min="1" max="2000000000" step="1" inputmode="numeric" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required><span class="grid w-14 shrink-0 place-items-center border-l border-line bg-canvas font-mono text-[9px] text-steel-500 uppercase">Aves</span></div></x-form-field>
                    <x-form-field name="peso_origen_kg" label="Peso vivo" hint="Kg retirados del origen" required><div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><input id="peso_origen_kg" name="peso_origen_kg" data-beneficiary-source-weight value="{{ old('peso_origen_kg') }}" type="number" min="0.001" max="999999999.999" step="0.001" inputmode="decimal" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required><span class="grid w-14 shrink-0 place-items-center border-l border-line bg-canvas font-mono text-[9px] text-steel-500 uppercase">kg</span></div></x-form-field>
                    <x-form-field name="peso_resultante_kg" label="Peso beneficiado" hint="Kg que ingresan a venta" required><div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><input id="peso_resultante_kg" name="peso_resultante_kg" data-beneficiary-result-weight value="{{ old('peso_resultante_kg') }}" type="number" min="0.001" max="999999999.999" step="0.001" inputmode="decimal" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required><span class="grid w-14 shrink-0 place-items-center border-l border-line bg-canvas font-mono text-[9px] text-steel-500 uppercase">kg</span></div></x-form-field>
                </div>

                <x-form-field name="observacion" label="Observación" hint="Opcional, máximo 255 caracteres"><textarea id="observacion" name="observacion" rows="3" maxlength="255" placeholder="Turno, responsable del proceso o detalle relevante..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">{{ old('observacion') }}</textarea></x-form-field>

                @unless ($canRegister)
                    <div class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">Necesitas al menos una carga viva con saldo y un producto activo configurado como “Solo peso”.</div>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('beneficiados.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a><button type="submit" @disabled(! $canRegister) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar beneficiado</button></div>
        </form>

        <aside class="grid content-start gap-6">
            <section class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="beneficiary-preview-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Rendimiento</p><h2 id="beneficiary-preview-title" class="mt-2 font-display text-2xl font-bold uppercase">Resultado estimado</h2>
                <p data-beneficiary-load-meta class="mt-3 text-sm leading-6 text-steel-300">Selecciona una carga para consultar su saldo procesable.</p>
                <div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Disponible carga</p><p data-beneficiary-available-birds class="mt-2 font-display text-2xl font-bold">0 aves</p><p data-beneficiary-available-weight class="mt-1 text-xs text-steel-300">0,000 kg</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Producto obtenido</p><p data-beneficiary-result-output class="mt-2 font-display text-2xl font-bold text-signal">0,000 kg</p><p class="mt-1 text-xs text-steel-300">ingresa al stock de venta</p></div></div>
                <div class="mt-px grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-white/5 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Merma</p><p data-beneficiary-loss-output class="mt-2 font-display text-2xl font-bold text-hazard">0,000 kg</p></div><div class="bg-white/5 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Rendimiento</p><p data-beneficiary-yield-output class="mt-2 font-display text-2xl font-bold">0,00%</p></div></div>
                <p data-beneficiary-message class="mt-4 text-xs leading-5 text-steel-300" role="status" aria-live="polite">Completa los pesos para calcular merma y rendimiento.</p>
            </section>

            <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Movimiento automático</p><p class="mt-2 text-sm leading-6 text-ink-700">Las aves y el peso vivo saldrán del producto de la carga. Solo los kilogramos obtenidos ingresarán al producto beneficiado.</p></section>
        </aside>
    </div>
@endsection
