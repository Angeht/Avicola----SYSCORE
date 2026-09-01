@extends('layouts.app')

@section('title', 'Editar proveedor')
@section('section', 'Catálogo de proveedores')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="supplier-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Proveedores / Edición</p>
            <h1 id="supplier-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar proveedor</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Actualiza la ficha de <strong class="text-ink-950">{{ $supplier->nombre_razon_social }}</strong>.</p>
            <form method="POST" action="{{ route('proveedores.update', $supplier) }}" class="mt-8">@csrf @method('PUT') @include('proveedores._form', ['supplier' => $supplier, 'submitLabel' => 'Actualizar proveedor'])</form>
        </section>
        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="supplier-status-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Registro #{{ $supplier->id }}</p><h2 id="supplier-status-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Control de estado</h2><p class="mt-3 text-sm leading-6 text-steel-300">Desactivar conserva cargas y pagos históricos. Puedes reactivarlo en cualquier momento.</p>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5"><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado actual</dt><dd class="mt-2"><x-status-badge :active="$supplier->activo" /></dd></div><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Última actualización</dt><dd class="mt-1 text-sm text-white">{{ $supplier->updated_at->translatedFormat('d M Y · H:i') }}</dd></div></dl>
        </aside>
    </div>
@endsection
