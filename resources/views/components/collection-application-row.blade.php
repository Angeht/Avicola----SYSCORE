@props([
    'index',
    'pendingSales',
    'application' => [],
])

@php
    $selectedSaleId = $application['venta_id'] ?? null;
    $selectedAmount = $application['monto_aplicado'] ?? null;
@endphp

<article data-collection-application class="border border-line bg-canvas p-4 sm:p-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3"><span data-collection-application-number class="grid size-8 place-items-center bg-ink-950 font-mono text-[10px] font-bold text-white">{{ is_numeric($index) ? ((int) $index + 1) : '—' }}</span><div><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Aplicación</p><p class="text-xs text-steel-500">Distribución a una venta pendiente</p></div></div>
        <button type="button" data-remove-collection-application class="inline-flex min-h-10 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase transition hover:border-danger hover:text-danger disabled:cursor-not-allowed disabled:opacity-30">Quitar</button>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_210px]">
        <div>
            <label for="aplicaciones_{{ $index }}_venta_id" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Venta <span class="text-danger" aria-hidden="true">*</span></label>
            <select id="aplicaciones_{{ $index }}_venta_id" name="aplicaciones[{{ $index }}][venta_id]" data-collection-sale class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15">
                <option value="">Selecciona una venta pendiente</option>
                @foreach ($pendingSales as $sale)
                    <option value="{{ $sale->venta_id }}" data-client="{{ $sale->cliente_id ?? '' }}" data-number="{{ $sale->numero_venta }}" data-customer="{{ $sale->cliente ?? 'Cliente anónimo' }}" data-balance="{{ $sale->saldo_pendiente }}" data-total="{{ $sale->total_venta }}" data-date="{{ \Illuminate\Support\Carbon::parse($sale->fecha_venta)->format('d/m/Y') }}" @selected((string) $selectedSaleId === (string) $sale->venta_id)>{{ $sale->numero_venta }} · {{ $sale->cliente ?? 'Anónima' }} · saldo S/ {{ number_format((float) $sale->saldo_pendiente, 2, ',', '.') }}</option>
                @endforeach
            </select>
            @error("aplicaciones.$index.venta_id")
                <p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
            @enderror
            <p data-collection-sale-meta class="mt-2 text-xs text-steel-500">Selecciona una venta para consultar su saldo.</p>
        </div>

        <div>
            <label for="aplicaciones_{{ $index }}_monto_aplicado" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Monto aplicado <span class="text-danger" aria-hidden="true">*</span></label>
            <div class="mt-2 flex min-h-12 border border-line bg-white transition focus-within:border-signal focus-within:ring-2 focus-within:ring-signal/15"><span class="grid w-11 shrink-0 place-items-center border-r border-line bg-paper font-display font-bold text-ink-950">S/</span><input id="aplicaciones_{{ $index }}_monto_aplicado" name="aplicaciones[{{ $index }}][monto_aplicado]" data-collection-applied-amount value="{{ $selectedAmount }}" type="number" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 px-3 text-sm font-semibold text-ink-950 outline-none placeholder:text-steel-300"></div>
            @error("aplicaciones.$index.monto_aplicado")
                <p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
            @enderror
            <button type="button" data-use-sale-balance class="mt-2 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:text-ink-950">Usar saldo completo</button>
        </div>
    </div>
</article>
