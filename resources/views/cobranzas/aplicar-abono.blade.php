@extends('layouts.app')

@section('title', 'Aplicar saldo de abono')
@section('section', 'Cobranzas')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $oldApplications = old('aplicaciones');

        if (! is_array($oldApplications) || $oldApplications === []) {
            $oldApplications = [['venta_id' => null, 'monto_aplicado' => null]];
        }

        $canApply = $pendingSales->isNotEmpty() && $remainingAmount > 0;
    @endphp

    <header class="reveal-up border-b border-line pb-7"><a href="{{ route('cobranzas.show', $collection) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a la cobranza</a><p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Distribución posterior</p><h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Aplicar saldo de abono</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Distribuye el dinero ya recibido entre ventas pendientes del mismo cliente. Esta operación no registra un nuevo ingreso ni modifica la caja.</p></header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_400px]">
        <form method="POST" action="{{ route('cobranzas.aplicaciones.store', $collection) }}" data-advance-application-form data-remaining="{{ $remainingAmount }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf
            <div class="flex flex-col gap-3 border-b border-line px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Distribución / Ventas</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Nuevas aplicaciones</h2></div><button type="button" data-add-advance-application @disabled(! $canApply) class="inline-flex min-h-11 items-center justify-center gap-2 border border-ink-950 px-4 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-ink-950 hover:text-white disabled:cursor-not-allowed disabled:opacity-40">Agregar venta <span aria-hidden="true">+</span></button></div>
            <div class="grid gap-5 p-5 sm:p-6">
                @error('aplicaciones')<p class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                <div data-advance-applications class="grid gap-4">@foreach ($oldApplications as $index => $application)<x-collection-application-row :index="$index" :pending-sales="$pendingSales" :application="$application" />@endforeach</div>
                <template data-advance-application-template><x-collection-application-row index="__APPLICATION__" :pending-sales="$pendingSales" /></template>

                @unless ($canApply)<div class="border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-sm leading-6 text-ink-700">{{ $remainingAmount <= 0 ? 'El abono ya fue distribuido por completo.' : 'Este cliente no tiene ventas pendientes disponibles para recibir el saldo.' }}</div>@endunless
                <div class="border-l-4 border-signal bg-signal-soft px-4 py-3 text-sm leading-6 text-ink-700"><strong>Sin movimiento de caja:</strong> solo se actualizarán las deudas de las ventas seleccionadas y quedará registrada la persona que realizó la distribución.</div>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('cobranzas.show', $collection) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a><button type="submit" @disabled(! $canApply) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar aplicación</button></div>
        </form>

        <aside class="grid content-start gap-6"><section class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="advance-preview-title"><p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Abono</p><h2 id="advance-preview-title" class="mt-2 font-display text-2xl font-bold uppercase">Distribución del saldo</h2><p class="mt-3 font-display text-xl font-bold uppercase">{{ $collection->cliente->nombres_razon_social }}</p><p class="mt-1 text-xs text-steel-300">{{ $collection->numero_cobranza }} · recibido {{ $collection->fecha_pago->format('d/m/Y') }}</p><div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Recibido</p><p class="mt-2 font-display text-2xl font-bold">{{ $money($collection->monto_total) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Ya aplicado</p><p class="mt-2 font-display text-2xl font-bold text-signal">{{ $money($appliedAmount) }}</p></div></div><div class="mt-px border border-white/10 bg-white/5 p-4"><div class="flex items-center justify-between gap-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Disponible ahora</p><p class="font-display text-2xl font-bold text-hazard">{{ $money($remainingAmount) }}</p></div><div class="mt-4 flex items-center justify-between gap-4 border-t border-white/10 pt-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Nueva aplicación</p><p data-advance-preview-applied class="font-display text-2xl font-bold text-signal">{{ $money(0) }}</p></div><div class="mt-4 flex items-center justify-between gap-4 border-t border-white/10 pt-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Quedará disponible</p><p data-advance-preview-remaining class="font-display text-2xl font-bold text-hazard">{{ $money($remainingAmount) }}</p></div><p data-advance-preview-message class="mt-3 text-xs leading-5 text-steel-300" aria-live="polite">Selecciona una venta y define el importe a aplicar.</p></div></section>

            @if ($collection->aplicaciones->isNotEmpty())<section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Distribución existente</p><ul class="mt-4 grid gap-3">@foreach ($collection->aplicaciones as $application)<li class="flex items-center justify-between gap-4 border-b border-line pb-3 text-sm last:border-0 last:pb-0"><span><strong class="block font-mono text-[10px] text-ink-950">{{ $application->venta->numero_venta }}</strong><span class="mt-1 block text-xs text-steel-500">{{ $application->created_at->format('d/m/Y') }}</span></span><strong class="text-signal">{{ $money($application->monto_aplicado) }}</strong></li>@endforeach</ul></section>@endif
        </aside>
    </div>
@endsection
