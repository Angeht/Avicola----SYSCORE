@php
    $inputClasses = 'min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40';
    $selectedRoleIds = collect(old('roles', $user?->roles->pluck('id')->all() ?? []))->map(fn ($id) => (string) $id);
@endphp

<div class="grid gap-7">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form-field name="nombres" label="Nombres" required>
            <input id="nombres" name="nombres" type="text" value="{{ old('nombres', $user?->nombres) }}" maxlength="100" autocomplete="given-name" required class="{{ $inputClasses }}" placeholder="Nombres del colaborador" @error('nombres') aria-invalid="true" aria-describedby="nombres-error" @enderror>
        </x-form-field>

        <x-form-field name="apellidos" label="Apellidos" required>
            <input id="apellidos" name="apellidos" type="text" value="{{ old('apellidos', $user?->apellidos) }}" maxlength="100" autocomplete="family-name" required class="{{ $inputClasses }}" placeholder="Apellidos del colaborador" @error('apellidos') aria-invalid="true" aria-describedby="apellidos-error" @enderror>
        </x-form-field>
    </div>

    <x-form-field name="usuario" label="Usuario de acceso" hint="Letras, números, punto, guion o guion bajo" required>
        <input id="usuario" name="usuario" type="text" value="{{ old('usuario', $user?->usuario) }}" maxlength="80" autocomplete="username" required class="{{ $inputClasses }} font-mono lowercase" placeholder="ej. maria.operaciones" @error('usuario') aria-invalid="true" aria-describedby="usuario-error" @enderror>
    </x-form-field>

    @if ($user === null)
        <div class="grid gap-5 border-y border-line bg-canvas px-4 py-5 sm:grid-cols-2">
            <div>
                <div class="flex items-end justify-between gap-4">
                    <label for="password" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Contraseña inicial <span class="text-danger" aria-hidden="true">*</span></label>
                    <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" class="text-xs font-semibold text-signal underline-offset-4 hover:underline"><span data-show-label>Mostrar</span><span data-hide-label class="hidden">Ocultar</span></button>
                </div>
                <input id="password" name="password" type="password" autocomplete="new-password" required class="{{ $inputClasses }} mt-2" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p id="password-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex items-end justify-between gap-4">
                    <label for="password_confirmation" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Confirmar contraseña <span class="text-danger" aria-hidden="true">*</span></label>
                    <button type="button" data-password-toggle aria-controls="password_confirmation" aria-pressed="false" class="text-xs font-semibold text-signal underline-offset-4 hover:underline"><span data-show-label>Mostrar</span><span data-hide-label class="hidden">Ocultar</span></button>
                </div>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="{{ $inputClasses }} mt-2">
            </div>
            <p class="text-xs leading-5 text-steel-500 sm:col-span-2">Mínimo 12 caracteres, con mayúsculas, minúsculas, número y símbolo.</p>
        </div>
    @endif

    <fieldset>
        <legend class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Roles asignados <span class="text-danger" aria-hidden="true">*</span></legend>
        <p class="mt-2 text-xs leading-5 text-steel-500">Los permisos efectivos son la suma de los roles activos seleccionados.</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($roles as $role)
                <label class="group flex min-h-24 cursor-pointer items-start gap-4 border border-line bg-white p-4 transition hover:border-ink-950 has-checked:border-signal has-checked:bg-signal-soft/35">
                    <input name="roles[]" type="checkbox" value="{{ $role->id }}" class="mt-1 size-5 shrink-0 accent-signal" @checked($selectedRoleIds->contains((string) $role->id))>
                    <span>
                        <span class="block font-display text-sm font-bold tracking-wide text-ink-950 uppercase">{{ $role->nombre }}</span>
                        <span class="mt-1 block text-xs leading-5 text-steel-500">{{ $role->descripcion ?: 'Sin descripción operativa.' }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('roles')<p id="roles-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        @error('roles.*')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
    </fieldset>

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('usuarios.index') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">{{ $submitLabel }}</button>
    </div>
</div>
