@extends('layouts.app')

@section('title', 'Nuevo proveedor')
@section('section', 'Catálogo de proveedores')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="supplier-form-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Proveedores / Alta</p>
            <h1 id="supplier-form-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Nuevo proveedor</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Registra al responsable del abastecimiento para usarlo en cargas y pagos.</p>
            <form method="POST" action="{{ route('proveedores.store') }}" class="mt-8">@csrf @include('proveedores._form', ['supplier' => null, 'submitLabel' => 'Guardar proveedor'])</form>
        </section>
        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="supplier-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 20h18M5 20V8l7-4 7 4v12M9 11h6m-6 4h6" /></svg></span>
            <h2 id="supplier-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Ficha de suministro</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Una identificación clara evita errores al registrar cargas, saldos y pagos pendientes.</p>
            <ul class="mt-6 grid gap-3 text-sm text-white">@foreach (['Valida la razón social', 'Registra un contacto directo', 'Usa una dirección verificable'] as $tip)<li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>@endforeach</ul>
        </aside>
    </div>
@endsection
