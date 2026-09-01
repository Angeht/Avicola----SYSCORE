@extends('layouts.guest')

@section('title', 'Acceso')

@section('content')
    <section class="panel-cut corner-frame reveal-up border border-line bg-paper p-6 shadow-panel sm:p-8" aria-labelledby="login-title">
        <div class="flex items-start justify-between gap-6">
            <div>
                <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Estación / Acceso</p>
                <h1 id="login-title" class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">
                    Inicia turno
                </h1>
            </div>
            <span class="grid size-11 shrink-0 place-items-center border border-hazard bg-hazard-soft font-mono text-xs font-bold text-ink-950">01</span>
        </div>

        <p class="mt-5 max-w-sm text-sm leading-6 text-steel-500">
            Ingresa con tu usuario operativo. El acceso se controla según el rol y los permisos asignados.
        </p>

        @if ($errors->any())
            <div class="mt-6 border-l-4 border-danger bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">
                Revisa los datos de acceso e inténtalo nuevamente.
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-7 grid gap-5">
            @csrf

            <div>
                <label for="usuario" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Usuario</label>
                <input
                    id="usuario"
                    name="usuario"
                    type="text"
                    value="{{ old('usuario') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-base text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"
                    placeholder="Ej. admin"
                    @error('usuario') aria-invalid="true" aria-describedby="usuario-error" @enderror
                >
                @error('usuario')
                    <p id="usuario-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between gap-4">
                    <label for="password" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">Contraseña</label>
                    <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" class="text-xs font-semibold text-signal underline-offset-4 hover:underline">
                        <span data-show-label>Mostrar</span>
                        <span data-hide-label class="hidden">Ocultar</span>
                    </button>
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-base text-ink-950 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
                @error('password')
                    <p id="password-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="group mt-2 flex min-h-13 w-full items-center justify-between bg-ink-950 px-5 font-display text-base font-bold tracking-[0.08em] text-white uppercase transition hover:bg-ink-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hazard">
                Entrar al sistema
                <span class="grid size-7 place-items-center bg-hazard text-ink-950 transition group-hover:translate-x-1" aria-hidden="true">→</span>
            </button>
        </form>

        <p class="mt-6 border-t border-line pt-4 font-mono text-[9px] leading-5 tracking-wider text-steel-500 uppercase">
            Acceso restringido · La actividad de sesión queda registrada
        </p>
    </section>
@endsection
