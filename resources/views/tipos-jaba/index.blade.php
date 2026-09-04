@extends('layouts.app')

@section('title', 'Tipos de jaba')
@section('section', 'Jabas y taras')

@section('content')
    @php
        $tare = static function (float|int|string|null $value): string {
            return rtrim(rtrim(number_format((float) ($value ?? 0), 3, ',', '.'), '0'), ',');
        };
    @endphp

    <x-catalog-header
        eyebrow="Proveedores / Pesajes"
        title="Jabas y taras"
        description="Administra los recipientes y la tara que se descuenta para obtener el peso neto de cada operación."
        :count="$activeCount + $inactiveCount"
        count-label="Tipos registrados"
        :create-href="route('tipos-jaba.create')"
        create-label="Nueva jaba"
    />

    @if ($pendingTareCount > 0)
        <section class="reveal-up mt-6 flex flex-col gap-4 border-l-4 border-danger bg-danger/8 p-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Advertencia de taras pendientes">
            <div class="flex gap-4">
                <span class="grid size-10 shrink-0 place-items-center bg-danger text-white"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4 3 20h18L12 4Z" /><path d="M12 9v5m0 3h.01" /></svg></span>
                <div><p class="font-display text-lg font-bold text-ink-950 uppercase">{{ $pendingTareCount }} {{ $pendingTareCount === 1 ? 'jaba activa necesita' : 'jabas activas necesitan' }} calibración</p><p class="mt-1 text-sm text-ink-700">No uses estos tipos en pesajes reales hasta reemplazar la tara 0 kg.</p></div>
            </div>
            <span class="whitespace-nowrap font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Acción requerida</span>
        </section>
    @endif

    <section class="mt-6 grid grid-cols-2 gap-3 lg:max-w-2xl lg:grid-cols-4" aria-label="Resumen de tipos de jaba">
        <div class="border-l-4 border-signal bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $activeCount }}</span><p class="font-mono text-[8px] tracking-wider text-signal uppercase">Activas</p></div>
        <div class="border-l-4 border-steel-300 bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $inactiveCount }}</span><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Inactivas</p></div>
        <div class="border-l-4 border-danger bg-paper px-4 py-3 shadow-sm"><span class="font-display text-3xl font-extrabold text-ink-950">{{ $pendingTareCount }}</span><p class="font-mono text-[8px] tracking-wider text-danger uppercase">Tara pendiente</p></div>
        <div class="border-l-4 border-hazard bg-paper px-4 py-3 shadow-sm"><span class="font-mono text-2xl font-extrabold text-ink-950">{{ $tare($averageTare) }}</span><p class="font-mono text-[8px] tracking-wider text-ink-700 uppercase">Tara media kg</p></div>
    </section>

    <x-catalog-toolbar :action="route('tipos-jaba.index')" :search="$search" :status="$status" placeholder="Buscar por nombre o descripción" />

    <section class="reveal-up reveal-up-delay-2 mt-4 overflow-hidden border border-line bg-paper shadow-panel" aria-labelledby="crate-results-title">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6"><div><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">Resultado / Calibración</p><h2 id="crate-results-title" class="mt-1 font-display text-xl font-bold text-ink-950 uppercase">Jabas registradas</h2></div><span class="border border-line px-2.5 py-1 font-mono text-[9px] text-steel-500 uppercase">{{ $crateTypes->total() }} encontradas</span></div>

        @if ($crateTypes->isEmpty())
            <x-empty-state title="No encontramos tipos de jaba" description="Ajusta los filtros o registra el primer recipiente con su tara real." :action-href="route('tipos-jaba.create')" action-label="Registrar jaba"><x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16l-2 12H6L4 7Z" /><path d="M8 7V4h8v3" /></svg></x-slot:icon></x-empty-state>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[900px] border-collapse text-left" aria-label="Tipos de jaba registrados">
                    <thead class="bg-canvas font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase"><tr><th scope="col" class="px-6 py-3 font-semibold">Tipo de jaba</th><th scope="col" class="px-6 py-3 font-semibold">Tara referencial</th><th scope="col" class="px-6 py-3 font-semibold">Estado</th><th scope="col" class="px-6 py-3 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($crateTypes as $crateType)
                            <tr class="transition hover:bg-hazard-soft/20">
                                <td class="px-6 py-4"><p class="font-semibold text-ink-950">{{ $crateType->nombre }}</p><p class="mt-1 max-w-xl truncate text-xs text-steel-500">{{ $crateType->descripcion ?: 'Sin descripción' }}</p></td>
                                <td class="px-6 py-4">
                                    @if ((float) $crateType->tara_referencial_kg > 0)
                                        <p class="font-mono text-lg font-bold text-ink-950">{{ $tare($crateType->tara_referencial_kg) }} <span class="text-[10px] text-signal">kg</span></p>
                                    @else
                                        <span class="inline-flex items-center gap-2 border border-danger/30 bg-danger/8 px-2.5 py-1 font-mono text-[8px] font-semibold tracking-wider text-danger uppercase"><span class="size-1.5 bg-danger"></span>0 kg · Pendiente</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4"><x-status-badge :active="$crateType->activo" /></td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('tipos-jaba.edit', $crateType) }}" class="inline-flex min-h-9 items-center border border-line px-3 font-mono text-[9px] font-semibold tracking-wider text-ink-700 uppercase transition hover:border-ink-950 hover:bg-ink-950 hover:text-white">Editar</a>
                                        @if ($crateType->activo)
                                            <form method="POST" action="{{ route('tipos-jaba.activacion.destroy', $crateType) }}" data-confirm="¿Desactivar este tipo de jaba? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="inline-flex min-h-9 items-center border border-danger/25 px-3 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase transition hover:bg-danger hover:text-white">Desactivar</button></form>
                                        @else
                                            <form method="POST" action="{{ route('tipos-jaba.activacion.store', $crateType) }}">@csrf<button type="submit" class="inline-flex min-h-9 items-center border border-signal/30 px-3 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase transition hover:bg-signal hover:text-white">Activar</button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line md:hidden">
                @foreach ($crateTypes as $crateType)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><p class="font-semibold text-ink-950">{{ $crateType->nombre }}</p><p class="mt-1 text-xs leading-5 text-steel-500">{{ $crateType->descripcion ?: 'Sin descripción' }}</p></div><x-status-badge :active="$crateType->activo" /></div>
                        <div class="mt-4 border-l-4 {{ (float) $crateType->tara_referencial_kg > 0 ? 'border-signal' : 'border-danger' }} bg-canvas px-4 py-3"><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">Tara referencial</p><p class="mt-1 font-mono text-xl font-bold text-ink-950">{{ $tare($crateType->tara_referencial_kg) }} kg</p>@if ((float) $crateType->tara_referencial_kg <= 0)<p class="mt-1 text-xs font-semibold text-danger">Pendiente de configurar</p>@endif</div>
                        <div class="mt-4 flex gap-2">
                            <a href="{{ route('tipos-jaba.edit', $crateType) }}" class="inline-flex min-h-10 flex-1 items-center justify-center border border-ink-950 font-mono text-[9px] font-semibold tracking-wider text-ink-950 uppercase">Editar</a>
                            @if ($crateType->activo)
                                <form method="POST" action="{{ route('tipos-jaba.activacion.destroy', $crateType) }}" class="flex-1" data-confirm="¿Desactivar este tipo de jaba? Su historial no se eliminará.">@csrf @method('DELETE')<button type="submit" class="min-h-10 w-full border border-danger/30 font-mono text-[9px] font-semibold tracking-wider text-danger uppercase">Desactivar</button></form>
                            @else
                                <form method="POST" action="{{ route('tipos-jaba.activacion.store', $crateType) }}" class="flex-1">@csrf<button type="submit" class="min-h-10 w-full border border-signal/30 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">Activar</button></form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <x-catalog-pagination :paginator="$crateTypes" />
        @endif
    </section>
@endsection
