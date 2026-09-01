@extends('layouts.app')

@section('title', 'Autorizar edición de pesaje')
@section('section', 'Cargas de proveedor')

@section('content')
    @php
        $quantity = fn (float|int|string|null $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    @endphp

    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="authorize-weighing-title">
            <a href="{{ route('cargas-proveedor.show', $load) }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase transition hover:text-ink-950">← Volver a la carga</a>
            <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.22em] text-danger uppercase">Control administrativo / Pesaje #{{ $position }}</p>
            <h1 id="authorize-weighing-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Autorizar edición</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Selecciona al administrador responsable e ingresa su PIN de 4 dígitos. La autorización será válida solo para este pesaje durante {{ $validityMinutes }} minutos.</p>

            @if ($hasActivePayments)
                <p class="mt-6 border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-sm leading-6 text-ink-700">Esta carga tiene pagos vigentes. Puedes corregir el pesaje, pero el nuevo costo total no podrá ser menor que el monto ya pagado.</p>
            @endif

            @error('autorizacion')<p class="mt-6 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</p>@enderror

            @if ($administrators->isEmpty())
                <div class="mt-8 border-l-4 border-hazard bg-hazard-soft p-5">
                    <p class="font-display text-lg font-bold text-ink-950 uppercase">No hay un PIN disponible</p>
                    <p class="mt-2 text-sm leading-6 text-ink-700">Un administrador activo debe configurar primero su PIN desde Usuarios y roles.</p>
                    @if ($pinSetupUser)
                        <a href="{{ route('usuarios.pin-autorizacion.edit', $pinSetupUser) }}" class="mt-4 inline-flex min-h-10 items-center justify-center bg-danger px-5 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-danger/90">Configurar mi PIN ahora</a>
                    @else
                        <p class="mt-3 text-xs leading-5 text-ink-700">Inicia sesión con una cuenta que tenga el rol ADMINISTRADOR para configurarlo.</p>
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('cargas-proveedor.pesajes.autorizacion.store', [$load, $weighing]) }}" class="mt-8 grid max-w-2xl gap-5">
                    @csrf

                    <x-form-field name="administrador_id" label="Administrador que autoriza" required>
                        <select id="administrador_id" name="administrador_id" required class="min-h-12 w-full border border-line bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-danger focus:ring-2 focus:ring-danger/20" @error('administrador_id') aria-invalid="true" aria-describedby="administrador_id-error" @enderror>
                            <option value="">Selecciona un administrador</option>
                            @foreach ($administrators as $administrator)
                                <option value="{{ $administrator->id }}" @selected((string) old('administrador_id') === (string) $administrator->id)>{{ $administrator->nombreCompleto() }} · {{ '@'.$administrator->usuario }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <label for="pin_autorizacion" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">PIN administrativo <span class="text-danger" aria-hidden="true">*</span></label>
                            <span class="font-mono text-[9px] text-steel-500 uppercase">4 dígitos</span>
                        </div>
                        <input id="pin_autorizacion" name="pin_autorizacion" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" data-digits-only required autofocus class="mt-2 min-h-14 w-full border border-line bg-white px-4 text-center font-mono text-2xl tracking-[0.55em] text-ink-950 outline-none transition focus:border-danger focus:ring-2 focus:ring-danger/20" @error('pin_autorizacion') aria-invalid="true" aria-describedby="pin_autorizacion-error" @enderror>
                        @error('pin_autorizacion')<p id="pin_autorizacion-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('cargas-proveedor.show', $load) }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-danger px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-danger/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger">Validar y continuar</button>
                    </div>
                </form>
            @endif
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="selected-weighing-title">
            <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">{{ $load->numero_carga }}</p>
            <h2 id="selected-weighing-title" class="mt-4 font-display text-2xl font-bold tracking-wide uppercase">Pesaje seleccionado</h2>
            <p class="mt-2 text-sm text-steel-300">{{ $load->producto->nombre }} · {{ $load->proveedor->nombre_razon_social }}</p>
            <dl class="mt-7 grid grid-cols-2 gap-px border border-white/10 bg-white/10">
                <div class="bg-ink-950 p-4"><dt class="font-mono text-[8px] text-steel-500 uppercase">Aves</dt><dd class="mt-2 font-display text-2xl font-bold">{{ $quantity($weighing->cantidad_pollos) }}</dd></div>
                <div class="bg-ink-950 p-4"><dt class="font-mono text-[8px] text-steel-500 uppercase">Jabas</dt><dd class="mt-2 font-display text-2xl font-bold">{{ $quantity($weighing->cantidad_jabas) }}</dd></div>
                <div class="bg-ink-950 p-4"><dt class="font-mono text-[8px] text-steel-500 uppercase">Peso bruto</dt><dd class="mt-2 font-display text-xl font-bold">{{ $quantity($weighing->peso_bruto_kg, 3) }} kg</dd></div>
                <div class="bg-ink-950 p-4"><dt class="font-mono text-[8px] text-hazard uppercase">Peso neto</dt><dd class="mt-2 font-display text-xl font-bold text-hazard">{{ $quantity($weighing->peso_neto_kg, 3) }} kg</dd></div>
            </dl>
            <p class="mt-5 text-xs leading-5 text-steel-300">{{ $weighing->tipoJaba?->nombre ?? 'Sin jabas' }}{{ $weighing->observacion ? ' · '.$weighing->observacion : '' }}</p>
        </aside>
    </div>
@endsection
