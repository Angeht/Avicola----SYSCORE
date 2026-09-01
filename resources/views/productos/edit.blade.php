@extends('layouts.app')

@section('title', 'Editar producto')
@section('section', 'Catálogo de productos')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="product-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Productos / Edición</p><h1 id="product-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar producto</h1><p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Actualiza la configuración de <strong class="text-ink-950">{{ $product->nombre }}</strong>.</p>
            <form method="POST" action="{{ route('productos.update', $product) }}" class="mt-8">@csrf @method('PUT') @include('productos._form', ['product' => $product, 'submitLabel' => 'Actualizar producto'])</form>
        </section>
        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="product-status-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Registro #{{ $product->id }}</p><h2 id="product-status-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Control de estado</h2><p class="mt-3 text-sm leading-6 text-steel-300">Desactivar evita nuevas operaciones sin borrar la trazabilidad existente.</p>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5"><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado actual</dt><dd class="mt-2"><x-status-badge :active="$product->activo" /></dd></div><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Última actualización</dt><dd class="mt-1 text-sm text-white">{{ $product->updated_at->translatedFormat('d M Y · H:i') }}</dd></div></dl>
        </aside>
    </div>
@endsection
