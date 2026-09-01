@props([
    'index',
    'weighing' => [],
    'crateTypes',
    'showErrors' => true,
])

@php
    $fieldError = fn (string $field): ?string => $showErrors ? $errors->first("pesajes.$index.$field") : null;
@endphp

<article data-weighing-row class="corner-frame border border-line bg-white shadow-sm">
    <header class="flex items-center justify-between gap-4 border-b border-line bg-canvas px-4 py-3 sm:px-5">
        <div class="flex items-center gap-3">
            <span data-weighing-number class="grid size-8 place-items-center bg-ink-950 font-mono text-[10px] font-bold text-hazard">{{ (int) $index + 1 }}</span>
            <div>
                <p class="font-display text-lg font-bold text-ink-950 uppercase">Lote de pesaje</p>
                <p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Entrada parcial de la carga</p>
            </div>
        </div>
        <button id="pesajes-{{ $index }}-eliminar" type="button" data-remove-weighing class="inline-flex min-h-10 items-center justify-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase transition hover:border-danger hover:bg-danger-soft hover:text-danger" aria-label="Eliminar este pesaje">Eliminar</button>
    </header>

    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-5">
        <div>
            <label data-field-label="cantidad_jabas" for="pesajes-{{ $index }}-cantidad-jabas" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Cantidad de jabas</label>
            <input data-crates id="pesajes-{{ $index }}-cantidad-jabas" name="pesajes[{{ $index }}][cantidad_jabas]" value="{{ $weighing['cantidad_jabas'] ?? 0 }}" type="number" min="0" max="99999" step="1" inputmode="numeric" class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15" required>
            @if ($fieldError('cantidad_jabas'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('cantidad_jabas') }}</p>@endif
        </div>

        <div>
            <label data-field-label="tipo_jaba_id" for="pesajes-{{ $index }}-tipo-jaba" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Tipo de jaba</label>
            <select data-crate-type id="pesajes-{{ $index }}-tipo-jaba" name="pesajes[{{ $index }}][tipo_jaba_id]" class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15">
                <option value="">Sin jabas</option>
                @foreach ($crateTypes as $crateType)
                    <option value="{{ $crateType->id }}" data-reference-tare="{{ number_format((float) $crateType->tara_referencial_kg, 3, '.', '') }}" @selected((string) ($weighing['tipo_jaba_id'] ?? '') === (string) $crateType->id)>{{ $crateType->nombre }} · ref. {{ number_format((float) $crateType->tara_referencial_kg, 3, ',', '.') }} kg</option>
                @endforeach
            </select>
            @if ($fieldError('tipo_jaba_id'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('tipo_jaba_id') }}</p>@endif
        </div>

        <div>
            <label data-field-label="tara_unitaria_aplicada_kg" for="pesajes-{{ $index }}-tara" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Tara unitaria kg</label>
            <input data-tare id="pesajes-{{ $index }}-tara" name="pesajes[{{ $index }}][tara_unitaria_aplicada_kg]" value="{{ $weighing['tara_unitaria_aplicada_kg'] ?? '0.000' }}" type="number" min="0" max="9999999.999" step="0.001" inputmode="decimal" class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15">
            <p class="mt-1 text-xs leading-5 text-steel-500">Se carga desde el tipo de jaba y puedes editarla.</p>
            @if ($fieldError('tara_unitaria_aplicada_kg'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('tara_unitaria_aplicada_kg') }}</p>@endif
        </div>

        <div>
            <label data-field-label="cantidad_pollos" for="pesajes-{{ $index }}-cantidad-pollos" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Cantidad de pollos</label>
            <input data-birds id="pesajes-{{ $index }}-cantidad-pollos" name="pesajes[{{ $index }}][cantidad_pollos]" value="{{ $weighing['cantidad_pollos'] ?? '' }}" type="number" min="1" max="9999999" step="1" inputmode="numeric" placeholder="0" class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm font-semibold text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" required>
            @if ($fieldError('cantidad_pollos'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('cantidad_pollos') }}</p>@endif
        </div>

        <div>
            <label data-field-label="peso_bruto_kg" for="pesajes-{{ $index }}-peso-bruto" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Peso bruto kg</label>
            <input data-gross-weight id="pesajes-{{ $index }}-peso-bruto" name="pesajes[{{ $index }}][peso_bruto_kg]" value="{{ $weighing['peso_bruto_kg'] ?? '' }}" type="number" min="0.001" max="999999999.999" step="0.001" inputmode="decimal" placeholder="0.000" class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm font-semibold text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15" required>
            @if ($fieldError('peso_bruto_kg'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('peso_bruto_kg') }}</p>@endif
        </div>

        <div class="sm:col-span-2 xl:col-span-3">
            <label data-field-label="observacion" for="pesajes-{{ $index }}-observacion" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Observación del pesaje <span class="font-sans font-normal tracking-normal text-steel-500 normal-case">(opcional)</span></label>
            <input id="pesajes-{{ $index }}-observacion" name="pesajes[{{ $index }}][observacion]" value="{{ $weighing['observacion'] ?? '' }}" type="text" maxlength="255" placeholder="Estado del lote, incidencias o referencia..." class="mt-2 min-h-12 w-full border border-line bg-paper px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-signal focus:ring-2 focus:ring-signal/15">
            @if ($fieldError('observacion'))<p class="mt-2 text-sm font-medium text-danger">{{ $fieldError('observacion') }}</p>@endif
        </div>

        <div class="grid border-l-4 border-signal bg-signal-soft sm:col-span-2 sm:grid-cols-2 xl:col-span-2">
            <div class="p-4">
                <p class="font-mono text-[8px] tracking-wider text-signal uppercase">Tara total estimada</p>
                <p id="pesajes-{{ $index }}-tara-total" data-total-tare class="mt-1 font-display text-2xl font-extrabold text-danger">0,000 kg</p>
            </div>
            <div class="border-t border-signal/20 p-4 sm:border-t-0 sm:border-l">
                <p class="font-mono text-[8px] tracking-wider text-signal uppercase">Peso neto estimado</p>
                <p data-net-weight class="mt-1 font-display text-2xl font-extrabold text-ink-950">0,000 kg</p>
            </div>
        </div>
    </div>
</article>
