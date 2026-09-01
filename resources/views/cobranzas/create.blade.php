@extends('layouts.app')

@section('title', 'Registrar cobranza')
@section('section', 'Cobranzas')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $selectedClientId = old('cliente_id', $preselectedClientId);
        $selectedClient = $clients->firstWhere('id', (int) $selectedClientId);
        $receivedAmount = old('monto_total');
        $canRegister = $paymentMethods->isNotEmpty() && $clients->isNotEmpty();
    @endphp

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('cobranzas.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a cobranzas</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Tesorería / Pago de cliente</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Registrar cobranza</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Selecciona al cliente, revisa su deuda total e ingresa cuánto está pagando. El sistema descontará el monto automáticamente, sea un pago parcial o completo.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_380px]">
        <form method="POST" action="{{ route('cobranzas.store') }}" data-collection-form data-has-open-cash="{{ $openCashSession ? 'true' : 'false' }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf

            <div class="border-b border-line px-5 py-5 sm:px-6">
                <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Documento / Recepción</p>
                <h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Datos del pago</h2>
            </div>

            <div class="grid gap-6 p-5 sm:p-6">
                <x-form-field name="cliente_id" label="Cliente" hint="Solo aparecen clientes con deuda pendiente" required>
                    <select id="cliente_id" name="cliente_id" data-collection-client class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                        <option value="">Selecciona un cliente</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" data-debt="{{ $client->deuda_total }}" data-pending-sales="{{ $client->ventas_pendientes }}" @selected((string) $selectedClientId === (string) $client->id)>{{ $client->nombres_razon_social }}{{ $client->nro_documento ? ' · '.$client->nro_documento : '' }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <section class="grid gap-px border border-line bg-line sm:grid-cols-2" aria-label="Deuda seleccionada">
                    <div class="bg-canvas p-4"><p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Deuda actual</p><p data-collection-current-debt class="mt-2 font-display text-3xl font-bold text-ink-950">{{ $money($selectedClient?->deuda_total) }}</p></div>
                    <div class="bg-canvas p-4"><p class="font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase">Ventas pendientes</p><p data-collection-pending-sales class="mt-2 font-display text-3xl font-bold text-ink-950">{{ number_format((int) ($selectedClient?->ventas_pendientes ?? 0), 0, ',', '.') }}</p></div>
                </section>

                <div class="grid gap-5 lg:grid-cols-2">
                    <x-form-field name="monto_total" label="Monto que paga" hint="Puede ser parcial o el total de la deuda" required>
                        <div class="flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15">
                            <span class="grid w-12 shrink-0 place-items-center border-r border-line bg-canvas font-display text-lg font-bold text-ink-950">S/</span>
                            <input id="monto_total" name="monto_total" data-collection-total value="{{ $receivedAmount }}" type="number" min="0.01" max="{{ $selectedClient?->deuda_total ?? '999999999999.99' }}" step="0.01" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 px-4 text-sm font-semibold text-ink-950 outline-none placeholder:text-steel-300" required>
                            <button type="button" data-use-client-debt class="shrink-0 border-l border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white disabled:cursor-not-allowed disabled:text-steel-300">Pagar total</button>
                        </div>
                    </x-form-field>

                    <x-form-field name="medio_pago_id" label="Medio de pago" required>
                        <select id="medio_pago_id" name="medio_pago_id" data-collection-payment-method class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
                            <option value="">Selecciona un medio</option>
                            @foreach ($paymentMethods as $paymentMethod)
                                <option value="{{ $paymentMethod->id }}" data-cash="{{ $paymentMethod->es_efectivo ? 'true' : 'false' }}" @selected((string) old('medio_pago_id') === (string) $paymentMethod->id)>{{ $paymentMethod->nombre }}{{ $paymentMethod->es_efectivo ? ' · requiere caja' : '' }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                </div>

                <x-form-field name="observacion" label="Observación" hint="Opcional">
                    <textarea id="observacion" name="observacion" rows="3" maxlength="255" placeholder="Operación, referencia o detalle del pago..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">{{ old('observacion') }}</textarea>
                </x-form-field>

                <div data-collection-cash-requirement class="hidden border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm text-danger" role="status">La cobranza en efectivo requiere una sesión de caja abierta. @if ($authenticatedUser?->tienePermiso('CAJA_ABRIR_CERRAR'))<a href="{{ route('caja.create') }}" class="font-semibold underline underline-offset-2">Abrir caja</a>@else Solicita la apertura a un responsable de caja o elige un medio no efectivo. @endif</div>

                @unless ($canRegister)
                    <div class="border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">No hay clientes con deuda pendiente o medios de pago activos para registrar una cobranza.</div>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('cobranzas.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Cancelar</a><button type="submit" data-collection-submit @disabled(! $canRegister) class="inline-flex min-h-12 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:bg-steel-300">Registrar pago</button></div>
        </form>

        <aside class="grid content-start gap-6">
            <section class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="collection-preview-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Cliente</p><h2 id="collection-preview-title" class="mt-2 font-display text-2xl font-bold uppercase">Resultado del pago</h2>
                <div class="mt-6 grid gap-px border border-white/10 bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Deuda antes del pago</p><p data-collection-preview-debt class="mt-2 font-display text-2xl font-bold">{{ $money($selectedClient?->deuda_total) }}</p></div><div class="grid grid-cols-2 gap-px bg-white/10"><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Abono</p><p data-collection-preview-payment class="mt-2 font-display text-2xl font-bold text-signal">{{ $money($receivedAmount) }}</p></div><div class="bg-ink-950 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Restante</p><p data-collection-preview-remaining class="mt-2 font-display text-2xl font-bold text-hazard">{{ $money(max(0, (float) ($selectedClient?->deuda_total ?? 0) - (float) ($receivedAmount ?? 0))) }}</p></div></div></div>
                <div class="mt-5 border-t border-white/10 pt-5"><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Cliente seleccionado</p><p data-collection-preview-client class="mt-2 font-display text-xl font-bold uppercase">{{ $selectedClient?->nombres_razon_social ?? 'Selecciona un cliente' }}</p><p data-collection-preview-message class="mt-2 text-xs leading-5 text-steel-300">Ingresa el monto recibido para calcular el saldo restante.</p></div>
            </section>

            <section class="reveal-up reveal-up-delay-2 border border-line bg-paper p-5 shadow-panel" aria-labelledby="collection-cash-title">
                <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center {{ $openCashSession ? 'bg-signal text-white' : 'bg-danger-soft text-danger' }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></span><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Caja / Recepción</p><h2 id="collection-cash-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">{{ $openCashSession ? 'Caja disponible' : 'Caja no abierta' }}</h2><p class="mt-1 text-sm text-steel-500">{{ $openCashSession ? 'Efectivo esperado actual: '.$money($openCashSummary?->efectivo_esperado) : 'Puedes cobrar por medios no efectivos sin abrir caja.' }}</p></div></div>
            </section>

            <section class="reveal-up reveal-up-delay-2 border-l-4 border-hazard bg-hazard-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Aplicación automática</p><p class="mt-2 text-sm leading-6 text-ink-700">El pago se descuenta del total del cliente. Internamente se aplica primero a sus ventas pendientes más antiguas para conservar la trazabilidad.</p></section>
        </aside>
    </div>
@endsection
