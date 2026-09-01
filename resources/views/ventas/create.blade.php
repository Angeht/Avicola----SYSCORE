@extends('layouts.app')

@section('title', $sale ? 'Editar venta' : 'Registrar venta')
@section('section', 'Ventas y pesajes')

@section('content')
    @php
        $isEditing = $sale !== null;
        $oldDetails = old('detalles', $initialDetails ?? [[
            'precio_version_id' => null,
            'precio_aplicado_kg' => '',
            'motivo_ajuste_precio' => null,
            'pesajes' => [[
                'tipo_jaba_id' => null,
                'cantidad_jabas' => 0,
                'cantidad_pollos' => '',
                'peso_bruto_kg' => '',
                'tara_unitaria_aplicada_kg' => '0.000',
                'observacion' => null,
            ]],
        ]]);
        $canSubmit = $priceOptions->isNotEmpty();
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl"><a href="{{ $isEditing ? route('ventas.show', $sale) : route('ventas.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← {{ $isEditing ? 'Volver al detalle' : 'Volver a ventas' }}</a><p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Comercial / {{ $isEditing ? $sale->numero_venta : 'Nueva salida' }}</p><h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">{{ $isEditing ? 'Editar venta' : 'Registrar venta' }}</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $isEditing ? 'Corrige el cliente, los productos o los pesajes. El stock y el saldo por cobrar se recalcularán al confirmar.' : 'Selecciona el producto, registra cada pesada y confirma. El importe se calcula con el peso neto y el precio vigente.' }}</p></div>
        @if ($isEditing)<div class="flex min-h-12 items-center gap-3 border border-hazard/40 bg-hazard-soft px-4"><span class="size-2 bg-hazard"></span><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Edición controlada</p><p class="text-sm font-semibold text-ink-950">La venta conservará su número y trazabilidad</p></div></div>@else<div class="flex min-h-12 items-center gap-3 border px-4 {{ $cashSession ? 'border-signal/30 bg-signal-soft' : 'border-hazard/40 bg-hazard-soft' }}"><span class="size-2 {{ $cashSession ? 'bg-signal' : 'bg-hazard' }}"></span><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Sesión operativa</p><p class="text-sm font-semibold text-ink-950">{{ $cashSession ? 'Caja #'.$cashSession->id.' vinculada' : 'Venta sin caja abierta' }}</p></div></div>@endif
    </header>

    <form method="POST" action="{{ $isEditing ? route('ventas.update', $sale) : route('ventas.store') }}" data-sale-form data-can-edit-price="{{ $canEditPrice ? 'true' : 'false' }}" class="mt-6 grid gap-6" novalidate>
        @csrf
        @if ($isEditing)@method('PUT')@endif

        <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="sale-customer-title">
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Paso 01 / Encabezado</p><h2 id="sale-customer-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Cliente y referencia</h2></div>
            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                <x-form-field name="cliente_id" label="Cliente" hint="Opcional; necesario para controlar crédito"><select id="cliente_id" name="cliente_id" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none focus:border-signal" autofocus><option value="">Venta sin cliente identificado</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected((string) old('cliente_id', $sale?->cliente_id) === (string) $client->id)>{{ $client->nombres_razon_social }}{{ $client->nro_documento ? ' · '.$client->nro_documento : '' }}</option>@endforeach</select></x-form-field>
                <x-form-field name="observacion" label="Observación" hint="Opcional"><input id="observacion" name="observacion" value="{{ old('observacion', $sale?->observacion) }}" type="text" maxlength="255" placeholder="Pedido, placa, destino o indicación comercial..." class="min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none placeholder:text-steel-300 focus:border-signal"></x-form-field>
            </div>
        </section>

        <section class="reveal-up reveal-up-delay-2 border border-line bg-paper shadow-panel" aria-labelledby="sale-details-title">
            <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Paso 02 / Despacho</p><h2 id="sale-details-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Productos y pesajes</h2><p class="mt-1 text-xs text-steel-500">Se muestran los productos con precio vigente; la existencia se valida antes de confirmar.</p></div><button type="button" data-add-sale-detail class="inline-flex min-h-11 items-center justify-center gap-3 bg-hazard px-5 font-display text-sm font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">Agregar producto <span aria-hidden="true">+</span></button></div>

            @error('detalles')<p class="mx-5 mt-5 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger sm:mx-6">{{ $message }}</p>@enderror
            <div data-sale-details class="grid gap-5 p-5 sm:p-6">@foreach ($oldDetails as $detailIndex => $detail)<x-sale-detail :detail-index="$detailIndex" :detail="$detail" :price-options="$priceOptions" :crate-types="$crateTypes" :can-edit-price="$canEditPrice" />@endforeach</div>
            <template data-sale-detail-template><x-sale-detail detail-index="__DETAIL__" :detail="[]" :price-options="$priceOptions" :crate-types="$crateTypes" :can-edit-price="$canEditPrice" :show-errors="false" /></template>

            <div class="grid gap-px border-t border-line bg-line sm:grid-cols-2 xl:grid-cols-4" aria-live="polite"><div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Productos</p><p data-sale-total-details class="mt-1 font-display text-2xl font-extrabold text-ink-950">1</p></div><div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Aves vendidas</p><p data-sale-total-birds class="mt-1 font-display text-2xl font-extrabold text-ink-950">0</p></div><div class="bg-canvas p-4 sm:px-6"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Peso neto</p><p data-sale-total-kilograms class="mt-1 font-display text-2xl font-extrabold text-ink-950">0,000 kg</p></div><div class="industrial-hatch bg-ink-950 p-4 text-white sm:px-6"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Total estimado</p><p data-sale-grand-total class="mt-1 font-display text-3xl font-extrabold">S/ 0,00</p></div></div>
        </section>

        @if ($isEditing)
            <section class="border-l-4 border-hazard bg-hazard-soft p-5 shadow-sm sm:p-6"><x-form-field name="motivo_edicion" label="Motivo de la edición" hint="Obligatorio, entre 10 y 255 caracteres" required><input id="motivo_edicion" name="motivo_edicion" value="{{ old('motivo_edicion') }}" type="text" minlength="10" maxlength="255" placeholder="Ej. Corrección del peso registrado por caja..." class="min-h-12 w-full border border-hazard/50 bg-white px-4 text-sm text-ink-950 outline-none placeholder:text-steel-300 focus:border-ink-950" required></x-form-field><p class="mt-3 text-xs leading-5 text-ink-700">La edición quedará registrada con tu usuario, fecha, motivo y los cambios realizados.</p></section>
        @endif

        @unless ($canSubmit)
            <div class="flex flex-col gap-4 border-l-4 border-danger bg-danger-soft px-4 py-4 text-sm font-medium text-danger sm:flex-row sm:items-center sm:justify-between"><p>No hay productos con precio vigente para hoy.</p>@if ($authenticatedUser?->tienePermiso('PRECIO_DIA_GESTIONAR'))<a href="{{ route('precios-dia.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center bg-danger px-4 font-display text-xs font-bold tracking-wider text-white uppercase">Configurar precio</a>@endif</div>
        @endunless

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end"><a href="{{ $isEditing ? route('ventas.show', $sale) : route('ventas.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a><button type="submit" @disabled(! $canSubmit) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">{{ $isEditing ? 'Guardar cambios' : 'Confirmar venta' }}</button></div>
    </form>
@endsection
