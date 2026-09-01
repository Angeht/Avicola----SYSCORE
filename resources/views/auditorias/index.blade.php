<div>
    <!-- Simplicity is the ultimate sophistication. - Leonardo da Vinci -->
</div>
@extends('layouts.app')

@section('title', 'Auditoría general')
@section('section', 'Auditoría general')

@section('content')
    <header class="reveal-up flex flex-col gap-5 border-b border-ink-950 pb-7 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="font-mono text-[10px] font-semibold tracking-[0.2em] text-signal uppercase">Trazabilidad / Control interno</p>
            <h1 class="mt-2 font-display text-4xl font-bold tracking-tight text-ink-950 uppercase sm:text-5xl">Historial de operaciones</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">Consulta quién creó, modificó, anuló o accedió al sistema, con el detalle exacto de cada cambio.</p>
        </div>
        <div class="border-l-4 border-hazard bg-paper px-5 py-3 shadow-panel">
            <p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">Resultado filtrado</p>
            <p class="mt-1 font-display text-3xl font-bold text-ink-950">{{ number_format($audits->total(), 0, ',', '.') }}</p>
        </div>
    </header>

    <section class="reveal-up reveal-up-delay-1 mt-6 grid gap-3 sm:grid-cols-3" aria-label="Indicadores de auditoría">
        <x-stat-card label="Eventos de hoy" :value="number_format($changesToday, 0, ',', '.')" meta="Actividad registrada" tone="hazard" />
        <x-stat-card label="Anulaciones de hoy" :value="number_format($annulmentsToday, 0, ',', '.')" meta="Operaciones revertidas" />
        <x-stat-card label="Módulos auditados" :value="number_format($tables->count(), 0, ',', '.')" meta="Tablas con historial" />
    </section>

    <form method="GET" action="{{ route('auditorias.index') }}" class="reveal-up reveal-up-delay-1 mt-6 border border-line bg-paper p-5 shadow-panel" aria-label="Filtros de auditoría">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <label class="xl:col-span-2">
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Buscar</span>
                <input type="search" name="buscar" value="{{ $filters['buscar'] ?? '' }}" placeholder="Usuario, tabla o registro" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/30">
            </label>
            <label>
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Acción</span>
                <select name="accion" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
                    <option value="">Todas</option>
                    @foreach ($actions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['accion'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Módulo</span>
                <select name="tabla" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
                    <option value="">Todos</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table }}" @selected(($filters['tabla'] ?? '') === $table)>{{ str($table)->replace('_', ' ')->headline() }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Desde</span>
                <input type="date" name="desde" value="{{ $filters['desde'] ?? '' }}" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
            </label>
            <label>
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Hasta</span>
                <input type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
            </label>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <label class="w-full sm:max-w-sm">
                <span class="font-mono text-[9px] font-semibold tracking-wider text-steel-500 uppercase">Responsable</span>
                <select name="usuario_id" class="mt-2 min-h-11 w-full border border-line bg-canvas px-3 text-sm outline-none focus:border-ink-950">
                    <option value="">Todos los usuarios</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['usuario_id'] ?? '') === (string) $user->id)>{{ $user->nombreCompleto() }} · {{ $user->usuario }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2">
                <a href="{{ route('auditorias.index') }}" class="inline-flex min-h-11 items-center justify-center border border-line px-4 font-display text-xs font-bold tracking-wider text-ink-700 uppercase transition hover:border-ink-950">Limpiar</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-ink-950 px-6 font-display text-xs font-bold tracking-wider text-white uppercase transition hover:bg-ink-800">Aplicar filtros</button>
            </div>
        </div>
        @error('hasta')<p class="mt-3 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
    </form>

    <section class="reveal-up reveal-up-delay-2 mt-6 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="audit-history-title">
        <div class="border-b border-line px-5 py-5 sm:px-6">
            <p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Bitácora / Eventos</p>
            <h2 id="audit-history-title" class="mt-1 font-display text-2xl font-bold text-ink-950 uppercase">Cambios registrados</h2>
        </div>

        @if ($audits->isEmpty())
            <x-empty-state title="No hay eventos para mostrar" description="Prueba otro rango o filtro. Los nuevos cambios autenticados se registrarán aquí.">
                <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3 4 6v5c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-3Z" /><path d="M9 12h6m-3-3v6" /></svg></x-slot:icon>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-left">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase">
                        <tr><th class="px-6 py-3 font-semibold">Momento</th><th class="px-6 py-3 font-semibold">Responsable</th><th class="px-6 py-3 font-semibold">Acción</th><th class="px-6 py-3 font-semibold">Módulo / Registro</th><th class="px-6 py-3 font-semibold">Cambios</th><th class="px-6 py-3 text-right font-semibold">Detalle</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($audits as $audit)
                            @php($actionClass = match ($audit->accion) { 'ANULAR', 'DELETE' => 'border-danger/30 bg-danger-soft text-danger', 'INSERT' => 'border-signal/30 bg-signal-soft text-signal', 'LOGIN' => 'border-hazard/40 bg-hazard-soft text-ink-950', default => 'border-line bg-canvas text-ink-700' })
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-semibold text-ink-950">{{ $audit->created_at->format('d/m/Y') }}</p><p class="mt-1 text-xs text-steel-500">{{ $audit->created_at->format('H:i:s') }}</p></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $audit->usuario?->nombreCompleto() ?? 'Sistema / usuario eliminado' }}</p><p class="mt-1 font-mono text-[9px] text-steel-500">{{ $audit->usuario?->usuario ?? '—' }} · {{ $audit->ip ?: 'IP no disponible' }}</p></td>
                                <td class="px-6 py-4"><span class="inline-flex border px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider uppercase {{ $actionClass }}">{{ $audit->etiquetaAccion() }}</span></td>
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $audit->etiquetaTabla() }}</p><p class="mt-1 font-mono text-[9px] text-steel-500">{{ $audit->registro_id ? 'Registro #'.$audit->registro_id : 'Sin registro asociado' }}</p></td>
                                <td class="px-6 py-4 font-mono text-xs text-ink-700">{{ $audit->detalles_count }} campos</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('auditorias.show', $audit) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Inspeccionar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-catalog-pagination :paginator="$audits" />
        @endif
    </section>
@endsection
