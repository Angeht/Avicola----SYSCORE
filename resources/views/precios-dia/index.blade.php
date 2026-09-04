@extends('layouts.app')

@section('title', 'Precio del día')
@section('section', 'Precio del día')

@section('content')
    @php
        $unitPrice = static function (float|int|string|null $value): string {
            $formatted = number_format((float) ($value ?? 0), 4, ',', '.');

            return 'S/ '.(preg_replace('/0{1,2}$/', '', $formatted) ?? $formatted);
        };
        $selectedDate = Illuminate\Support\Carbon::parse($date);
    @endphp

    <x-catalog-header
        eyebrow="Operación / Tarifario"
        title="Precio del día"
        description="Define el valor por kilogramo que utilizarán las ventas y conserva cada modificación como una versión auditable."
        :count="$prices->total()"
        count-label="Precios en la fecha"
        :create-href="route('precios-dia.create')"
        create-label="Registrar precio"
    />

    <section class="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Cobertura de precios">
        <div class="border-l-4 border-signal bg-paper px-4 py-4 shadow-sm">
            <span class="font-display text-3xl font-extrabold text-ink-950">{{ $pricedProductCount }}</span>
            <p class="font-mono text-[8px] tracking-wider text-signal uppercase">Productos con precio</p>
        </div>
        <div class="border-l-4 {{ $missingProductCount > 0 ? 'border-danger' : 'border-steel-300' }} bg-paper px-4 py-4 shadow-sm">
            <span class="font-display text-3xl font-extrabold text-ink-950">{{ $missingProductCount }}</span>
            <p class="font-mono text-[8px] tracking-wider {{ $missingProductCount > 0 ? 'text-danger' : 'text-steel-500' }} uppercase">Productos pendientes</p>
        </div>
        <div class="border-l-4 border-hazard bg-paper px-4 py-4 shadow-sm">
            <span class="font-display text-2xl font-extrabold text-ink-950">{{ $selectedDate->format('d/m') }}</span>
            <p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $selectedDate->translatedFormat('l · Y') }}</p>
        </div>
    </section>

    <form method="GET" action="{{ route('precios-dia.index') }}" class="reveal-up reveal-up-delay-1 mt-5 grid gap-3 border border-line bg-paper p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_auto]" role="search">
        <div>
            <label for="buscar" class="font-mono text-[9px] font-semibold tracking-[0.15em] text-ink-700 uppercase">Buscar producto</label>
            <div class="relative mt-2">
                <svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                <input id="buscar" name="buscar" value="{{ $search }}" type="search" placeholder="Nombre del producto" class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">
            </div>
        </div>
        <div>
            <label for="fecha" class="font-mono text-[9px] font-semibold tracking-[0.15em] text-ink-700 uppercase">Fecha operativa</label>
            <input id="fecha" name="fecha" value="{{ $date }}" type="date" class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15">
        </div>
        <button type="submit" class="min-h-12 self-end bg-ink-950 px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">Consultar</button>
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="daily-price-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div>
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Tarifario / {{ $selectedDate->format('d.m.Y') }}</p>
                <h2 id="daily-price-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Precios registrados</h2>
            </div>
            <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $prices->total() }} encontrados</span>
        </div>

        @if ($prices->isEmpty())
            <x-empty-state title="Sin precios para esta fecha" description="Registra el precio de los productos antes de iniciar las ventas." :action-href="route('precios-dia.create')" action-label="Registrar precio">
                <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 12 12 20 4 12V4h8l8 8Z" /><circle cx="8.5" cy="8.5" r="1.2" /></svg></x-slot:icon>
            </x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[900px] border-collapse text-left" aria-label="Precios del día registrados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase">
                        <tr><th scope="col" class="px-6 py-3 font-semibold">Producto</th><th scope="col" class="px-6 py-3 font-semibold">Precio vigente</th><th scope="col" class="px-6 py-3 font-semibold">Última actualización</th><th scope="col" class="px-6 py-3 font-semibold">Versiones</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($prices as $price)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $price->producto->nombre }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $price->producto->activo ? 'Producto activo' : 'Producto inactivo' }}</p></td>
                                <td class="px-6 py-4"><span class="font-display text-2xl font-extrabold text-ink-950">{{ $price->versionVigente ? $unitPrice($price->versionVigente->precio_kg) : 'Sin versión' }}</span><span class="ml-1 text-xs text-steel-500">/ kg</span></td>
                                <td class="px-6 py-4"><p class="text-sm text-ink-700">{{ $price->versionVigente?->vigente_desde?->format('H:i:s') ?? '—' }}</p><p class="mt-1 text-xs text-steel-500">{{ $price->versionVigente?->registradoPor?->nombreCompleto() ?? 'Sin responsable' }}</p></td>
                                <td class="px-6 py-4"><span class="inline-flex border border-line bg-canvas px-2.5 py-1 font-mono text-[9px] font-semibold text-ink-700 uppercase">{{ $price->versiones_count }} {{ $price->versiones_count === 1 ? 'versión' : 'versiones' }}</span></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><a href="{{ route('precios-dia.show', $price) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Historial</a>@if ($date === today()->toDateString())<a href="{{ route('precios-dia.create', ['producto' => $price->producto_id]) }}" class="inline-flex min-h-9 items-center border border-hazard/50 bg-hazard-soft px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase transition hover:bg-hazard">Cambiar precio</a>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($prices as $price)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-ink-950">{{ $price->producto->nombre }}</p><p class="mt-1 font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $price->versiones_count }} {{ $price->versiones_count === 1 ? 'versión' : 'versiones' }}</p></div><span class="font-display text-2xl font-extrabold text-ink-950">{{ $price->versionVigente ? $unitPrice($price->versionVigente->precio_kg) : '—' }}</span></div>
                        <p class="mt-3 text-xs text-steel-500">Actualizado {{ $price->versionVigente?->vigente_desde?->format('H:i:s') ?? 'sin hora' }} por {{ $price->versionVigente?->registradoPor?->nombreCompleto() ?? 'un usuario no disponible' }}.</p>
                        <div class="mt-4 flex gap-2"><a href="{{ route('precios-dia.show', $price) }}" class="inline-flex min-h-10 flex-1 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Historial</a>@if ($date === today()->toDateString())<a href="{{ route('precios-dia.create', ['producto' => $price->producto_id]) }}" class="inline-flex min-h-10 flex-1 items-center justify-center bg-hazard px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Cambiar</a>@endif</div>
                    </article>
                @endforeach
            </div>

            <x-catalog-pagination :paginator="$prices" />
        @endif
    </section>
@endsection
