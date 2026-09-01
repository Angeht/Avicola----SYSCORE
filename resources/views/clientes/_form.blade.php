@php
    $inputClasses = 'min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40';
@endphp

<div class="grid gap-6">
    <x-form-field name="nombres_razon_social" label="Nombre o razón social" required>
        <input id="nombres_razon_social" name="nombres_razon_social" type="text" value="{{ old('nombres_razon_social', $client?->nombres_razon_social) }}" maxlength="150" autocomplete="organization" required class="{{ $inputClasses }}" placeholder="Nombre completo o empresa" @error('nombres_razon_social') aria-invalid="true" aria-describedby="nombres_razon_social-error" @enderror>
    </x-form-field>

    <div class="grid gap-5 sm:grid-cols-[220px_minmax(0,1fr)]">
        <x-form-field name="tipo_documento_id" label="Tipo de documento" hint="Opcional">
            <div class="relative grid">
                <select id="tipo_documento_id" name="tipo_documento_id" data-document-type class="{{ $inputClasses }} col-start-1 row-start-1 appearance-none pr-10" @error('tipo_documento_id') aria-invalid="true" aria-describedby="tipo_documento_id-error" @enderror>
                    <option value="">Sin documento</option>
                    @foreach ($documentTypes as $documentType)
                        <option value="{{ $documentType->id }}" data-max="{{ $documentType->longitud_maxima }}" @selected((string) old('tipo_documento_id', $client?->tipo_documento_id) === (string) $documentType->id)>
                            {{ $documentType->codigo }} · {{ $documentType->nombre }}
                        </option>
                    @endforeach
                </select>
                <svg class="pointer-events-none col-start-1 row-start-1 mr-4 size-4 self-center justify-self-end text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
            </div>
        </x-form-field>

        <x-form-field name="nro_documento" label="Número de documento" hint="Según el tipo seleccionado">
            <input id="nro_documento" name="nro_documento" type="text" value="{{ old('nro_documento', $client?->nro_documento) }}" maxlength="20" data-document-number class="{{ $inputClasses }} font-mono uppercase" placeholder="Número de identificación" @error('nro_documento') aria-invalid="true" aria-describedby="nro_documento-error" @enderror>
        </x-form-field>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-form-field name="telefono" label="Teléfono" hint="Opcional · 9 dígitos">
            <input id="telefono" name="telefono" type="tel" value="{{ old('telefono', $client?->telefono) }}" maxlength="9" inputmode="numeric" pattern="[0-9]{9}" data-digits-only autocomplete="tel" class="{{ $inputClasses }}" placeholder="Ej. 999999999" @error('telefono') aria-invalid="true" aria-describedby="telefono-error" @enderror>
        </x-form-field>

        <x-form-field name="direccion" label="Dirección" hint="Opcional">
            <input id="direccion" name="direccion" type="text" value="{{ old('direccion', $client?->direccion) }}" maxlength="255" autocomplete="street-address" class="{{ $inputClasses }}" placeholder="Dirección de entrega o referencia" @error('direccion') aria-invalid="true" aria-describedby="direccion-error" @enderror>
        </x-form-field>
    </div>

    <x-form-field name="observacion" label="Observación" hint="Máximo 255 caracteres">
        <textarea id="observacion" name="observacion" rows="3" maxlength="255" class="{{ $inputClasses }} resize-y py-3" placeholder="Condiciones comerciales, referencias u otra nota" @error('observacion') aria-invalid="true" aria-describedby="observacion-error" @enderror>{{ old('observacion', $client?->observacion) }}</textarea>
    </x-form-field>

    <label class="flex cursor-pointer items-center justify-between gap-4 border border-line bg-canvas px-4 py-3">
        <span><span class="block text-sm font-semibold text-ink-950">Cliente activo</span><span class="mt-0.5 block text-xs text-steel-500">Disponible para nuevas operaciones comerciales.</span></span>
        <input type="hidden" name="activo" value="0">
        <input name="activo" type="checkbox" value="1" class="size-5 accent-signal" @checked((bool) old('activo', $client?->activo ?? true))>
    </label>

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('clientes.index') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">{{ $submitLabel }}</button>
    </div>
</div>
