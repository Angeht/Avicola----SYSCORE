@extends('layouts.app')

@section('title', 'Anular pago')
@section('section', 'Pagos a proveedores')

@section('content')
    @php($money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.'))

    <header class="reveal-up border-b border-line pb-7">
        <a href="{{ route('pagos-proveedor.show', $payment) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver al pago</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-danger uppercase">Auditoría / Corrección</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Anular pago</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">La operación conservará su historial, dejará de sumar como pago vigente y devolverá el importe al saldo de la carga.</p>
    </header>

    <div class="mx-auto mt-6 grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ route('pagos-proveedor.anulacion.store', $payment) }}" data-confirm="¿Confirmas la anulación de este pago? Esta acción quedará registrada en la auditoría." class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" novalidate>
            @csrf
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-danger uppercase">Motivo obligatorio</p><h2 class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Justificación de la anulación</h2></div>
            <div class="p-5 sm:p-6">
                <x-form-field name="motivo_anulacion" label="Motivo" hint="Entre 10 y 255 caracteres" required>
                    <textarea id="motivo_anulacion" name="motivo_anulacion" rows="5" minlength="10" maxlength="255" placeholder="Explica por qué debe anularse este pago..." class="w-full resize-y border border-line bg-white px-4 py-3 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-danger focus:ring-2 focus:ring-danger/15" required autofocus>{{ old('motivo_anulacion') }}</textarea>
                </x-form-field>
                <div class="mt-5 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm leading-6 text-danger">No se eliminará el registro. Se guardarán el usuario, la fecha y el motivo de esta anulación.</div>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-line px-5 py-5 sm:flex-row sm:justify-end sm:px-6"><a href="{{ route('pagos-proveedor.show', $payment) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-6 font-display text-sm font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">Conservar pago</a><button type="submit" class="inline-flex min-h-12 items-center justify-center bg-danger px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-red-700">Confirmar anulación</button></div>
        </form>

        <aside class="industrial-hatch panel-cut reveal-up reveal-up-delay-2 bg-ink-950 p-6 text-white shadow-panel">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Pago seleccionado</p><h2 class="mt-2 font-mono text-sm font-semibold">{{ $payment->numero_pago }}</h2><p class="mt-5 font-display text-4xl font-extrabold">{{ $money($payment->monto) }}</p><div class="mt-6 border-t border-white/10 pt-5"><p class="font-display text-xl font-bold uppercase">{{ $payment->carga->proveedor->nombre_razon_social }}</p><p class="mt-2 font-mono text-[9px] tracking-wider text-steel-300 uppercase">{{ $payment->carga->numero_carga }} · {{ $payment->medioPago->nombre }}</p><p class="mt-3 text-sm text-steel-300">Registrado por {{ $payment->pagadoPor->nombreCompleto() }} el {{ $payment->pagado_at->format('d/m/Y \\a \\l\\a\\s H:i') }}.</p></div>
        </aside>
    </div>
@endsection
