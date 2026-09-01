@extends('layouts.app')

@section('title', 'Restablecer contraseña')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="reset-password-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-danger uppercase">Seguridad / Acción administrativa</p>
            <h1 id="reset-password-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Restablecer clave</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Define una contraseña nueva para <strong class="text-ink-950">{{ $user->nombreCompleto() }}</strong> <span class="font-mono">({{ '@'.$user->usuario }})</span>.</p>

            <form method="POST" action="{{ route('usuarios.password.update', $user) }}" class="mt-8 grid max-w-2xl gap-5">
                @csrf
                @method('PUT')
                @foreach ([['id' => 'password', 'label' => 'Nueva contraseña'], ['id' => 'password_confirmation', 'label' => 'Confirmar contraseña']] as $field)
                    <div>
                        <div class="flex items-center justify-between gap-4"><label for="{{ $field['id'] }}" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">{{ $field['label'] }}</label><button type="button" data-password-toggle aria-controls="{{ $field['id'] }}" aria-pressed="false" class="text-xs font-semibold text-signal underline-offset-4 hover:underline"><span data-show-label>Mostrar</span><span data-hide-label class="hidden">Ocultar</span></button></div>
                        <input id="{{ $field['id'] }}" name="{{ $field['id'] }}" type="password" autocomplete="new-password" required class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-base text-ink-950 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40" @error($field['id']) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @enderror>
                        @error($field['id'])<p id="{{ $field['id'] }}-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <p class="border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-xs leading-5 text-ink-700">La auditoría registrará quién realizó el restablecimiento, pero nunca almacenará la contraseña ni su hash.</p>
                <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between"><a href="{{ route('usuarios.edit', $user) }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a><button type="submit" class="inline-flex min-h-11 items-center justify-center bg-danger px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-danger/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger">Restablecer contraseña</button></div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="reset-rules-title">
            <span class="grid size-12 place-items-center border border-danger/50 bg-danger/10 text-danger"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 5 6v5c0 4.8 2.9 8.3 7 10 4.1-1.7 7-5.2 7-10V6l-7-3Z" /><path d="M9 12h6m-3-3v6" /></svg></span>
            <h2 id="reset-rules-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Acción sensible</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">La nueva clave debe tener 12 caracteres como mínimo, mayúsculas, minúsculas, un número y un símbolo.</p>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5"><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado</dt><dd class="mt-2"><x-status-badge :active="$user->activo" /></dd></div><div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Roles</dt><dd class="mt-1 text-sm text-white">{{ $user->roles->pluck('nombre')->join(' · ') ?: 'Sin roles' }}</dd></div></dl>
        </aside>
    </div>
@endsection
