@extends('layouts.app')

@section('title', 'Registrar pago a proveedor')
@section('section', 'Pagos a proveedores')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $selectedLoadId = old('carga_id', $preselectedLoadId);
        $canRegister = $loads->isNotEmpty() && $paymentMethods->isNotEmpty();
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('pagos-proveedor.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a pagos</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Nuevo egreso</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar pago</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Aplica un abono a una carga pendiente. El saldo y el efectivo disponible se validarán nuevamente al guardar.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
        <form method="POST" action="{{ route('pagos-proveedor.store') }}" data-provider-payment-form data-has-open-cash="{{ $openCashSession ? 'true' : 'false' }}" data-cash-available="{{ $openCashSummary?->efectivo_esperado ?? 0 }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Comprobante / Datos</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Aplicación del pago</h2></div>

            <div class="grid gap-5 p-5 sm:p-6">
                <x-form-field name="carga_id" label="Carga pendiente" hint="Ordenadas por antigüedad" required>
                    <select id="carga_id" name="carga_id" data-payment-load class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                        <option value="">Selecciona una carga</option>
                        @foreach ($loads as $load)
                            @php($loadBalance = $balances->get($load->id))
                            <option value="{{ $load->id }}" data-number="{{ $load->numero_carga }}" data-provider="{{ $load->proveedor->nombre_razon_social }}" data-product="{{ $load->producto->nombre }}" data-balance="{{ $loadBalance?->saldo_pendiente ?? 0 }}" data-cost="{{ $load->costo_total }}" @selected((string) $selectedLoadId === (string) $load->id)>{{ $load->numero_carga }} · {{ $load->proveedor->nombre_razon_social }} · saldo {{ $money($loadBalance?->saldo_pendiente) }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form-field name="medio_pago_id" label="Medio de pago" required>
                        <select id="medio_pago_id" name="medio_pago_id" data-payment-method class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                            <option value="">Selecciona un medio</option>
                            @foreach ($paymentMethods as $paymentMethod)
                                <option value="{{ $paymentMethod->id }}" data-cash="{{ $paymentMethod->es_efectivo ? 'true' : 'false' }}" @selected((string) old('medio_pago_id') === (string) $paymentMethod->id)>{{ $paymentMethod->nombre }}{{ $paymentMethod->es_efectivo ? ' · requiere caja' : '' }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field name="monto" label="Monto del pago" hint="Máximo: saldo pendiente" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold text-ink-950">S/</span><input id="monto" name="monto" data-payment-amount value="{{ old('monto') }}" type="number" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none placeholder:text-steel-300" required></div>
                        <button type="button" data-use-full-balance class="mt-2 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:text-ink-950">Usar saldo completo</button>
                    </x-form-field>
                </div>

                <x-form-field name="observacion" label="Observación" hint="Opcional">
                    <textarea id="observacion" name="observacion" rows="3" maxlength="255" placeholder="Referencia, operación o detalle del abono..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">{{ old('observacion') }}</textarea>
                </x-form-field>

                <div data-cash-requirement class="hidden border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="status">El pago en efectivo requiere una sesión de caja abierta. @if ($authenticatedUser?->tienePermiso('CAJA_ABRIR_CERRAR'))<a href="{{ route('caja.create') }}" class="font-semibold underline underline-offset-2">Abrir caja</a>@else Solicita la apertura a un responsable de caja o elige un medio no efectivo. @endif</div>

                @unless ($canRegister)
                    <div class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">
                        @if ($loads->isEmpty()) No existen cargas con saldo pendiente. @else No hay medios de pago activos disponibles. @endif
                    </div>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6">
                <a href="{{ route('pagos-proveedor.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a>
                <button type="submit" @disabled(! $canRegister) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar pago</button>
            </div>
        </form>

        <aside class="grid content-start gap-6">
            <section class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="payment-preview-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Cuenta</p>
                <h2 id="payment-preview-title" data-preview-load class="mt-2 font-display text-2xl font-bold uppercase">Selecciona una carga</h2>
                <p data-preview-provider class="mt-2 text-sm text-steel-300">Aquí verás el proveedor y el saldo actualizado.</p>
                <div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Saldo actual</p><p data-preview-balance class="mt-2 font-display text-2xl font-bold">{{ $money(0) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-hazard uppercase">Saldo posterior</p><p data-preview-remaining class="mt-2 font-display text-2xl font-bold text-hazard">{{ $money(0) }}</p></div></div>
                <div class="mt-5 border border-white/10 bg-white/5 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Producto</p><p data-preview-product class="mt-2 font-semibold">—</p></div>
            </section>

            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel" aria-labelledby="cash-availability-title">
                <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center {{ $openCashSession ? 'bg-signal text-white' : 'bg-danger-soft text-danger' }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Caja / Efectivo</p><h2 id="cash-availability-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">{{ $openCashSession ? 'Caja disponible' : 'Caja no abierta' }}</h2><p class="mt-1 text-sm text-steel-500">{{ $openCashSession ? 'Efectivo esperado: '.$money($openCashSummary?->efectivo_esperado) : 'Puedes usar medios no efectivos sin abrir caja.' }}</p></div></div>
            </section>
        </aside>
    </div>
@endsection
