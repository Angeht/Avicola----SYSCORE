@extends('layouts.app')

@section('title', 'Editar pesaje')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $inputClasses = 'mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-signal focus:ring-2 focus:ring-signal/15';
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    @endphp

    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="edit-weighing-title">
            <a href="{{ route('cargas-proveedor.show', $load) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a la carga</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">{{ $load->numero_carga }} / Pesaje #{{ $weighing->id }}</p>
            <h1 id="edit-weighing-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Editar pesaje</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Corrige únicamente los datos del lote seleccionado. Al guardar se recalcularán el peso neto y el costo total de toda la carga.</p>

            @error('pesaje')<p class="mt-6 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror
            @error('pesajes')<p class="mt-6 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror

            <form method="POST" action="{{ route('cargas-proveedor.pesajes.update', [$load, $weighing]) }}" class="mt-8" data-edit-weighing-form>
                @csrf
                @method('PUT')

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="cantidad_jabas" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Cantidad de jabas</label>
                        <input data-crates id="cantidad_jabas" name="cantidad_jabas" value="{{ old('cantidad_jabas', $weighing->cantidad_jabas) }}" type="number" min="0" max="99999" step="1" inputmode="numeric" required class="{{ $inputClasses }}" @error('cantidad_jabas') aria-invalid="true" aria-describedby="cantidad_jabas-error" @enderror>
                        @error('cantidad_jabas')<p id="cantidad_jabas-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tipo_jaba_id" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Tipo de jaba</label>
                        <select data-crate-type id="tipo_jaba_id" name="tipo_jaba_id" class="{{ $inputClasses }}" @error('tipo_jaba_id') aria-invalid="true" aria-describedby="tipo_jaba_id-error" @enderror>
                            <option value="">Sin jabas</option>
                            @foreach ($crateTypes as $crateType)
                                <option value="{{ $crateType->id }}" data-reference-tare="{{ number_format((float) $crateType->tara_referencial_kg, 3, '.', '') }}" @selected((string) old('tipo_jaba_id', $weighing->tipo_jaba_id) === (string) $crateType->id)>{{ $crateType->nombre }} · ref. {{ $quantity($crateType->tara_referencial_kg, 3) }} kg</option>
                            @endforeach
                        </select>
                        @error('tipo_jaba_id')<p id="tipo_jaba_id-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tara_unitaria_aplicada_kg" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Tara unitaria kg</label>
                        <input data-tare id="tara_unitaria_aplicada_kg" name="tara_unitaria_aplicada_kg" value="{{ old('tara_unitaria_aplicada_kg', $weighing->tara_unitaria_aplicada_kg) }}" type="number" min="0" max="9999999.999" step="0.001" inputmode="decimal" class="{{ $inputClasses }}" @error('tara_unitaria_aplicada_kg') aria-invalid="true" aria-describedby="tara_unitaria_aplicada_kg-error" @enderror>
                        <p class="mt-1 text-xs leading-5 text-steel-500">Cambiar el tipo de jaba carga su tara referencial.</p>
                        @error('tara_unitaria_aplicada_kg')<p id="tara_unitaria_aplicada_kg-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cantidad_pollos" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Cantidad de pollos</label>
                        <input id="cantidad_pollos" name="cantidad_pollos" value="{{ old('cantidad_pollos', $weighing->cantidad_pollos) }}" type="number" min="1" max="9999999" step="1" inputmode="numeric" required class="{{ $inputClasses }}" @error('cantidad_pollos') aria-invalid="true" aria-describedby="cantidad_pollos-error" @enderror>
                        @error('cantidad_pollos')<p id="cantidad_pollos-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="peso_bruto_kg" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Peso bruto kg</label>
                        <input data-gross-weight id="peso_bruto_kg" name="peso_bruto_kg" value="{{ old('peso_bruto_kg', $weighing->peso_bruto_kg) }}" type="number" min="0.001" max="999999999.999" step="0.001" inputmode="decimal" required class="{{ $inputClasses }}" @error('peso_bruto_kg') aria-invalid="true" aria-describedby="peso_bruto_kg-error" @enderror>
                        @error('peso_bruto_kg')<p id="peso_bruto_kg-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2 xl:col-span-1">
                        <label for="observacion" class="font-mono text-[9px] font-semibold tracking-[0.14em] text-ink-700 uppercase">Observación <span class="font-sans font-normal tracking-normal text-steel-500 normal-case">(opcional)</span></label>
                        <input id="observacion" name="observacion" value="{{ old('observacion', $weighing->observacion) }}" type="text" maxlength="255" class="{{ $inputClasses }}" placeholder="Estado del lote o referencia..." @error('observacion') aria-invalid="true" aria-describedby="observacion-error" @enderror>
                        @error('observacion')<p id="observacion-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 grid border-l-4 border-signal bg-signal-soft sm:grid-cols-2">
                    <div class="p-5">
                        <p class="font-mono text-[8px] tracking-wider text-signal uppercase">Tara total corregida</p>
                        <p data-edit-total-tare class="mt-1 font-display text-3xl font-extrabold text-danger">{{ $quantity($weighing->tara_total_kg, 3) }} kg</p>
                    </div>
                    <div class="border-t border-signal/20 p-5 sm:border-t-0 sm:border-l">
                        <p class="font-mono text-[8px] tracking-wider text-signal uppercase">Peso neto corregido</p>
                        <p data-edit-net-weight class="mt-1 font-display text-3xl font-extrabold text-ink-950">{{ $quantity($weighing->peso_neto_kg, 3) }} kg</p>
                    </div>
                </div>

                <div class="mt-7 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('cargas-proveedor.show', $load) }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-signal px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-signal">Guardar corrección</button>
                </div>
            </form>
        </section>

        <aside class="grid content-start gap-6">
            <section class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="edit-authorization-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Autorización vigente</p>
                <h2 id="edit-authorization-title" class="mt-3 font-display text-2xl font-bold uppercase">{{ $administrator->nombreCompleto() }}</h2>
                <p class="mt-2 text-sm text-steel-300">{{ '@'.$administrator->usuario }} autorizó esta edición. La autorización se consumirá al guardar.</p>
            </section>

            <section class="border border-line bg-paper p-5 shadow-panel" aria-labelledby="edit-load-title">
                <p class="font-mono text-[9px] tracking-[0.18em] text-signal uppercase">Carga vinculada</p>
                <h2 id="edit-load-title" class="mt-2 font-display text-xl font-bold text-ink-950 uppercase">{{ $load->numero_carga }}</h2>
                <dl class="mt-5 grid gap-4 border-t border-line pt-4 text-sm">
                    <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Producto</dt><dd class="mt-1 font-semibold text-ink-950">{{ $load->producto->nombre }}</dd></div>
                    <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Proveedor</dt><dd class="mt-1 font-semibold text-ink-950">{{ $load->proveedor->nombre_razon_social }}</dd></div>
                    <div><dt class="font-mono text-[8px] text-steel-500 uppercase">Costo por kg</dt><dd class="mt-1 font-semibold text-ink-950">S/ {{ $quantity($load->costo_kg, 4) }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
