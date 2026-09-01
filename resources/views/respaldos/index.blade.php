@extends('layouts.app')

@section('title', 'Respaldos y restauración')
@section('section', 'Respaldos y restauración')

@section('content')
    @php
        $formatBytes = function (float|int|string|null $bytes): string {
            $value = (float) ($bytes ?? 0);
            $units = ['B', 'KB', 'MB', 'GB'];
            $unit = 0;

            while ($value >= 1024 && $unit < count($units) - 1) {
                $value /= 1024;
                $unit++;
            }

            return number_format($value, $unit === 0 ? 0 : 2, ',', '.').' '.$units[$unit];
        };
        $statusClass = fn (string $status): string => match ($status) {
            'COMPLETADO' => 'border-signal/30 bg-signal-soft text-signal',
            'FALLIDO', 'FALLIDA_CRITICA' => 'border-danger/30 bg-danger-soft text-danger',
            'ELIMINADO', 'FALLIDA_REVERTIDA' => 'border-steel-300 bg-canvas text-steel-500',
            default => 'border-hazard/40 bg-hazard-soft text-ink-950',
        };
        $days = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    @endphp

    <header class="reveal-up flex flex-col gap-6 border-b border-line pb-7 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
            <p class="font-mono text-[10px] font-semibold tracking-[0.24em] text-signal uppercase">Administración / Continuidad</p>
            <h1 class="mt-3 font-display text-4xl leading-none font-extrabold tracking-tight text-ink-950 uppercase sm:text-5xl">Respaldos de MySQL</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Configura copias automáticas, comprueba que realmente puedan restaurarse y recupera la base de datos con una copia preventiva.</p>
        </div>
        <form method="POST" action="{{ route('respaldos.store') }}">
            @csrf
            <button type="submit" @disabled(! $engineAvailable) class="inline-flex min-h-12 items-center justify-center gap-3 bg-ink-950 px-6 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 disabled:cursor-not-allowed disabled:opacity-40">
                <span class="grid size-7 place-items-center bg-hazard text-xl text-ink-950">+</span>
                Crear copia ahora
            </button>
        </form>
    </header>

    @if ($errors->any())
        <div class="mt-5 border-l-4 border-danger bg-danger-soft px-5 py-4 text-sm font-medium text-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Estado de los respaldos">
        <div class="border-l-4 {{ $lastBackup ? 'border-signal' : 'border-danger' }} bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Última copia válida</p><p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ $lastBackup?->creado_at->format('d/m/Y') ?? 'Sin copias' }}</p><p class="mt-1 text-xs text-steel-500">{{ $lastBackup?->creado_at->format('H:i:s') ?? 'Crea el primer respaldo' }}</p></div>
        <div class="border-l-4 {{ $schedulerInstalled ? 'border-signal' : 'border-hazard' }} bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Tarea de Windows</p><p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ $schedulerInstalled ? 'Instalada' : 'Pendiente' }}</p><p class="mt-1 text-xs text-steel-500">Revisión automática cada minuto</p></div>
        <div class="border-l-4 {{ $engineAvailable ? 'border-signal' : 'border-danger' }} bg-paper p-5 shadow-sm"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Motor MySQL</p><p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ $engineAvailable ? 'Disponible' : 'No localizado' }}</p><p class="mt-1 text-xs text-steel-500">mysql + mysqldump de Laragon</p></div>
        <div class="industrial-hatch border-l-4 border-hazard bg-ink-950 p-5 text-white shadow-sm"><p class="font-mono text-[8px] tracking-wider text-hazard uppercase">Espacio utilizado</p><p class="mt-2 font-display text-2xl font-extrabold">{{ $formatBytes($storageBytes) }}</p><p class="mt-1 text-xs text-steel-300">Archivos disponibles</p></div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(340px,0.75fr)]">
        <section class="reveal-up reveal-up-delay-1 border border-line bg-paper shadow-panel" aria-labelledby="backup-settings-title">
            <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Programación / Retención</p><h2 id="backup-settings-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Configuración automática</h2></div>
            <form method="POST" action="{{ route('respaldos.configuracion.update') }}" class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                @csrf
                @method('PUT')
                <label class="flex min-h-14 items-center gap-3 border border-line bg-canvas px-4 sm:col-span-2"><input type="checkbox" name="activo" value="1" @checked(old('activo', $configuration->activo)) class="size-4 accent-signal"><span><span class="block text-sm font-semibold text-ink-950">Activar copias automáticas</span><span class="mt-0.5 block text-xs text-steel-500">La tarea solo genera archivos cuando esta opción está activa.</span></span></label>

                <div><label for="backup-frequency" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Frecuencia</label><select id="backup-frequency" name="frecuencia" class="mt-2 min-h-12 w-full border border-line bg-white px-3 text-sm font-semibold text-ink-950 outline-none focus:border-ink-950"><option value="DIARIA" @selected(old('frecuencia', $configuration->frecuencia) === 'DIARIA')>Diaria</option><option value="SEMANAL" @selected(old('frecuencia', $configuration->frecuencia) === 'SEMANAL')>Semanal</option><option value="MENSUAL" @selected(old('frecuencia', $configuration->frecuencia) === 'MENSUAL')>Mensual</option></select></div>
                <div><label for="backup-time" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Hora</label><input id="backup-time" name="hora" type="time" value="{{ old('hora', substr((string) $configuration->hora, 0, 5)) }}" required class="mt-2 min-h-12 w-full border border-line bg-white px-3 text-sm font-semibold text-ink-950 outline-none focus:border-ink-950"></div>
                <div><label for="backup-weekday" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Día si es semanal</label><select id="backup-weekday" name="dia_semana" class="mt-2 min-h-12 w-full border border-line bg-white px-3 text-sm text-ink-950 outline-none focus:border-ink-950">@foreach ($days as $number => $day)<option value="{{ $number }}" @selected((int) old('dia_semana', $configuration->dia_semana ?? 1) === $number)>{{ $day }}</option>@endforeach</select></div>
                <div><label for="backup-monthday" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Día si es mensual</label><input id="backup-monthday" name="dia_mes" type="number" min="1" max="28" value="{{ old('dia_mes', $configuration->dia_mes ?? 1) }}" class="mt-2 min-h-12 w-full border border-line bg-white px-3 text-sm text-ink-950 outline-none focus:border-ink-950"></div>
                <div><label for="backup-retention" class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">Copias automáticas a conservar</label><input id="backup-retention" name="retencion_cantidad" type="number" min="1" max="365" value="{{ old('retencion_cantidad', $configuration->retencion_cantidad) }}" required class="mt-2 min-h-12 w-full border border-line bg-white px-3 text-sm text-ink-950 outline-none focus:border-ink-950"><p class="mt-1.5 text-xs text-steel-500">Las copias manuales y preventivas no se eliminan por retención.</p></div>
                <label class="flex min-h-12 items-center gap-3 border border-line bg-canvas px-4"><input type="checkbox" name="verificar_automaticamente" value="1" @checked(old('verificar_automaticamente', $configuration->verificar_automaticamente)) class="size-4 accent-signal"><span class="text-sm font-semibold text-ink-950">Probar restauración automáticamente</span></label>
                <button type="submit" class="min-h-12 bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 sm:col-span-2">Guardar programación</button>
            </form>
        </section>

        <aside class="grid content-start gap-6">
            <section class="industrial-hatch bg-ink-950 p-6 text-white shadow-panel" aria-labelledby="scheduler-title">
                <p class="font-mono text-[9px] tracking-[0.2em] text-hazard uppercase">Laragon / Windows</p>
                <h2 id="scheduler-title" class="mt-2 font-display text-2xl font-bold uppercase">Programador del sistema</h2>
                <p class="mt-3 text-sm leading-6 text-steel-300">Laravel revisa la programación guardada cada minuto. La tarea de Windows mantiene el proceso activo aunque el navegador esté cerrado.</p>
                <div class="mt-5 border border-white/10 bg-white/5 p-4"><p class="font-mono text-[8px] text-steel-300 uppercase">Estado</p><p class="mt-2 font-display text-xl font-bold {{ $schedulerInstalled ? 'text-signal' : 'text-hazard' }}">{{ $schedulerInstalled ? 'Tarea operativa' : 'Instalación pendiente' }}</p></div>
                @if ($schedulerCompatible)
                    <form method="POST" action="{{ route('respaldos.tarea-programada.store') }}" class="mt-5" data-confirm="¿Instalar o reparar la tarea de respaldos en el Programador de Windows?">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center bg-hazard px-4 font-display text-xs font-bold tracking-wider text-ink-950 uppercase transition hover:bg-white">{{ $schedulerInstalled ? 'Reparar tarea' : 'Instalar tarea' }}</button>
                    </form>
                @else
                    <p class="mt-4 border-l-2 border-danger pl-3 text-sm text-steel-300">El servidor actual no ofrece el Programador de tareas de Windows.</p>
                @endif
            </section>

            <section class="border-l-4 border-signal bg-signal-soft p-5"><p class="font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Restauración segura</p><ol class="mt-3 grid gap-2 text-sm leading-6 text-ink-700"><li>1. Se valida el checksum SHA-256.</li><li>2. Se exige una restauración aislada previa.</li><li>3. Se crea una copia preventiva actual.</li><li>4. Si falla, se revierte automáticamente.</li></ol></section>
        </aside>
    </div>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="backup-history-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-5 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Archivos / Integridad</p><h2 id="backup-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Copias disponibles</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $backups->total() }} registros</span></div>

        @if ($backups->isEmpty())
            <x-empty-state title="Aún no existen respaldos" description="Crea una copia manual o instala la tarea automática para proteger la información de la avícola." :action-href="null" action-label=""><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3" /><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] border-collapse text-left" aria-label="Historial de respaldos">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th class="px-6 py-3 font-semibold">Archivo</th><th class="px-6 py-3 font-semibold">Tipo</th><th class="px-6 py-3 font-semibold">Estado</th><th class="px-6 py-3 font-semibold">Integridad</th><th class="px-6 py-3 text-right font-semibold">Tamaño</th><th class="px-6 py-3 text-right font-semibold">Gestión</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($backups as $backup)
                            <tr class="align-top transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="max-w-sm truncate font-mono text-xs font-semibold text-ink-950" title="{{ $backup->nombre_archivo }}">{{ $backup->nombre_archivo }}</p><p class="mt-1 text-xs text-steel-500">{{ $backup->creado_at->format('d/m/Y · H:i:s') }} · {{ $backup->creadoPor?->nombreCompleto() ?? 'Tarea automática' }}</p>@if ($backup->error)<p class="mt-2 max-w-sm text-xs leading-5 text-danger">{{ $backup->error }}</p>@endif</td>
                                <td class="px-6 py-4"><span class="font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase">{{ $backup->etiquetaTipo() }}</span></td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $statusClass($backup->estado) }}">{{ $backup->etiquetaEstado() }}</span></td>
                                <td class="px-6 py-4">@if ($backup->verificado_at)<p class="font-semibold text-signal">Restauración probada</p><p class="mt-1 text-xs text-steel-500">{{ $backup->verificado_at->format('d/m/Y · H:i') }}</p>@elseif ($backup->estaDisponible())<p class="font-semibold text-hazard">Pendiente de verificar</p><p class="mt-1 font-mono text-[8px] text-steel-500">SHA {{ str($backup->checksum_sha256)->substr(0, 12) }}…</p>@else<span class="text-xs text-steel-500">No disponible</span>@endif</td>
                                <td class="px-6 py-4 text-right font-mono text-xs font-semibold text-ink-950">{{ $formatBytes($backup->tamano_bytes) }}</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($backup->estaDisponible())<a href="{{ route('respaldos.download', $backup) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[8px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Descargar</a><form method="POST" action="{{ route('respaldos.verificacion.store', $backup) }}" data-confirm="Se restaurará esta copia en una base temporal aislada. ¿Continuar?">@csrf<button type="submit" class="inline-flex min-h-9 items-center border border-signal/30 px-3 font-mono text-[8px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Verificar</button></form>@if ($backup->estaRestaurable())<a href="{{ route('respaldos.restauracion.create', $backup) }}" class="inline-flex min-h-9 items-center border border-danger/30 px-3 font-mono text-[8px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Restaurar</a>@endif<form method="POST" action="{{ route('respaldos.destroy', $backup) }}" data-confirm="¿Eliminar permanentemente {{ $backup->nombre_archivo }}? El archivo no podrá recuperarse.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center px-2 font-mono text-[8px] font-semibold tracking-wider text-steel-500 uppercase hover:text-danger">Eliminar</button></form>@else<span class="text-xs text-steel-500">—</span>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-catalog-pagination :paginator="$backups" />
        @endif
    </section>

    <section class="reveal-up reveal-up-delay-3 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="restoration-history-title">
        <div class="border-b border-line px-5 py-5 sm:px-6"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-danger uppercase">Recuperación / Auditoría</p><h2 id="restoration-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Restauraciones recientes</h2></div>
        @if ($restorations->isEmpty())
            <p class="p-6 text-sm text-steel-500">Todavía no se ha restaurado la base de datos.</p>
        @else
            <div class="divide-y divide-line">@foreach ($restorations as $restoration)<article class="grid gap-3 p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center sm:p-6"><div><div class="flex flex-wrap items-center gap-2"><p class="font-mono text-xs font-semibold text-ink-950">{{ $restoration->respaldo_nombre }}</p><span class="border px-2 py-1 font-mono text-[8px] font-semibold uppercase {{ $statusClass($restoration->estado) }}">{{ $restoration->etiquetaEstado() }}</span></div><p class="mt-2 text-xs text-steel-500">{{ $restoration->iniciado_at->format('d/m/Y · H:i:s') }} · {{ $restoration->solicitante }}</p>@if ($restoration->respaldo_preventivo_nombre)<p class="mt-1 text-xs text-steel-500">Copia preventiva: {{ $restoration->respaldo_preventivo_nombre }}</p>@endif @if ($restoration->error)<p class="mt-2 text-xs text-danger">{{ $restoration->error }}</p>@endif</div><p class="font-mono text-[9px] text-steel-500 uppercase">{{ $restoration->operacion_uuid }}</p></article>@endforeach</div>
        @endif
    </section>
@endsection
