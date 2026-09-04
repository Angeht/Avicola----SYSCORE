@extends('layouts.app')

@section('title', 'Anular carga')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $money = fn (float|int|string|null $value): string => 'S/ '.number_format((float) ($value ?? 0), 2, ',', '.');
        $quantity = fn (float|int|string|null $value): string => number_format((float) ($value ?? 0), 3, ',', '.');
    @endphp

    <header class="reveal-up max-w-3xl border-b border-line pb-7">
        <a href="{{ route('cargas-proveedor.show', $load) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">← Volver al detalle</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-danger uppercase">Corrección / {{ $load->numero_carga }}</p>
        <h1 class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Anular carga</h1>
        <p class="mt-3 text-sm leading-6 text-steel-500">La operación quedará identificada en auditoría y dejará de afectar el stock y la deuda con el proveedor.</p>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ route('cargas-proveedor.anulacion.store', $load) }}" class="reveal-up reveal-up-delay-1 border border-line bg-paper p-6 shadow-panel sm:p-8">
            @csrf
            <div class="border-l-4 border-danger bg-danger-soft p-5">
                <p class="font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Acción permanente</p>
                <p class="mt-2 text-sm leading-6 text-ink-700">Los pesajes y el registro original se conservarán como evidencia. No se eliminará información.</p>
            </div>

            <div class="mt-6">
                <label for="motivo_anulacion" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Motivo de anulación</label>
                <textarea id="motivo_anulacion" name="motivo_anulacion" rows="5" minlength="10" maxlength="255" required class="mt-2 w-full border border-line bg-white p-4 text-sm leading-6 text-ink-950 outline-none focus:border-danger focus:ring-2 focus:ring-danger/20" placeholder="Explica qué ocurrió y por qué debe anularse esta carga...">{{ old('motivo_anulacion') }}</textarea>
                @error('motivo_anulacion')<p class="mt-2 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-steel-500">Mínimo 10 y máximo 255 caracteres.</p>
            </div>

            <x-confirmacion-pin-administrador :administrators="$administrators" :pin-setup-user="$pinSetupUser" operation="la anulación de la carga" class="mt-6" />

            <div class="mt-7 flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('cargas-proveedor.show', $load) }}" class="inline-flex min-h-12 items-center justify-center border border-line px-5 font-display text-sm font-bold tracking-wider text-ink-700 uppercase">Cancelar</a>
                <button type="submit" @disabled($administrators->isEmpty()) class="inline-flex min-h-12 items-center justify-center bg-danger px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-950 disabled:cursor-not-allowed disabled:bg-steel-300">Confirmar anulación</button>
            </div>
        </form>

        <aside class="reveal-up reveal-up-delay-2 grid content-start gap-4">
            <section class="industrial-hatch bg-ink-950 p-6 text-white shadow-panel">
                <p class="font-mono text-[9px] tracking-wider text-hazard uppercase">Impacto a revertir</p>
                <dl class="mt-5 grid gap-4 text-sm"><div><dt class="text-steel-300">Proveedor</dt><dd class="mt-1 font-semibold">{{ $load->proveedor->nombre_razon_social }}</dd></div><div><dt class="text-steel-300">Producto</dt><dd class="mt-1 font-semibold">{{ $load->producto->nombre }}</dd></div><div class="grid grid-cols-2 gap-3 border-t border-white/10 pt-4"><div><dt class="text-steel-300">Peso neto</dt><dd class="mt-1 font-display text-xl font-bold">{{ $quantity($summary->peso_neto_kg) }} kg</dd></div><div><dt class="text-steel-300">Deuda</dt><dd class="mt-1 font-display text-xl font-bold text-hazard">{{ $money($balance->saldo_pendiente) }}</dd></div></div></dl>
            </section>
            <section class="border-l-4 border-hazard bg-hazard-soft p-5 text-sm leading-6 text-ink-700"><strong>Importante:</strong> una carga con pagos vigentes no puede anularse. Primero deben anularse esos pagos para mantener consistente la caja.</section>
        </aside>
    </div>
@endsection
