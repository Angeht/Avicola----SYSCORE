@extends('layouts.app')

@section('title', 'Registrar pago a proveedor')
@section('section', 'Pagos a proveedores')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $selectedProviderId = old('proveedor_id', $preselectedProviderId);
        $canRegister = $supplierDebts->isNotEmpty() && $paymentMethods->isNotEmpty();
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('pagos-proveedor.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a pagos</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Nuevo egreso</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar pago</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Registra un abono sobre la deuda total del proveedor. El sistema lo aplicará automáticamente a sus cargas pendientes más antiguas.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
        <form method="POST" action="{{ route('pagos-proveedor.store') }}" data-provider-payment-form data-has-open-cash="{{ $openCashSession ? 'true' : 'false' }}" data-cash-available="{{ $openCashSummary?->efectivo_esperado ?? 0 }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Comprobante / Datos</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Aplicación del pago</h2></div>

            <div class="grid gap-5 p-5 sm:p-6">
                <x-form-field name="proveedor_id" label="Proveedor" hint="El pago cubre su deuda acumulada" required>
                    <select id="proveedor_id" name="proveedor_id" data-payment-provider class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required autofocus>
                        <option value="">Selecciona un proveedor</option>
                        @foreach ($supplierDebts as $supplierDebt)
                            <option value="{{ $supplierDebt->proveedor_id }}" data-name="{{ $supplierDebt->proveedor }}" data-balance="{{ $supplierDebt->deuda_total }}" data-loads="{{ $supplierDebt->cargas_pendientes }}" data-account="{{ $supplierDebt->numero_cuenta }}" @selected((string) $selectedProviderId === (string) $supplierDebt->proveedor_id)>{{ $supplierDebt->proveedor }} · deuda total {{ $money($supplierDebt->deuda_total) }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <section class="overflow-hidden border border-line" aria-labelledby="pending-provider-loads-title">
                    <div class="flex items-center justify-between gap-3 border-b border-line bg-canvas px-4 py-3"><div><p class="font-mono text-[8px] font-semibold tracking-wider text-signal uppercase">Detalle / Distribución automática</p><h3 id="pending-provider-loads-title" class="mt-1 font-display text-lg font-bold text-ink-950 uppercase">Cargas pendientes</h3></div><span data-provider-load-count class="font-mono text-[9px] text-steel-500 uppercase">0 cargas</span></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] border-collapse text-left" aria-label="Cargas pendientes del proveedor seleccionado">
                            <thead class="bg-paper font-mono text-[8px] tracking-wider text-steel-500 uppercase"><tr><th class="px-4 py-3 font-semibold">Carga</th><th class="px-4 py-3 font-semibold">Producto</th><th class="px-4 py-3 text-right font-semibold">Costo</th><th class="px-4 py-3 text-right font-semibold">Abonado / ajustado</th><th class="px-4 py-3 text-right font-semibold">Saldo</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($loads as $load)
                                    @php($loadBalance = $balances->get($load->id))
                                    <tr data-provider-load-row data-provider-id="{{ $load->proveedor_id }}" class="{{ (string) $selectedProviderId === (string) $load->proveedor_id ? '' : 'hidden' }}">
                                        <td class="px-4 py-3"><p class="font-mono text-xs font-semibold text-ink-950">{{ $load->numero_carga }}</p><p class="mt-1 text-xs text-steel-500">{{ $load->fecha_carga->format('d/m/Y') }}</p></td>
                                        <td class="px-4 py-3 text-sm text-ink-700">{{ $load->producto->nombre }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-xs text-ink-700">{{ $money($load->costo_total) }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-xs text-signal">{{ $money(((float) ($loadBalance?->total_pagado ?? 0)) + ((float) ($loadBalance?->total_ajustado ?? 0))) }}</td>
                                        <td class="px-4 py-3 text-right font-display text-lg font-bold text-danger">{{ $money($loadBalance?->saldo_pendiente) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p data-provider-loads-empty class="{{ $selectedProviderId ? 'hidden' : '' }} px-4 py-5 text-center text-sm text-steel-500">Selecciona un proveedor para revisar las cargas, abonos y saldos que forman su deuda.</p>
                </section>

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

                @if ($canAdjustProvider)
                    <section class="border border-line bg-canvas p-4" aria-label="Descuento opcional del proveedor">
                        <label class="flex cursor-pointer items-start gap-3"><input type="checkbox" name="aplicar_descuento" value="1" data-provider-discount-toggle @checked(old('aplicar_descuento')) class="mt-1 size-4 accent-signal"><span><span class="block font-display text-sm font-bold tracking-wide text-ink-950 uppercase">El proveedor reconoce un descuento</span><span class="mt-1 block text-xs leading-5 text-steel-500">Úsalo solo si el descuento fue acordado en este pago. No genera salida de caja.</span></span></label>
                        <div data-provider-discount-fields class="{{ old('aplicar_descuento') ? '' : 'hidden' }} mt-4 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                            <x-form-field name="monto_descuento" label="Descuento reconocido"><div class="flex min-h-12 border border-line bg-white"><span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold">S/</span><input name="monto_descuento" data-provider-discount-amount value="{{ old('monto_descuento') }}" type="number" min="0.01" step="0.01" inputmode="decimal" class="min-w-0 flex-1 px-4 text-sm font-semibold outline-none" @disabled(! old('aplicar_descuento'))></div></x-form-field>
                            <x-form-field name="motivo_descuento" label="Motivo"><input name="motivo_descuento" data-provider-discount-reason value="{{ old('motivo_descuento') }}" type="text" minlength="5" maxlength="255" placeholder="Ej. Diferencia de calidad" class="min-h-12 w-full border border-line bg-white px-4 text-sm outline-none" @disabled(! old('aplicar_descuento'))></x-form-field>
                        </div>
                    </section>
                @endif

                <x-form-field name="observacion" label="Observación" hint="Opcional">
                    <textarea id="observacion" name="observacion" rows="3" maxlength="255" placeholder="Referencia, operación o detalle del abono..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">{{ old('observacion') }}</textarea>
                </x-form-field>

                <div data-cash-requirement class="hidden border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="status">El pago en efectivo requiere una sesión de caja abierta. @if ($authenticatedUser?->tienePermiso('CAJA_ABRIR_CERRAR'))<a href="{{ route('caja.create') }}" class="font-semibold underline underline-offset-2">Abrir caja</a>@else Solicita la apertura a un responsable de caja o elige un medio no efectivo. @endif</div>

                @unless ($canRegister)
                    <div class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">
                        @if ($supplierDebts->isEmpty()) No existen proveedores con deuda pendiente. @else No hay medios de pago activos disponibles. @endif
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
                <h2 id="payment-preview-title" data-preview-provider class="mt-2 font-display text-2xl font-bold uppercase">Selecciona un proveedor</h2>
                <p class="mt-2 text-sm text-steel-300">La deuda se calcula con todas sus cargas vigentes.</p>
                <div class="mt-6 grid grid-cols-2 gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Deuda acumulada</p><p data-preview-balance class="mt-2 font-display text-2xl font-bold">{{ $money(0) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-hazard uppercase">Saldo posterior</p><p data-preview-remaining class="mt-2 font-display text-2xl font-bold text-hazard">{{ $money(0) }}</p></div></div>
                <div data-provider-discount-preview class="hidden mt-px bg-white/5 p-4"><p class="font-mono text-[8px] text-signal uppercase">Descuento aplicado</p><p data-provider-discount-preview-amount class="mt-2 font-display text-xl font-bold text-signal">{{ $money(0) }}</p></div>
                <div class="mt-5 grid gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Cargas pendientes</p><p data-preview-loads class="mt-2 font-semibold">—</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Cuenta / CCI</p><p data-preview-account class="mt-2 break-all font-mono text-xs font-semibold">No registrada</p></div></div>
            </section>

            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel" aria-labelledby="cash-availability-title">
                <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center {{ $openCashSession ? 'bg-signal text-white' : 'bg-danger-soft text-danger' }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Caja / Efectivo</p><h2 id="cash-availability-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">{{ $openCashSession ? 'Caja disponible' : 'Caja no abierta' }}</h2><p class="mt-1 text-sm text-steel-500">{{ $openCashSession ? 'Efectivo esperado: '.$money($openCashSummary?->efectivo_esperado) : 'Puedes usar medios no efectivos sin abrir caja.' }}</p></div></div>
            </section>
        </aside>
    </div>
@endsection
