@extends('layouts.app')

@section('title', 'Apertura del día')
@section('section', 'Apertura y cierre del día')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="cash-opening-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Tesorería / Apertura</p>
            <h1 id="cash-opening-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Apertura del día</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Confirma el efectivo físico con el que inicia la jornada. Los cobros y pagos en Yape, transferencia y otros medios se controlarán por separado en el cierre.</p>

            @if ($openSession)
                <div class="mt-8 border-l-4 border-hazard bg-hazard-soft p-5">
                    <p class="font-display text-xl font-bold text-ink-950 uppercase">Ya existe una apertura activa</p>
                    <p class="mt-2 text-sm text-ink-700">La jornada #{{ $openSession->id }} está activa desde {{ $openSession->apertura_at->format('d/m/Y H:i') }}. Debes registrar su cierre antes de iniciar otra.</p>
                    <a href="{{ route('caja.show', $openSession) }}" class="mt-5 inline-flex min-h-11 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase">Ver jornada</a>
                </div>
            @else
                <form method="POST" action="{{ route('caja.store') }}" class="mt-8">
                    @csrf
                    @if ($previousClosedSession)
                        <div class="mb-6 grid gap-4 border-l-4 border-signal bg-signal-soft p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div>
                                <p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Saldo del cierre anterior</p>
                                <p class="mt-2 text-sm leading-6 text-ink-700">Jornada #{{ $previousClosedSession->id }} del {{ $previousClosedSession->fecha_operacion->format('d/m/Y') }}. Se precarga solo el efectivo físico contado.</p>
                            </div>
                            <p class="font-display text-3xl font-extrabold text-ink-950">S/ {{ number_format((float) $previousClosedSession->monto_contado_efectivo, 2, ',', '.') }}</p>
                        </div>
                    @else
                        <div class="mb-6 border-l-4 border-steel-300 bg-canvas p-5">
                            <p class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Sin cierre anterior</p>
                            <p class="mt-2 text-sm leading-6 text-ink-700">No existe una jornada previa cerrada para precargar. El efectivo inicial comienza en cero y puedes editarlo.</p>
                        </div>
                    @endif

                    <x-form-field name="monto_apertura" label="Efectivo inicial del día" hint="Precargado desde el último cierre; puedes corregirlo" required>
                        <div class="flex min-h-14 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15">
                            <span class="grid w-14 shrink-0 place-items-center border-r border-line bg-canvas font-display text-xl font-bold text-ink-950">S/</span>
                            <input id="monto_apertura" name="monto_apertura" value="{{ old('monto_apertura', number_format((float) ($previousClosedSession?->monto_contado_efectivo ?? 0), 2, '.', '')) }}" type="number" min="0" max="999999999999.99" step="0.01" inputmode="decimal" class="min-w-0 flex-1 px-4 font-display text-2xl font-bold text-ink-950 outline-none" aria-describedby="@error('monto_apertura') monto_apertura-error @enderror" autofocus>
                        </div>
                    </x-form-field>

                    <div class="mt-6 flex items-start gap-3 border border-line bg-canvas p-4">
                        <span class="mt-0.5 grid size-6 shrink-0 place-items-center bg-signal-soft font-mono text-xs font-bold text-signal">i</span>
                        <p class="text-sm leading-6 text-steel-500">El valor es editable: confirma el dinero físico real. Yape y transferencias no se convierten en efectivo de apertura.</p>
                    </div>

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                        <a href="{{ route('caja.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
                        <button type="submit" class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Confirmar apertura</button>
                    </div>
                </form>
            @endif
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="cash-opening-guide-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span>
            <h2 id="cash-opening-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Una jornada por usuario</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">El sistema impide aperturas duplicadas y conecta automáticamente los movimientos con la jornada vigente.</p>
            <div class="mt-7 border border-white/10 bg-white/5 p-5"><p class="font-mono text-[9px] tracking-[0.18em] text-hazard uppercase">Fecha operativa</p><p class="mt-2 font-display text-3xl font-extrabold">{{ today()->format('d.m.Y') }}</p><p class="mt-1 text-xs text-steel-300">{{ now()->translatedFormat('l · H:i') }}</p></div>
            <ul class="mt-6 grid gap-3 text-sm text-white">@foreach (['Confirma o edita el saldo precargado', 'No mezcles saldos digitales con efectivo', 'Registra el cierre al terminar la jornada'] as $tip)<li class="flex items-center gap-3 border-b border-white/10 pb-3"><span class="size-2 shrink-0 bg-hazard"></span>{{ $tip }}</li>@endforeach</ul>
        </aside>
    </div>
@endsection
