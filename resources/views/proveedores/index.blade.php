@extends('layouts.app')

@section('title', 'Proveedores')
@section('section', 'Catálogo de proveedores')

@section('content')
    <x-catalog-header
        eyebrow="Directorio / Abastecimiento"
        title="Proveedores"
        description="Centraliza las empresas y personas que abastecen mercadería a la operación."
        :count="$activeCount + $inactiveCount"
        count-label="Registros totales"
        :create-href="route('proveedores.create')"
        create-label="Nuevo proveedor"
    />

    <section class="mt-6 grid grid-cols-2 gap-3 sm:max-w-md" aria-label="Resumen de proveedores">
        <div class="border-l-4 border-signal bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $activeCount }}</span><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Activos</p></div>
        <div class="border-l-4 border-steel-300 bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $inactiveCount }}</span><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Inactivos</p></div>
    </section>

    <x-catalog-toolbar
        :action="route('proveedores.index')"
        :search="$search"
        :status="$status"
        placeholder="Buscar por razón social, documento, teléfono o dirección"
    />

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="suppliers-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Resultado / Abastecimiento</p><h2 id="suppliers-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Listado de proveedores</h2></div>
            <span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $suppliers->total() }} encontrados</span>
        </div>

        @if ($suppliers->isEmpty())
            <x-empty-state title="No encontramos proveedores" description="Ajusta los filtros o registra el primer proveedor para recibir mercadería." :action-href="route('proveedores.create')" action-label="Registrar proveedor">
                <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 20h18M5 20V8l7-4 7 4v12M9 11h6m-6 4h6" /></svg></x-slot:icon>
            </x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[920px] border-collapse text-left" aria-label="Proveedores registrados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase">
                        <tr><th scope="col" class="px-6 py-3 font-semibold">Proveedor</th><th scope="col" class="px-6 py-3 font-semibold">Documento</th><th scope="col" class="px-6 py-3 font-semibold">Contacto</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($suppliers as $supplier)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $supplier->nombre_razon_social }}</p><p class="mt-1 max-w-xs truncate text-xs text-steel-500">{{ $supplier->direccion ?: 'Dirección no registrada' }}</p></td>
                                <td class="px-6 py-4">
                                    @if ($supplier->tipoDocumento && $supplier->nro_documento)
                                        <span class="font-mono text-[9px] font-semibold text-signal">{{ $supplier->tipoDocumento->codigo }}</span><p class="mt-1 font-mono text-xs text-ink-700">{{ $supplier->nro_documento }}</p>
                                    @else
                                        <span class="text-xs text-steel-500">Sin documento</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-ink-700">{{ $supplier->telefono ?: 'Sin teléfono' }}</td>
                                <td class="px-6 py-4"><x-status-badge :active="$supplier->activo" /></td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('proveedores.edit', $supplier) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Editar</a>
                                        @if ($supplier->activo)
                                            <form method="POST" action="{{ route('proveedores.destroy', $supplier) }}" data-confirm="¿Desactivar a este proveedor? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center border border-danger/25 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Desactivar</button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($suppliers as $supplier)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="font-semibold text-ink-950">{{ $supplier->nombre_razon_social }}</p><p class="mt-1 font-mono text-[9px] text-steel-500 uppercase">{{ $supplier->tipoDocumento?->codigo ?: 'Sin doc.' }} {{ $supplier->nro_documento }}</p></div><x-status-badge :active="$supplier->activo" /></div>
                        <dl class="mt-4 grid gap-2 border-t border-line pt-3 text-sm"><div class="flex gap-3"><dt class="w-20 shrink-0 text-steel-500">Teléfono</dt><dd class="text-ink-700">{{ $supplier->telefono ?: 'No registrado' }}</dd></div><div class="flex gap-3"><dt class="w-20 shrink-0 text-steel-500">Dirección</dt><dd class="text-ink-700">{{ $supplier->direccion ?: 'No registrada' }}</dd></div></dl>
                        <div class="mt-4 flex gap-2">
                            <a href="{{ route('proveedores.edit', $supplier) }}" class="inline-flex min-h-10 flex-1 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Editar</a>
                            @if ($supplier->activo)
                                <form method="POST" action="{{ route('proveedores.destroy', $supplier) }}" class="flex-1" data-confirm="¿Desactivar a este proveedor? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="min-h-10 w-full border border-danger/30 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Desactivar</button></form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$suppliers" />
        @endif
    </section>
@endsection
