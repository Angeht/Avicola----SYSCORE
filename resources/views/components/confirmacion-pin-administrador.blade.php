@props([
    'administrators',
    'pinSetupUser' => null,
    'operation',
])

@php
    $selectedAdministratorId = old(
        'administrador_id',
        $administrators->count() === 1 ? $administrators->first()->getKey() : null,
    );
@endphp

<section {{ $attributes->class(['border-l-4 border-danger bg-danger-soft p-5']) }} aria-labelledby="administrator-pin-title">
    <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-danger uppercase">Control administrativo</p>
    <h2 id="administrator-pin-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Autorizar {{ $operation }}</h2>
    <p class="mt-2 text-xs leading-5 text-ink-700">Selecciona al administrador responsable e ingresa su PIN de 4 dígitos. El PIN no se guarda en el formulario ni en la auditoría.</p>

    @if ($administrators->isEmpty())
        <div class="mt-5 border border-danger/25 bg-white p-4">
            <p class="font-semibold text-ink-950">No hay un PIN administrativo disponible.</p>
            <p class="mt-1 text-xs leading-5 text-steel-500">Un administrador activo debe configurar su PIN desde Usuarios y roles.</p>
            @if ($pinSetupUser)
                <a href="{{ route('usuarios.pin-autorizacion.edit', $pinSetupUser) }}" class="mt-3 inline-flex min-h-10 items-center justify-center bg-danger px-4 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-danger/90">Configurar mi PIN</a>
            @endif
        </div>
    @else
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <x-form-field name="administrador_id" label="Administrador que autoriza" required>
                <select id="administrador_id" name="administrador_id" required class="min-h-12 w-full border border-danger/30 bg-white px-4 text-sm font-semibold text-ink-950 outline-none transition focus:border-danger focus:ring-2 focus:ring-danger/20" @error('administrador_id') aria-invalid="true" aria-describedby="administrador_id-error" @enderror>
                    <option value="">Selecciona un administrador</option>
                    @foreach ($administrators as $administrator)
                        <option value="{{ $administrator->getKey() }}" @selected((string) $selectedAdministratorId === (string) $administrator->getKey())>{{ $administrator->nombreCompleto() }} · {{ '@'.$administrator->usuario }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field name="pin_autorizacion" label="PIN administrativo" hint="4 dígitos" required>
                <input id="pin_autorizacion" name="pin_autorizacion" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" data-digits-only required class="min-h-12 w-full border border-danger/30 bg-white px-4 text-center font-mono text-xl tracking-[0.45em] text-ink-950 outline-none transition focus:border-danger focus:ring-2 focus:ring-danger/20" @error('pin_autorizacion') aria-invalid="true" aria-describedby="pin_autorizacion-error" @enderror>
            </x-form-field>
        </div>
    @endif
</section>
