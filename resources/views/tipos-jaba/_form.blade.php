@php
    $inputClasses = 'min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40';
@endphp

<div class="grid gap-6">
    <x-form-field name="nombre" label="Nombre del tipo de jaba" hint="Se guardará en mayúsculas" required>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $crateType?->nombre) }}" maxlength="80" required class="{{ $inputClasses }} uppercase" placeholder="Ej. JABA GRANDE AZUL" @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
    </x-form-field>

    <x-form-field name="tara_referencial_kg" label="Tara referencial" hint="Kilogramos, hasta 3 decimales" required>
        <div class="relative">
            <input id="tara_referencial_kg" name="tara_referencial_kg" type="number" value="{{ old('tara_referencial_kg', $crateType?->tara_referencial_kg) }}" min="0.001" max="9999999.999" step="0.001" inputmode="decimal" required class="{{ $inputClasses }} pr-14 font-mono text-lg font-semibold" placeholder="0.000" @error('tara_referencial_kg') aria-invalid="true" aria-describedby="tara_referencial_kg-error" @enderror>
            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 font-mono text-[10px] font-semibold text-steel-500 uppercase">kg</span>
        </div>
    </x-form-field>

    <x-form-field name="descripcion" label="Descripción" hint="Máximo 255 caracteres">
        <textarea id="descripcion" name="descripcion" rows="4" maxlength="255" class="{{ $inputClasses }} resize-y py-3" placeholder="Color, tamaño, material o uso de la jaba" @error('descripcion') aria-invalid="true" aria-describedby="descripcion-error" @enderror>{{ old('descripcion', $crateType?->descripcion) }}</textarea>
    </x-form-field>

    <div class="border-l-4 border-hazard bg-hazard-soft px-4 py-3">
        <p class="font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Regla de pesaje</p>
        <p class="mt-1 text-xs leading-5 text-ink-700">La tara debe ser mayor que cero. El sistema la descontará del peso bruto cuando la operación use este tipo de jaba.</p>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('tipos-jaba.index') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">{{ $submitLabel }}</button>
    </div>
</div>
