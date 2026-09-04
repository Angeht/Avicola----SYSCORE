@extends('layouts.app')

@section('title', 'Ajustar cuenta del cliente')
@section('section', 'Ventas y cobranzas')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $selectedType = old('tipo', 'DESCUENTO');
        $currentBalance = (float) $balance->saldo_pendiente;
        $selectedReturnProduct = $products->firstWhere('producto_id', (int) old('producto_id'));
        $previewAdjustment = $selectedType === 'DEVOLUCION'
            ? round((float) old('peso_kg', 0) * (float) ($selectedReturnProduct?->precio_promedio_kg ?? 0), 2)
            : max(0, $currentBalance - (float) old('nuevo_saldo', $currentBalance));
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('ventas.show', $sale) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a {{ $sale->numero_venta }}</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Cliente / Ajuste comercial</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Descuento o devolución</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Elige una sola operación. Los datos de mercadería aparecerán únicamente si registras una devolución.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_380px]">
        <form method="POST" action="{{ route('ventas.ajustes.store', $sale) }}" data-commercial-adjustment-form data-balance="{{ $balance->saldo_pendiente }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">{{ $sale->numero_venta }} / {{ $sale->cliente->nombres_razon_social }}</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">¿Qué ocurrió?</h2></div>
            <div class="grid gap-6 p-5 sm:p-6">
                <x-form-field name="tipo" label="Tipo de ajuste" required>
                    <select id="tipo" name="tipo" data-commercial-adjustment-type class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                        <option value="DESCUENTO" @selected($selectedType === 'DESCUENTO')>Descuento · solo reduce la deuda</option>
                        <option value="DEVOLUCION" @selected($selectedType === 'DEVOLUCION')>Devolución · reduce deuda y devuelve mercadería</option>
                    </select>
                </x-form-field>

                <div data-commercial-discount-fields class="{{ $selectedType === 'DESCUENTO' ? '' : 'hidden' }}">
                    <x-form-field name="nuevo_saldo" label="Nuevo saldo pendiente" hint="Saldo actual: {{ $money($balance->saldo_pendiente) }}" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold text-ink-950">S/</span><input id="nuevo_saldo" name="nuevo_saldo" data-commercial-new-balance value="{{ old('nuevo_saldo') }}" type="number" min="0" max="{{ $balance->saldo_pendiente }}" step="0.01" inputmode="decimal" placeholder="{{ number_format($currentBalance, 2, '.', '') }}" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none placeholder:text-steel-300" required @disabled($selectedType !== 'DESCUENTO')></div>
                    </x-form-field>
                </div>

                <section data-commercial-return-fields class="{{ $selectedType === 'DEVOLUCION' ? '' : 'hidden' }} grid gap-5 border-l-4 border-hazard bg-hazard-soft p-5" aria-label="Mercadería devuelta">
                    <div><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Mercadería recibida</p><p class="mt-1 text-sm text-ink-700">Solo completa la cantidad o el peso que realmente regresó.</p></div>
                    <x-form-field name="producto_id" label="Producto devuelto" required>
                        <select id="producto_id" name="producto_id" data-commercial-return-product class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none focus:border-signal" @disabled($selectedType !== 'DEVOLUCION')>
                            <option value="">Selecciona un producto</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->producto_id }}" data-birds="{{ $product->pollos_disponibles_devolucion }}" data-kilograms="{{ $product->kg_disponibles_devolucion }}" data-unit-price="{{ $product->precio_promedio_kg }}" @selected((string) old('producto_id') === (string) $product->producto_id)>{{ $product->producto }} · hasta {{ number_format((int) $product->pollos_disponibles_devolucion, 0, ',', '.') }} aves / {{ number_format((float) $product->kg_disponibles_devolucion, 3, ',', '.') }} kg · {{ $money($product->precio_promedio_kg) }}/kg</option>
                            @endforeach
                        </select>
                    </x-form-field>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form-field name="cantidad_pollos" label="Aves devueltas"><input id="cantidad_pollos" name="cantidad_pollos" value="{{ old('cantidad_pollos', 0) }}" type="number" min="0" step="1" data-commercial-return-birds class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none focus:border-signal" @disabled($selectedType !== 'DEVOLUCION')></x-form-field>
                        <x-form-field name="peso_kg" label="Peso devuelto" required><div class="flex min-h-12 border border-line bg-white"><input id="peso_kg" name="peso_kg" value="{{ old('peso_kg', '0.000') }}" type="number" min="0.001" step="0.001" data-commercial-return-kilograms class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none" required @disabled($selectedType !== 'DEVOLUCION')><span class="grid w-12 place-items-center border-l border-line bg-canvas font-mono text-xs font-bold">kg</span></div></x-form-field>
                    </div>
                </section>

                <x-form-field name="motivo" label="Motivo" hint="Mínimo 10 caracteres" required><textarea id="motivo" name="motivo" rows="4" minlength="10" maxlength="255" placeholder="Ej. Descuento comercial acordado por diferencia de peso..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" required>{{ old('motivo') }}</textarea></x-form-field>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('ventas.show', $sale) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase">Cancelar</a><button type="submit" class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Registrar ajuste</button></div>
        </form>

        <aside class="grid content-start gap-6"><section class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel"><p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Cuenta</p><h2 class="mt-2 font-display text-2xl font-bold uppercase">Saldo resultante</h2><div class="mt-6 grid gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Saldo actual</p><p class="mt-2 font-display text-2xl font-bold">{{ $money($balance->saldo_pendiente) }}</p></div><div class="grid grid-cols-2 gap-px bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-signal uppercase">Ajuste calculado</p><p data-commercial-preview-amount class="mt-2 font-display text-xl font-bold text-signal">{{ $money($previewAdjustment) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-hazard uppercase">Pendiente</p><p data-commercial-preview-remaining class="mt-2 font-display text-xl font-bold text-hazard">{{ $money(max(0, $currentBalance - $previewAdjustment)) }}</p></div></div></div></section><section class="border-l-4 border-signal bg-signal-soft p-5 text-sm leading-6 text-ink-700">Este ajuste no mueve caja. En una devolución, el sistema calcula el valor usando el peso devuelto y el precio aplicado en la venta.</section></aside>
    </div>
@endsection
