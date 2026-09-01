@extends('layouts.app')

@section('title', 'Registrar ajuste de mercadería')
@section('section', 'Mercadería')

@section('content')
    @php
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
        $selectedProductId = old('producto_id', $preselectedProductId);
        $selectedProduct = $products->firstWhere('id', (int) $selectedProductId);
        $selectedTypeId = old('tipo_ajuste_id');
        $selectedType = $adjustmentTypes->firstWhere('id', (int) $selectedTypeId);
        $canRegister = $products->isNotEmpty() && $adjustmentTypes->isNotEmpty();
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('mercaderia.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a mercadería</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Inventario / Corrección controlada</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar ajuste</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Registra una entrada o salida extraordinaria. El movimiento afectará inmediatamente el saldo del producto y quedará asociado a tu usuario.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_380px]">
        <form method="POST" action="{{ route('mercaderia.store') }}" data-adjustment-form class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf

            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Movimiento / Datos</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Detalle del ajuste</h2></div>

            <div class="grid gap-6 p-5 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-2">
                    <x-form-field name="producto_id" label="Producto" hint="Solo productos activos" required>
                        <select id="producto_id" name="producto_id" data-adjustment-product class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                            <option value="">Selecciona un producto</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-birds="{{ $product->pollos_disponibles }}" data-kilograms="{{ $product->kg_disponibles }}" data-review="{{ $product->requiere_revision ? 'true' : 'false' }}" @selected((string) $selectedProductId === (string) $product->id)>{{ $product->nombre }} · {{ $quantity($product->pollos_disponibles) }} aves · {{ $quantity($product->kg_disponibles, 3) }} kg</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field name="tipo_ajuste_id" label="Tipo de ajuste" hint="Define si suma o descuenta" required>
                        <select id="tipo_ajuste_id" name="tipo_ajuste_id" data-adjustment-type class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                            <option value="">Selecciona el tipo</option>
                            @foreach ($adjustmentTypes as $type)
                                <option value="{{ $type->id }}" data-code="{{ $type->codigo }}" data-nature="{{ $type->naturaleza }}" @selected((string) $selectedTypeId === (string) $type->id)>{{ $type->nombre }} · {{ $type->naturaleza === 'ENTRADA' ? 'suma stock' : 'descuenta stock' }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form-field name="cantidad_pollos" label="Cantidad de aves" hint="Usa 0 si solo ajustas peso" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><input id="cantidad_pollos" name="cantidad_pollos" data-adjustment-birds value="{{ old('cantidad_pollos', 0) }}" type="number" min="0" max="2000000000" step="1" inputmode="numeric" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required><span class="grid w-16 shrink-0 place-items-center border-l border-line bg-canvas font-mono text-[9px] text-steel-500 uppercase">Aves</span></div>
                    </x-form-field>

                    <x-form-field name="peso_kg" label="Peso" hint="Máximo 3 decimales" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><input id="peso_kg" name="peso_kg" data-adjustment-kilograms value="{{ old('peso_kg', '0.000') }}" type="number" min="0" max="999999999.999" step="0.001" inputmode="decimal" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required><span class="grid w-16 shrink-0 place-items-center border-l border-line bg-canvas font-mono text-[9px] text-steel-500 uppercase">kg</span></div>
                    </x-form-field>
                </div>

                <x-form-field name="motivo" label="Motivo del ajuste" hint="Entre 10 y 255 caracteres" required>
                    <textarea id="motivo" name="motivo" rows="4" minlength="10" maxlength="255" placeholder="Describe la causa y el contexto del movimiento..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" required>{{ old('motivo') }}</textarea>
                </x-form-field>

                <div data-adjustment-initial-warning class="hidden border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-sm leading-6 text-ink-700" role="status">El saldo inicial solo se admite antes del primer movimiento registrado para el producto.</div>

                @unless ($canRegister)
                    <div class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">No hay productos o tipos de ajuste activos disponibles para registrar el movimiento.</div>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('mercaderia.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a><button type="submit" @disabled(! $canRegister) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar ajuste</button></div>
        </form>

        <aside class="grid content-start gap-6">
            <section data-adjustment-preview-panel class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="adjustment-preview-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Existencia</p><h2 id="adjustment-preview-title" class="mt-2 font-display text-2xl font-bold uppercase">Saldo resultante</h2>
                <p data-adjustment-preview-product class="mt-3 text-sm text-steel-300">Selecciona un producto y el tipo de ajuste.</p>
                <div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Saldo actual</p><p data-adjustment-current-birds class="mt-2 font-display text-2xl font-bold">{{ $quantity($selectedProduct?->pollos_disponibles) }}</p><p data-adjustment-current-kilograms class="mt-1 text-xs text-steel-300">{{ $quantity($selectedProduct?->kg_disponibles, 3) }} kg</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Después del ajuste</p><p data-adjustment-result-birds class="mt-2 font-display text-2xl font-bold text-hazard">{{ $quantity($selectedProduct?->pollos_disponibles) }}</p><p data-adjustment-result-kilograms class="mt-1 text-xs text-steel-300">{{ $quantity($selectedProduct?->kg_disponibles, 3) }} kg</p></div></div>
                <div data-adjustment-preview-message class="mt-px border border-white/10 bg-white/5 p-4 text-xs leading-5 text-steel-300" role="status" aria-live="polite">Completa las cantidades para revisar el impacto.</div>
            </section>

            <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Control de salida</p><p class="mt-2 text-sm leading-6 text-ink-700">Las mermas, consumos, descartes y ajustes negativos no pueden superar las aves ni el peso disponible.</p></section>

            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Trazabilidad automática</p><p class="mt-2 text-sm leading-6 text-steel-500">El número, la fecha y el usuario se generan en el servidor. No necesitas ingresarlos manualmente.</p></section>
        </aside>
    </div>
@endsection
