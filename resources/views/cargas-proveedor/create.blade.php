@extends('layouts.app')

@section('title', 'Nueva carga')
@section('section', 'Cargas de proveedor')

@section('content')
    @php($canRegister = $providers->isNotEmpty() && $products->isNotEmpty())

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('cargas-proveedor.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a cargas</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Abastecimiento / Nueva entrada</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar carga</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Primero registra los datos comerciales y el costo por kilogramo. Al guardar se asignará el número interno y se habilitará el registro de pesajes.</p>
    </header>

    <form method="POST" action="{{ route('cargas-proveedor.store') }}" class="mt-6 grid gap-6" novalidate>
        @csrf

        <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="load-data-title">
            <div class="border-b border-line px-5 py-5 sm:px-6">
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Paso 01 / Autorización</p>
                <h2 id="load-data-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Datos de la carga</h2>
            </div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                <x-form-field name="proveedor_id" label="Proveedor" required class="sm:col-span-2">
                    <select id="proveedor_id" name="proveedor_id" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                        <option value="">Selecciona un proveedor</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}" @selected((string) old('proveedor_id') === (string) $provider->id)>{{ $provider->nombre_razon_social }}{{ $provider->nro_documento ? ' · '.$provider->nro_documento : '' }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field name="producto_id" label="Producto recibido" required>
                    <select id="producto_id" name="producto_id" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                        <option value="">Selecciona un producto</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('producto_id') === (string) $product->id)>{{ $product->nombre }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field name="fecha_carga" label="Fecha de recepción" required>
                    <input id="fecha_carga" name="fecha_carga" value="{{ old('fecha_carga', today()->toDateString()) }}" type="date" max="{{ today()->toDateString() }}" class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                </x-form-field>

                <x-form-field name="costo_kg" label="Costo por kg" hint="Usa 2 decimales; admite hasta 4 si los necesitas" required>
                    <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15">
                        <span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold text-ink-950">S/</span>
                        <input id="costo_kg" name="costo_kg" value="{{ old('costo_kg') }}" type="number" min="0.0001" max="99999999.9999" step="0.0001" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none placeholder:text-steel-300" required>
                    </div>
                </x-form-field>

                <x-form-field name="observacion" label="Observación general" hint="Opcional" class="sm:col-span-2 xl:col-span-3">
                    <input id="observacion" name="observacion" value="{{ old('observacion') }}" type="text" maxlength="255" placeholder="Guía, conductor, placa o incidencia de recepción..." class="min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">
                </x-form-field>
            </div>
        </section>

        <section class="reveal-up reveal-up-delay-2 grid gap-px border border-line bg-line shadow-panel sm:grid-cols-[auto_1fr]" aria-labelledby="next-step-title">
            <div class="grid min-h-32 place-items-center bg-ink-950 px-8">
                <span class="grid size-16 place-items-center border border-hazard font-display text-3xl font-extrabold text-hazard">02</span>
            </div>
            <div class="bg-paper p-5 sm:p-6">
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Siguiente / Balanza</p>
                <h2 id="next-step-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Pesajes después de autorizar</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-steel-500">Una vez creada la carga podrás registrar una o varias pesadas. El sistema acumulará el peso neto y calculará automáticamente: <strong class="text-ink-950">peso neto total × costo por kg</strong>.</p>
            </div>
        </section>

        @unless ($canRegister)
            <div class="flex flex-col gap-4 border-l-4 border-danger bg-danger-soft px-4 py-4 text-sm font-medium text-danger sm:flex-row sm:items-center sm:justify-between">
                <p>Necesitas al menos un proveedor y un producto activos antes de registrar una carga.</p>
                @if ($providers->isEmpty())
                    <a href="{{ route('proveedores.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center bg-danger px-4 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-950">Crear proveedor</a>
                @endif
            </div>
        @endunless

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('cargas-proveedor.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a>
            <button type="submit" @disabled(! $canRegister) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Crear carga y continuar</button>
        </div>
    </form>
@endsection
