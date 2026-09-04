@extends('layouts.app')

@section('title', 'Cierre del día')
@section('section', 'Apertura y cierre del día')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
    @endphp

    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="cash-closing-title">
            <a href="{{ route('caja.show', $cashSession) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a la jornada</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Arqueo / Jornada #{{ $cashSession->id }}</p>
            <h1 id="cash-closing-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Cierre del día</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Cuenta el efectivo físico. Yape, transferencias y demás medios ya aparecen conciliados por separado y forman parte del resultado general.</p>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Apertura</p><p class="mt-2 font-display text-xl font-bold text-ink-950">{{ $money($summary->monto_apertura) }}</p></div>
                <div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Efectivo esperado</p><p class="mt-2 font-display text-xl font-bold text-ink-950">{{ $money($summary->efectivo_esperado) }}</p></div>
                <div class="border border-line bg-canvas p-4"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Otros medios netos</p><p class="mt-2 font-display text-xl font-bold text-ink-950">{{ $money($summary->neto_otros_medios) }}</p></div>
                <div class="border-l-4 border-hazard bg-ink-950 p-4 text-white"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Resultado general</p><p class="mt-2 font-display text-xl font-bold">{{ $money($summary->resultado_general_sistema) }}</p></div>
            </div>

            <section class="mt-7 overflow-hidden border border-line" aria-labelledby="payment-methods-closing-title">
                <div class="border-b border-line bg-canvas px-5 py-4"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Todos los canales</p><h2 id="payment-methods-closing-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Resumen por medio de pago</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] border-collapse text-left" aria-label="Ingresos y egresos por medio de pago">
                        <thead class="bg-white font-mono text-[8px] tracking-wider text-steel-500 uppercase"><tr><th scope="col" class="px-5 py-3 font-semibold">Medio</th><th scope="col" class="px-5 py-3 text-right font-semibold">Ingresos</th><th scope="col" class="px-5 py-3 text-right font-semibold">Egresos</th><th scope="col" class="px-5 py-3 text-right font-semibold">Neto</th></tr></thead>
                        <tbody class="divide-y divide-line bg-paper">
                            @foreach ($paymentMethodBreakdown as $method)
                                <tr><td class="px-5 py-3"><p class="font-semibold text-ink-950">{{ $method->nombre }}</p><p class="mt-0.5 font-mono text-[8px] text-steel-500 uppercase">{{ $method->es_efectivo ? 'Efectivo físico' : 'Medio digital / bancario' }}</p></td><td class="px-5 py-3 text-right font-mono text-xs font-semibold text-signal">+ {{ $money($method->ingresos) }}</td><td class="px-5 py-3 text-right font-mono text-xs font-semibold text-danger">− {{ $money($method->egresos) }}</td><td class="px-5 py-3 text-right font-mono text-xs font-bold text-ink-950">{{ $money($method->neto) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <form method="POST" action="{{ route('caja.cierre.store', $cashSession) }}" class="mt-7" data-cash-close data-expected-cash="{{ $summary->efectivo_esperado }}">
                @csrf
                <div class="grid gap-6">
                    <x-form-field name="monto_contado_efectivo" label="Efectivo contado" hint="Conteo físico al cierre" required>
                        <div class="flex min-h-14 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15">
                            <span class="grid w-14 shrink-0 place-items-center border-r border-line bg-canvas font-display text-xl font-bold text-ink-950">S/</span>
                            <input id="monto_contado_efectivo" name="monto_contado_efectivo" value="{{ old('monto_contado_efectivo', number_format((float) $summary->efectivo_esperado, 2, '.', '')) }}" type="number" min="0" max="999999999999.99" step="0.01" inputmode="decimal" class="min-w-0 flex-1 px-4 font-display text-2xl font-bold text-ink-950 outline-none" aria-describedby="@error('monto_contado_efectivo') monto_contado_efectivo-error @enderror" data-counted-cash autofocus>
                        </div>
                    </x-form-field>

                    <div class="grid gap-3 sm:grid-cols-2" aria-live="polite">
                        <div class="border-l-4 border-hazard bg-ink-950 p-4 text-white"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Efectivo esperado</p><p class="mt-2 font-display text-2xl font-extrabold">{{ $money($summary->efectivo_esperado) }}</p><p class="mt-1 text-xs text-steel-300">Solo dinero físico</p></div>
                        <div data-cash-difference-panel class="border-l-4 border-signal bg-signal-soft p-4"><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Diferencia calculada</p><p data-cash-difference class="mt-2 font-display text-2xl font-extrabold text-signal">S/ 0,00</p></div>
                    </div>

                    <x-form-field name="observacion_cierre" label="Observación del cierre" hint="Obligatoria si existe diferencia">
                        <textarea id="observacion_cierre" name="observacion_cierre" rows="3" maxlength="255" placeholder="Describe faltantes, sobrantes o cualquier incidencia" class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" aria-describedby="@error('observacion_cierre') observacion_cierre-error @enderror">{{ old('observacion_cierre') }}</textarea>
                    </x-form-field>

                    <x-confirmacion-pin-administrador :administrators="$administrators" :pin-setup-user="$pinSetupUser" operation="el cierre del día" />
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('caja.show', $cashSession) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
                    <button type="submit" @disabled($administrators->isEmpty()) class="inline-flex min-h-12 items-center justify-center bg-danger px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-950 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar cierre</button>
                </div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="cash-closing-guide-title">
            <span class="grid size-12 place-items-center border border-danger/50 bg-danger/15 text-danger-soft"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span>
            <h2 id="cash-closing-guide-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Cierre irreversible</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Una jornada cerrada no puede reabrirse. Verifica el conteo y documenta cualquier diferencia antes de confirmar.</p>
            <dl class="mt-7 grid gap-4 border-y border-white/10 py-5 text-sm">
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Inicio</dt><dd class="mt-1 font-semibold">{{ $cashSession->apertura_at->format('d/m/Y · H:i:s') }}</dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Duración</dt><dd class="mt-1 font-semibold">{{ $cashSession->apertura_at->diffForHumans(now(), true) }}</dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Otros medios netos</dt><dd class="mt-1 font-semibold text-hazard">{{ $money($summary->neto_otros_medios) }}</dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Resultado general del día</dt><dd class="mt-1 font-display text-2xl font-extrabold text-white">{{ $money($summary->resultado_general_sistema) }}</dd></div>
            </dl>
            <p class="mt-6 text-xs leading-5 text-steel-300">El sistema registrará tu usuario como responsable del arqueo y conservará la diferencia calculada.</p>
        </aside>
    </div>
@endsection
