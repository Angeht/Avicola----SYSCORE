@extends('layouts.app')

@section('title', 'Nuevo producto')
@section('section', 'Catálogo de productos')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="product-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Productos / Alta</p><h1 id="product-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nuevo producto</h1><p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Crea un producto para integrarlo con precios, cargas, ventas y saldos de mercadería.</p>
            <form method="POST" action="{{ route('productos.store') }}" class="mt-8">@csrf @include('productos._form', ['product' => null, 'submitLabel' => 'Guardar producto'])</form>
        </section>
        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="product-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /></svg></span><h2 id="product-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Unidad consistente</h2><p class="mt-3 text-sm leading-6 text-steel-300">La unidad seleccionada se utilizará en toda la trazabilidad del producto.</p>
            <ul class="mt-6 grid gap-3 text-sm text-white">@foreach (['Usa nombres cortos y claros', 'Evita productos duplicados', 'Confirma la unidad antes de guardar'] as $tip)<li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>@endforeach</ul>
        </aside>
    </div>
@endsection
