@props([
    'detailIndex',
    'detail' => [],
    'priceOptions',
    'crateTypes',
    'canEditPrice' => false,
    'showErrors' => true,
])

@php
    $defaultWeighing = ['tipo_jaba_id' => null, 'cantidad_jabas' => 0, 'cantidad_pollos' => '', 'peso_bruto_kg' => '', 'tara_unitaria_aplicada_kg' => '0.000', 'observacion' => null];
    $weighings = $detail['pesajes'] ?? [$defaultWeighing];
    $fieldError = fn (string $field): ?string => $showErrors ? $errors->first("detalles.$detailIndex.$field") : null;
@endphp

<article data-sale-detail class="corner-frame overflow-hidden border border-line bg-canvas shadow-sm">
    <header class="flex flex-col gap-4 border-b border-line bg-ink-950 px-5 py-4 text-white sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3"><span data-sale-detail-number class="grid size-9 place-items-center bg-hazard font-mono text-[10px] font-bold text-ink-950">{{ is_numeric($detailIndex) ? (int) $detailIndex + 1 : 1 }}</span><div><p class="font-display text-lg font-bold uppercase">Producto de venta</p><p class="font-mono text-[8px] tracking-wider text-steel-300 uppercase">Precio + pesajes</p></div></div>
        <button type="button" data-remove-sale-detail class="inline-flex min-h-9 items-center justify-center border border-white/15 px-3 font-mono text-[8px] font-semibold tracking-wider text-steel-300 uppercase transition hover:border-danger hover:bg-danger hover:text-white disabled:opacity-30">Quitar producto</button>
    </header>

    <div class="grid gap-5 border-b border-line bg-paper p-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(180px,0.6fr)_minmax(0,1fr)]">
        <div><label for="detalles-{{ $detailIndex }}-precio-version" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Producto con precio vigente</label><select data-sale-product id="detalles-{{ $detailIndex }}-precio-version" name="detalles[{{ $detailIndex }}][precio_version_id]" class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none focus:border-signal" required><option value="">Selecciona un producto</option>@foreach ($priceOptions as $option)<option value="{{ $option->precio_version_id }}" data-product-id="{{ $option->producto_id }}" data-product="{{ $option->producto }}" data-price="{{ $option->precio_kg }}" data-sale-mode="{{ $option->modalidad_venta }}" data-birds="{{ $option->pollos_disponibles }}" data-kilograms="{{ $option->kg_disponibles }}" @selected((string) ($detail['precio_version_id'] ?? '') === (string) $option->precio_version_id)>{{ $option->producto }} · {{ $option->modalidad_venta === \App\Models\Producto::MODALIDAD_SOLO_PESO ? 'solo kg' : 'pesaje vivo' }} · S/ {{ number_format((float) $option->precio_kg, 4, ',', '.') }}/kg</option>@endforeach</select>@if ($fieldError('precio_version_id'))<p class="mt-2 text-xs font-medium text-danger">{{ $fieldError('precio_version_id') }}</p>@endif<p data-sale-stock class="mt-2 text-xs text-steel-500">Selecciona un producto para ver su disponibilidad.</p></div>
        <div><label for="detalles-{{ $detailIndex }}-precio-aplicado" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Precio aplicado / kg</label><div class="mt-2 flex min-h-12 border border-line bg-white"><span class="grid w-11 place-items-center border-r border-line font-display font-bold">S/</span><input data-sale-price id="detalles-{{ $detailIndex }}-precio-aplicado" name="detalles[{{ $detailIndex }}][precio_aplicado_kg]" value="{{ $detail['precio_aplicado_kg'] ?? '' }}" type="number" min="0.0001" max="99999999.9999" step="0.0001" class="min-w-0 flex-1 px-3 text-sm font-semibold text-ink-950 outline-none disabled:bg-steel-300/20" required @readonly(! $canEditPrice)></div>@if ($fieldError('precio_aplicado_kg'))<p class="mt-2 text-xs font-medium text-danger">{{ $fieldError('precio_aplicado_kg') }}</p>@endif @if (! $canEditPrice)<p class="mt-2 text-xs text-steel-500">Se aplicará el precio vigente.</p>@endif</div>
        <div data-price-reason-panel><label for="detalles-{{ $detailIndex }}-motivo" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Motivo del ajuste</label><input data-sale-price-reason id="detalles-{{ $detailIndex }}-motivo" name="detalles[{{ $detailIndex }}][motivo_ajuste_precio]" value="{{ $detail['motivo_ajuste_precio'] ?? '' }}" type="text" maxlength="255" placeholder="Obligatorio si cambia el precio..." class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none placeholder:text-steel-300 focus:border-signal" @disabled(! $canEditPrice)>@if ($fieldError('motivo_ajuste_precio'))<p class="mt-2 text-xs font-medium text-danger">{{ $fieldError('motivo_ajuste_precio') }}</p>@endif</div>
    </div>

    @if ($showErrors && $errors->has("detalles.$detailIndex.pesajes"))<p class="mx-5 mt-4 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $errors->first("detalles.$detailIndex.pesajes") }}</p>@endif
    <div data-sale-weighings class="grid gap-4 p-5">@foreach ($weighings as $weighingIndex => $weighing)<x-sale-weighing-row :detail-index="$detailIndex" :index="$weighingIndex" :weighing="$weighing" :crate-types="$crateTypes" :show-errors="$showErrors" />@endforeach</div>
    <template data-sale-weighing-template><x-sale-weighing-row :detail-index="$detailIndex" index="__WEIGHING__" :weighing="[]" :crate-types="$crateTypes" :show-errors="false" /></template>

    <footer class="grid gap-px border-t border-line bg-line sm:grid-cols-[auto_1fr_auto_auto]">
        <button type="button" data-add-sale-weighing class="min-h-14 bg-hazard px-5 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-hazard-soft">+ Agregar pesaje</button>
        <div class="bg-paper p-4 sm:px-5"><p data-detail-birds-label class="font-mono text-[8px] text-steel-500 uppercase">Aves del producto</p><p data-detail-birds class="mt-1 font-display text-xl font-bold text-ink-950">0</p></div>
        <div class="bg-paper p-4 sm:px-5"><p class="font-mono text-[8px] text-steel-500 uppercase">Peso neto</p><p data-detail-kilograms class="mt-1 font-display text-xl font-bold text-ink-950">0,000 kg</p></div>
        <div class="bg-ink-950 p-4 text-white sm:px-5"><p class="font-mono text-[8px] text-hazard uppercase">Subtotal</p><p data-detail-total class="mt-1 font-display text-xl font-bold">S/ 0,00</p></div>
    </footer>
</article>
