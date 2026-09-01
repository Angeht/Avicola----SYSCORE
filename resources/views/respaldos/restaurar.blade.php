@extends('layouts.app')

@section('title', 'Restaurar base de datos')
@section('section', 'Respaldos y restauración')

@section('content')
    @php
        $formatBytes = fn (float|int|string|null $bytes): string => number_format((float) ($bytes ?? 0) / 1048576, 2, ',', '.').' MB';
    @endphp

    <header class="reveal-up max-w-4xl border-b border-line pb-7">
        <a href="{{ route('respaldos.index') }}" class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">← Volver a respaldos</a>
        <p class="mt-5 font-mono text-[10px] font-semibold tracking-[0.24em] text-danger uppercase">Operación crítica / Recuperación</p>
        <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Restaurar base de datos</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel-500">Los datos actuales serán reemplazados por el estado contenido en esta copia. La sesión se cerrará al terminar.</p>
    </header>

    @error('restauracion')<div class="mt-5 border-l-4 border-danger bg-danger-soft px-5 py-4 text-sm font-medium text-danger" role="alert">{{ $message }}</div>@enderror

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form method="POST" action="{{ route('respaldos.restauracion.store', $backup) }}" class="reveal-up reveal-up-delay-1 border border-danger/40 bg-paper p-6 shadow-panel sm:p-8">
            @csrf
            <div class="industrial-hatch bg-ink-950 p-6 text-white"><p class="font-mono text-[9px] tracking-[0.2em] text-danger uppercase">Confirmación reforzada</p><h2 class="mt-2 font-display text-2xl font-bold uppercase">Esta acción reemplaza datos</h2><p class="mt-3 text-sm leading-6 text-steel-300">Antes de iniciar se creará una copia preventiva de la base actual. Si la importación falla, el sistema intentará recuperarla automáticamente.</p></div>

            <div class="mt-6 grid gap-5">
                <div><label for="current-password" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Tu contraseña actual</label><input id="current-password" name="current_password" type="password" required autocomplete="current-password" class="mt-2 min-h-12 w-full border border-line bg-white px-4 text-sm text-ink-950 outline-none focus:border-danger focus:ring-2 focus:ring-danger/20">@error('current_password')<p class="mt-2 text-sm font-semibold text-danger">{{ $message }}</p>@enderror</div>
                <div><label for="restore-confirmation" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Escribe RESTAURAR</label><input id="restore-confirmation" name="confirmacion" type="text" required autocomplete="off" class="mt-2 min-h-12 w-full border border-line bg-white px-4 font-mono text-sm font-semibold tracking-wider text-ink-950 uppercase outline-none focus:border-danger focus:ring-2 focus:ring-danger/20">@error('confirmacion')<p class="mt-2 text-sm font-semibold text-danger">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mt-7 flex flex-col gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end"><a href="{{ route('respaldos.index') }}" class="inline-flex min-h-12 items-center justify-center border border-line px-5 font-display text-sm font-bold tracking-wider text-ink-700 uppercase">Cancelar</a><button type="submit" class="inline-flex min-h-12 items-center justify-center bg-danger px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-950">Restaurar y cerrar sesión</button></div>
        </form>

        <aside class="reveal-up reveal-up-delay-2 grid content-start gap-5">
            <section class="border border-line bg-paper shadow-panel"><div class="border-b border-line px-5 py-4"><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Copia verificada</p><h2 class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Origen de recuperación</h2></div><dl class="grid gap-4 p-5 text-sm"><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Archivo</dt><dd class="mt-1 break-all font-mono text-xs font-semibold text-ink-950">{{ $backup->nombre_archivo }}</dd></div><div class="grid grid-cols-2 gap-3"><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Creado</dt><dd class="mt-1 font-semibold text-ink-950">{{ $backup->creado_at->format('d/m/Y H:i') }}</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Tamaño</dt><dd class="mt-1 font-semibold text-ink-950">{{ $formatBytes($backup->tamano_bytes) }}</dd></div></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Verificado</dt><dd class="mt-1 font-semibold text-signal">{{ $backup->verificado_at->format('d/m/Y H:i:s') }}</dd></div><div><dt class="font-mono text-[8px] text-steel-500 uppercase">Checksum SHA-256</dt><dd class="mt-1 break-all font-mono text-[9px] text-ink-700">{{ $backup->checksum_sha256 }}</dd></div></dl></section>
            <section class="border-l-4 border-hazard bg-hazard-soft p-5 text-sm leading-6 text-ink-700"><strong>No cierres Laragon ni apagues el equipo</strong> mientras se ejecuta la restauración. El proceso puede tardar según el tamaño de la base.</section>
        </aside>
    </div>
@endsection
