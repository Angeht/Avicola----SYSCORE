@extends('layouts.app')

@section('title', 'Configuración de la empresa')
@section('section', 'Configuración general')

@section('content')
    @php
        $inputClasses = 'min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40';
        $previewName = old('nombre_comercial', $company->nombre_comercial) ?: 'NOMBRE COMERCIAL';
        $previewLegalName = old('razon_social', $company->razon_social) ?: 'RAZÓN SOCIAL';
        $previewDocument = old('nro_documento', $company->nro_documento);
        $previewAddress = old('direccion', $company->direccion);
        $previewPhone = old('telefono', $company->telefono);
        $previewMessage = old('mensaje_ticket', $company->mensaje_ticket) ?: 'Gracias por su compra.';
    @endphp

    <header class="reveal-up border-b-2 border-ink-950 pb-6">
        <div>
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Administración / Identidad</p>
            <h1 class="mt-3 max-w-4xl font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Configuración de la empresa</h1>
            <p class="mt-4 max-w-3xl text-sm leading-6 text-steel-500">Estos datos identifican el negocio en el sistema y forman el encabezado de los comprobantes.</p>
        </div>
    </header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="company-form-title">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-[9px] tracking-[0.18em] text-signal uppercase">Ficha maestra / Registro único</p>
                    <h2 id="company-form-title" class="mt-2 font-display text-2xl font-bold text-ink-950 uppercase">Datos legales y comerciales</h2>
                </div>
                @if (str_contains($company->razon_social ?? '', 'CONFIGURAR'))
                    <span class="border border-hazard/50 bg-hazard-soft px-3 py-1.5 font-mono text-[8px] font-semibold tracking-wider text-ink-950 uppercase">Configuración pendiente</span>
                @else
                    <span class="border border-signal/30 bg-signal-soft px-3 py-1.5 font-mono text-[8px] font-semibold tracking-wider text-signal uppercase">Datos configurados</span>
                @endif
            </div>

            <form method="POST" action="{{ route('configuracion.update') }}" class="mt-8">
                @csrf
                @method('PUT')

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-form-field name="razon_social" label="Razón social" hint="Se guardará en mayúsculas" required class="sm:col-span-2">
                        <input id="razon_social" name="razon_social" type="text" value="{{ old('razon_social', $company->razon_social) }}" maxlength="150" required class="{{ $inputClasses }} uppercase" placeholder="Ej. AVÍCOLA SAN MIGUEL S.A.C." @error('razon_social') aria-invalid="true" aria-describedby="razon_social-error" @enderror>
                    </x-form-field>

                    <x-form-field name="nombre_comercial" label="Nombre comercial" hint="Visible en el menú y comprobantes" required class="sm:col-span-2">
                        <input id="nombre_comercial" name="nombre_comercial" type="text" value="{{ old('nombre_comercial', $company->nombre_comercial) }}" maxlength="150" required class="{{ $inputClasses }} uppercase" placeholder="Ej. AVÍCOLA SAN MIGUEL" @error('nombre_comercial') aria-invalid="true" aria-describedby="nombre_comercial-error" @enderror>
                    </x-form-field>

                    <x-form-field name="tipo_documento_id" label="Tipo de documento">
                        <div class="relative grid">
                            <select id="tipo_documento_id" name="tipo_documento_id" class="{{ $inputClasses }} col-start-1 row-start-1 appearance-none pr-10" @error('tipo_documento_id') aria-invalid="true" aria-describedby="tipo_documento_id-error" @enderror>
                                <option value="">Sin documento</option>
                                @foreach ($documentTypes as $documentType)
                                    <option value="{{ $documentType->id }}" @selected((string) old('tipo_documento_id', $company->tipo_documento_id) === (string) $documentType->id)>{{ $documentType->codigo }} · {{ $documentType->nombre }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none col-start-1 row-start-1 mr-4 size-4 self-center justify-self-end text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
                        </div>
                    </x-form-field>

                    <x-form-field name="nro_documento" label="Número de documento" hint="RUC, DNI u otro">
                        <input id="nro_documento" name="nro_documento" type="text" value="{{ old('nro_documento', $company->nro_documento) }}" maxlength="20" class="{{ $inputClasses }} uppercase" placeholder="Ej. 20123456789" @error('nro_documento') aria-invalid="true" aria-describedby="nro_documento-error" @enderror>
                    </x-form-field>

                    <x-form-field name="direccion" label="Dirección" class="sm:col-span-2">
                        <input id="direccion" name="direccion" type="text" value="{{ old('direccion', $company->direccion) }}" maxlength="255" class="{{ $inputClasses }}" placeholder="Dirección fiscal o comercial" @error('direccion') aria-invalid="true" aria-describedby="direccion-error" @enderror>
                    </x-form-field>

                    <x-form-field name="telefono" label="Teléfono">
                        <input id="telefono" name="telefono" type="tel" value="{{ old('telefono', $company->telefono) }}" maxlength="9" inputmode="numeric" pattern="[0-9]{9}" data-digits-only class="{{ $inputClasses }}" placeholder="Ej. 999999999" @error('telefono') aria-invalid="true" aria-describedby="telefono-error" @enderror>
                    </x-form-field>

                    <x-form-field name="mensaje_ticket" label="Mensaje del comprobante" hint="Máximo 255 caracteres" class="sm:col-span-2">
                        <textarea id="mensaje_ticket" name="mensaje_ticket" rows="4" maxlength="255" class="{{ $inputClasses }} resize-y py-3" placeholder="Ej. Gracias por su compra." @error('mensaje_ticket') aria-invalid="true" aria-describedby="mensaje_ticket-error" @enderror>{{ old('mensaje_ticket', $company->mensaje_ticket) }}</textarea>
                    </x-form-field>
                </div>

                <div class="mt-7 flex justify-end border-t border-line pt-6">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">Guardar configuración</button>
                </div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="ticket-preview-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Vista previa / Comprobante</p>
            <h2 id="ticket-preview-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Identidad impresa</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">La vista muestra los datos guardados o recuperados después de una validación.</p>

            <div class="mt-7 bg-white p-6 text-center text-ink-950 shadow-lg">
                <p class="font-display text-xl font-extrabold uppercase">{{ $previewName }}</p>
                <p class="mt-1 text-[11px] font-semibold">{{ $previewLegalName }}</p>
                @if ($previewDocument)<p class="mt-2 font-mono text-[9px]">DOC. {{ $previewDocument }}</p>@endif
                @if ($previewAddress)<p class="mt-2 text-[10px] leading-4">{{ $previewAddress }}</p>@endif
                @if ($previewPhone)<p class="text-[10px]">TEL. {{ $previewPhone }}</p>@endif
                <div class="my-5 border-t border-dashed border-steel-300"></div>
                <p class="text-xs font-medium">{{ $previewMessage }}</p>
            </div>

            <div class="mt-6 border-l-2 border-hazard pl-4">
                <p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Importante</p>
                <p class="mt-2 text-xs leading-5 text-steel-300">Completa el documento y la dirección antes de emitir comprobantes reales.</p>
            </div>
        </aside>
    </div>
@endsection
