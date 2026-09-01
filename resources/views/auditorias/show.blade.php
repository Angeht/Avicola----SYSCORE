<div>
    <!-- Act only according to that maxim whereby you can, at the same time, will that it should become a universal law. - Immanuel Kant -->
</div>
@extends('layouts.app')

@section('title', 'Evento de auditoría')
@section('section', 'Auditoría general')

@section('content')
    @php($actionClass = match ($audit->accion) { 'ANULAR', 'DELETE' => 'border-danger/30 bg-danger-soft text-danger', 'INSERT' => 'border-signal/30 bg-signal-soft text-signal', 'LOGIN' => 'border-hazard/40 bg-hazard-soft text-ink-950', default => 'border-line bg-canvas text-ink-700' })

    <header class="reveal-up border-b border-ink-950 pb-7">
        <a href="{{ route('auditorias.index') }}" class="inline-flex items-center gap-2 font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase transition hover:text-ink-950">← Volver al historial</a>
        <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="font-mono text-[10px] font-semibold tracking-[0.2em] text-signal uppercase">Evento #{{ $audit->id }}</p><h1 class="mt-2 font-display text-4xl font-bold text-ink-950 uppercase sm:text-5xl">{{ $audit->etiquetaTabla() }}</h1><p class="mt-3 text-sm text-steel-500">Registro {{ $audit->registro_id ? '#'.$audit->registro_id : 'sin referencia' }} · {{ $audit->created_at->format('d/m/Y H:i:s') }}</p></div>
            <span class="inline-flex self-start border px-4 py-2 font-mono text-[10px] font-semibold tracking-wider uppercase {{ $actionClass }}">{{ $audit->etiquetaAccion() }}</span>
        </div>
    </header>

    <section class="reveal-up reveal-up-delay-1 mt-6 grid gap-4 lg:grid-cols-3" aria-label="Metadatos del evento">
        <article class="border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">Responsable</p><p class="mt-2 font-display text-2xl font-bold text-ink-950">{{ $audit->usuario?->nombreCompleto() ?? 'Sistema / usuario eliminado' }}</p><p class="mt-1 text-sm text-steel-500">{{ $audit->usuario?->usuario ?? 'Sin cuenta disponible' }}</p></article>
        <article class="border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">Origen</p><p class="mt-2 font-display text-2xl font-bold text-ink-950">{{ $audit->ip ?: 'No disponible' }}</p><p class="mt-1 text-sm text-steel-500">Dirección IP registrada</p></article>
        <article class="border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">Cambios</p><p class="mt-2 font-display text-2xl font-bold text-ink-950">{{ $audit->detalles->count() }}</p><p class="mt-1 text-sm text-steel-500">Campos documentados</p></article>
    </section>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="changes-title">
        <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Antes / Después</p><h2 id="changes-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Detalle del cambio</h2></div>
        @if ($audit->detalles->isEmpty())
            <x-empty-state title="Evento sin cambios de campos" description="Este tipo de evento registra identidad, fecha e IP, pero no modifica atributos." />
        @else
            <div class="divide-y divide-line">
                @foreach ($audit->detalles as $detail)
                    <article class="grid gap-4 p-5 sm:p-6 lg:grid-cols-[220px_1fr_40px_1fr] lg:items-center">
                        <div><p class="font-semibold text-ink-950">{{ $detail->etiquetaCampo() }}</p><p class="mt-1 font-mono text-[9px] text-steel-500">{{ $detail->campo }}</p></div>
                        <div class="min-w-0 border border-line bg-canvas p-3"><p class="font-mono text-[8px] text-steel-500 uppercase">Valor anterior</p><p class="mt-2 break-words text-sm text-ink-700">{{ filled($detail->valor_anterior) ? $detail->valor_anterior : 'Sin valor' }}</p></div>
                        <span class="hidden text-center text-steel-300 lg:block">→</span>
                        <div class="min-w-0 border border-signal/25 bg-signal-soft p-3"><p class="font-mono text-[8px] text-signal uppercase">Valor nuevo</p><p class="mt-2 break-words text-sm font-semibold text-ink-950">{{ filled($detail->valor_nuevo) ? $detail->valor_nuevo : 'Sin valor' }}</p></div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
