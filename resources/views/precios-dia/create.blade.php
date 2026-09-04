@extends('layouts.app')

@section('title', 'Registrar precio')
@section('section', 'Precio del día')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="daily-price-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Tarifario / Nueva versión</p>
            <h1 id="daily-price-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar precio</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Configura el precio por kilogramo para hoy. Si el producto ya tiene un valor, se guardará una nueva versión sin alterar el historial.</p>

            <form method="POST" action="{{ route('precios-dia.store') }}" class="mt-8">
                @csrf
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-form-field name="producto_id" label="Producto" hint="Solo productos activos" required class="sm:col-span-2">
                        <select id="producto_id" name="producto_id" class="min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" aria-describedby="@error('producto_id') producto_id-error @enderror">
                            <option value="">Selecciona un producto</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) old('producto_id', $preselectedProductId) === (string) $product->id)>{{ $product->nombre }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field name="fecha" label="Fecha operativa" hint="Solo la jornada actual" required>
                        <input id="fecha" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" type="date" readonly class="min-h-12 w-full border border-line bg-canvas px-4 text-sm text-ink-700 outline-none" aria-describedby="@error('fecha') fecha-error @enderror">
                    </x-form-field>

                    <x-form-field name="precio_kg" label="Precio por kilogramo" hint="Usa 2 decimales; admite hasta 4 si los necesitas" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15">
                            <span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold text-ink-950">S/</span>
                            <input id="precio_kg" name="precio_kg" value="{{ old('precio_kg') }}" type="number" min="0.0001" max="99999999.9999" step="0.0001" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 px-4 text-lg font-semibold text-ink-950 outline-none placeholder:text-steel-300" aria-describedby="@error('precio_kg') precio_kg-error @enderror" autofocus>
                        </div>
                    </x-form-field>

                    <x-form-field name="motivo_cambio" label="Motivo del cambio" hint="Obligatorio si ya existía un precio" class="sm:col-span-2">
                        <textarea id="motivo_cambio" name="motivo_cambio" rows="3" maxlength="255" placeholder="Ej. Ajuste por variación del mercado" class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" aria-describedby="@error('motivo_cambio') motivo_cambio-error @enderror">{{ old('motivo_cambio') }}</textarea>
                    </x-form-field>
                </div>

                @if ($products->isEmpty())
                    <p class="mt-6 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">No hay productos activos. Activa o registra un producto antes de definir su precio.</p>
                @endif

                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('precios-dia.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a>
                    <button type="submit" @disabled($products->isEmpty()) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Guardar versión</button>
                </div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="daily-price-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 12 12 20 4 12V4h8l8 8Z" /><circle cx="8.5" cy="8.5" r="1.2" /></svg></span>
            <h2 id="daily-price-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Trazabilidad total</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Cada modificación genera una versión nueva con hora, responsable y motivo. Las ventas conservarán el precio que les correspondía.</p>
            <div class="mt-7 border border-white/10 bg-white/5 p-5">
                <p class="font-mono text-[9px] tracking-[0.18em] text-hazard uppercase">Jornada activa</p>
                <p class="mt-2 font-display text-3xl font-extrabold">{{ today()->format('d.m.Y') }}</p>
                <p class="mt-1 text-xs text-steel-300">{{ today()->translatedFormat('l') }}</p>
            </div>
            <ul class="mt-6 grid gap-3 text-sm text-white">
                @foreach (['Confirma el producto correcto', 'Usa dos decimales normalmente', 'Justifica cualquier modificación'] as $tip)
                    <li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
