@extends('layouts.app')

@section('title', 'PIN administrativo')
@section('section', 'Usuarios y roles')

@section('content')
    <div class="reveal-up grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel-cut corner-frame border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="authorization-pin-title">
            <p class="font-mono text-[10px] font-semibold tracking-[0.22em] text-danger uppercase">Seguridad / Autorización reforzada</p>
            <h1 id="authorization-pin-title" class="mt-3 font-display text-4xl font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">PIN administrativo</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Define el PIN de 4 dígitos que <strong class="text-ink-950">{{ $user->nombreCompleto() }}</strong> usará para autorizar la edición de pesajes.</p>

            <form method="POST" action="{{ route('usuarios.pin-autorizacion.update', $user) }}" class="mt-8 grid max-w-2xl gap-5">
                @csrf
                @method('PUT')

                @foreach ([['id' => 'pin_autorizacion', 'label' => 'Nuevo PIN'], ['id' => 'pin_autorizacion_confirmation', 'label' => 'Confirmar PIN']] as $field)
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <label for="{{ $field['id'] }}" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">{{ $field['label'] }}</label>
                            <span class="font-mono text-[9px] text-steel-500 uppercase">4 dígitos</span>
                        </div>
                        <input id="{{ $field['id'] }}" name="{{ $field['id'] }}" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" data-digits-only required class="mt-2 min-h-14 w-full border border-line bg-white px-4 text-center font-mono text-2xl tracking-[0.55em] text-ink-950 outline-none transition focus:border-danger focus:ring-2 focus:ring-danger/20" @error($field['id']) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @enderror>
                        @error($field['id'])<p id="{{ $field['id'] }}-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <p class="border-l-4 border-hazard bg-hazard-soft px-4 py-3 text-xs leading-5 text-ink-700">El PIN se almacena protegido y nunca aparecerá en pantalla ni en la auditoría. Compártelo únicamente con el administrador titular.</p>

                <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('usuarios.edit', $user) }}" class="inline-flex min-h-11 items-center justify-center border border-line px-5 text-xs font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Cancelar</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-danger px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-danger/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger">Guardar PIN</button>
                </div>
            </form>
        </section>

        <aside class="industrial-hatch panel-cut bg-ink-950 p-6 text-white shadow-panel sm:p-8" aria-labelledby="authorization-pin-rules-title">
            <span class="grid size-12 place-items-center border border-danger/50 bg-danger/10 text-danger"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 5 6v5c0 4.8 2.9 8.3 7 10 4.1-1.7 7-5.2 7-10V6l-7-3Z" /><path d="M9.5 12.5 11 14l3.5-4" /></svg></span>
            <h2 id="authorization-pin-rules-title" class="mt-6 font-display text-2xl font-bold tracking-wide uppercase">Firma de administrador</h2>
            <p class="mt-3 text-sm leading-6 text-steel-300">Este PIN no reemplaza la contraseña de acceso. Solo confirma acciones operativas sensibles y está protegido contra intentos repetidos.</p>
            <dl class="mt-7 grid gap-4 border-t border-white/10 pt-5">
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Administrador</dt><dd class="mt-1 text-sm text-white">{{ $user->nombreCompleto() }} · {{ '@'.$user->usuario }}</dd></div>
                <div><dt class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Estado del PIN</dt><dd class="mt-2"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ filled($user->pin_autorizacion_hash) ? 'border-signal/30 bg-signal/10 text-signal' : 'border-hazard/40 bg-hazard/10 text-hazard' }}">{{ filled($user->pin_autorizacion_hash) ? 'Configurado' : 'Pendiente' }}</span></dd></div>
            </dl>
        </aside>
    </div>
@endsection
