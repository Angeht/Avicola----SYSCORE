@php
    $inputClasses = 'min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40';
    $isAdministratorRole = $role?->nombre === 'ADMINISTRADOR';
    $selectedPermissionIds = collect(old('permisos', $isAdministratorRole ? $permissionsByArea->flatten()->pluck('id')->all() : ($role?->permisos->pluck('id')->all() ?? [])))->map(fn ($id) => (string) $id);
@endphp

<div class="grid gap-7">
    <x-form-field name="nombre" label="Nombre del rol" hint="Se guardará en mayúsculas" required>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $role?->nombre) }}" maxlength="60" required @readonly($isAdministratorRole) class="{{ $inputClasses }} font-mono uppercase read-only:bg-canvas read-only:text-steel-500" placeholder="Ej. SUPERVISOR DE CAJA" @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
    </x-form-field>

    <x-form-field name="descripcion" label="Descripción" hint="Máximo 255 caracteres">
        <textarea id="descripcion" name="descripcion" rows="3" maxlength="255" class="{{ $inputClasses }} resize-y py-3" placeholder="Responsabilidad operativa del rol" @error('descripcion') aria-invalid="true" aria-describedby="descripcion-error" @enderror>{{ old('descripcion', $role?->descripcion) }}</textarea>
    </x-form-field>

    <fieldset>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div><legend class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Permisos incluidos <span class="text-danger" aria-hidden="true">*</span></legend><p class="mt-2 text-xs leading-5 text-steel-500">Selecciona únicamente las acciones necesarias para este rol.</p></div>
            <span class="font-mono text-[9px] text-steel-500 uppercase">{{ $permissionsByArea->flatten()->count() }} permisos disponibles</span>
        </div>

        @if ($isAdministratorRole)
            <p class="mt-4 border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-sm text-ink-700">El rol ADMINISTRADOR es estructural: siempre conserva todos los permisos actuales y futuros.</p>
        @endif

        <div class="mt-5 grid gap-4 xl:grid-cols-2">
            @foreach ($permissionsByArea as $area => $permissions)
                <section class="border border-line bg-canvas p-4" aria-labelledby="permission-area-{{ $loop->index }}">
                    <div class="flex items-center justify-between gap-3 border-b border-line pb-3"><h2 id="permission-area-{{ $loop->index }}" class="font-display text-base font-bold tracking-wide text-ink-950 uppercase">{{ $area }}</h2><span class="font-mono text-[8px] text-steel-500 uppercase">{{ $permissions->count() }} controles</span></div>
                    <div class="mt-3 grid gap-2">
                        @foreach ($permissions as $permission)
                            @if ($isAdministratorRole)<input type="hidden" name="permisos[]" value="{{ $permission->id }}">@endif
                            <label class="flex cursor-pointer items-start gap-3 border border-transparent bg-white p-3 transition hover:border-steel-300 has-checked:border-signal/40 has-checked:bg-signal-soft/40 {{ $isAdministratorRole ? 'cursor-not-allowed opacity-75' : '' }}">
                                <input name="{{ $isAdministratorRole ? '' : 'permisos[]' }}" type="checkbox" value="{{ $permission->id }}" class="mt-0.5 size-4 shrink-0 accent-signal" @checked($selectedPermissionIds->contains((string) $permission->id)) @disabled($isAdministratorRole)>
                                <span><span class="block text-sm font-semibold text-ink-950">{{ $permission->nombre }}</span><span class="mt-1 block font-mono text-[8px] tracking-wide text-steel-500">{{ $permission->codigo }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        @error('permisos')<p id="permisos-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
        @error('permisos.*')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
    </fieldset>

    <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('roles.index') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-7 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">{{ $submitLabel }}</button>
    </div>
</div>
