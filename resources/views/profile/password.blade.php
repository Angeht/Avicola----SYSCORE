@extends('layouts.app')

@section('title', 'Seguridad')
@section('section', 'Seguridad de la cuenta')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="password-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-signal uppercase">Perfil / Seguridad</p>
            <h1 id="password-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">
                Cambiar contraseña
            </h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">
                Confirma tu contraseña actual y define una nueva clave para proteger el acceso a la operación.
            </p>

            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-8 grid max-w-2xl gap-5">
                @csrf
                @method('PUT')

                @php
                    $fields = [
                        ['id' => 'current_password', 'label' => 'Contraseña actual', 'autocomplete' => 'current-password'],
                        ['id' => 'password', 'label' => 'Nueva contraseña', 'autocomplete' => 'new-password'],
                        ['id' => 'password_confirmation', 'label' => 'Confirmar nueva contraseña', 'autocomplete' => 'new-password'],
                    ];
                @endphp

                @foreach ($fields as $field)
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <label for="{{ $field['id'] }}" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">
                                {{ $field['label'] }}
                            </label>
                            <button type="button" data-password-toggle aria-controls="{{ $field['id'] }}" aria-pressed="false" class="text-xs font-semibold text-signal underline-offset-4 hover:underline">
                                <span data-show-label>Mostrar</span>
                                <span data-hide-label class="hidden">Ocultar</span>
                            </button>
                        </div>
                        <input
                            id="{{ $field['id'] }}"
                            name="{{ $field['id'] }}"
                            type="password"
                            autocomplete="{{ $field['autocomplete'] }}"
                            required
                            class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-base text-ink-950 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"
                            @error($field['id']) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @enderror
                        >
                        @error($field['id'])
                            <p id="{{ $field['id'] }}-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div class="mt-2 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:text-ink-950">
                        Volver al panel
                    </a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="password-rules-title">
            <span class="grid size-12 place-items-center border border-hazard/40 bg-hazard/10 text-hazard">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 4.8 2.9 8.3 7 10 4.1-1.7 7-5.2 7-10V6l-7-3Z" />
                    <path d="m9.5 12 1.7 1.7 3.6-4" />
                </svg>
            </span>
            <h2 id="password-rules-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Clave resistente</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">La nueva contraseña debe incluir:</p>
            <ul class="mt-5 grid gap-3 text-sm text-white">
                @foreach (['12 caracteres como mínimo', 'Letras mayúsculas y minúsculas', 'Al menos un número', 'Al menos un símbolo'] as $rule)
                    <li class="flex items-center gap-3 border-b border-white/10 pb-3">
                        <span class="size-2 shrink-0 bg-hazard"></span>
                        {{ $rule }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-7 font-mono text-[9px] leading-5 tracking-wider text-steel-300 uppercase">
                Recomendación: no reutilices una clave de otro servicio.
            </p>
        </aside>
    </div>
@endsection
