@extends('layouts.app')

@section('title', 'Productos')
@section('section', 'Catálogo de productos')

@section('content')
    <x-catalog-header
        eyebrow="Catálogo / Mercadería"
        title="Productos"
        description="Define los productos comercializables y la unidad usada para pesajes, precios y saldos."
        :count="$activeCount + $inactiveCount"
        count-label="Registros totales"
        :create-href="route('productos.create')"
        create-label="Nuevo producto"
    />

    <section class="mt-6 grid grid-cols-2 gap-3 sm:max-w-md" aria-label="Resumen de productos">
        <div class="border-l-4 border-signal bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $activeCount }}</span><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Activos</p></div>
        <div class="border-l-4 border-steel-300 bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $inactiveCount }}</span><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Inactivos</p></div>
    </section>

    <x-catalog-toolbar :action="route('productos.index')" :search="$search" :status="$status" placeholder="Buscar por nombre, descripción o unidad de medida" />

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="products-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Resultado / Mercadería</p><h2 id="products-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Listado de productos</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $products->total() }} encontrados</span></div>

        @if ($products->isEmpty())
            <x-empty-state title="No encontramos productos" description="Ajusta los filtros o crea el primer producto comercializable." :action-href="route('productos.create')" action-label="Registrar producto"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[980px] border-collapse text-left" aria-label="Productos registrados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Producto</th><th scope="col" class="px-6 py-3 font-semibold">Unidad</th><th scope="col" class="px-6 py-3 font-semibold">Venta</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($products as $product)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $product->nombre }}</p><p class="mt-1 max-w-xl truncate text-xs text-steel-500">{{ $product->descripcion ?: 'Sin descripción' }}</p></td>
                                <td class="px-6 py-4"><span class="inline-flex border border-line bg-canvas px-2.5 py-1 font-mono text-[9px] font-semibold text-ink-700 uppercase">{{ $product->unidadMedida->codigo }} · {{ $product->unidadMedida->simbolo }}</span></td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase {{ $product->seVendeSoloPorPeso() ? 'border-signal/30 bg-signal-soft text-signal' : 'border-hazard/40 bg-hazard-soft text-ink-700' }}">{{ $product->seVendeSoloPorPeso() ? 'Solo kg' : 'Pesaje vivo' }}</span></td>
                                <td class="px-6 py-4"><x-status-badge :active="$product->activo" /></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><a href="{{ route('productos.edit', $product) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Editar</a>@if ($product->activo)<form method="POST" action="{{ route('productos.destroy', $product) }}" data-confirm="¿Desactivar este producto? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center border border-danger/25 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Desactivar</button></form>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($products as $product)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="font-semibold text-ink-950">{{ $product->nombre }}</p><p class="mt-1 text-xs leading-5 text-steel-500">{{ $product->descripcion ?: 'Sin descripción' }}</p></div><x-status-badge :active="$product->activo" /></div>
                        <p class="mt-4 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Unidad · {{ $product->unidadMedida->nombre }} ({{ $product->unidadMedida->simbolo }})</p>
                        <p class="mt-2 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Venta · {{ $product->seVendeSoloPorPeso() ? 'Solo kilogramos' : 'Aves, jabas y tara' }}</p>
                        <div class="mt-4 flex gap-2"><a href="{{ route('productos.edit', $product) }}" class="inline-flex min-h-10 flex-1 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Editar</a>@if ($product->activo)<form method="POST" action="{{ route('productos.destroy', $product) }}" class="flex-1" data-confirm="¿Desactivar este producto? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="min-h-10 w-full border border-danger/30 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Desactivar</button></form>@endif</div>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$products" />
        @endif
    </section>
@endsection
