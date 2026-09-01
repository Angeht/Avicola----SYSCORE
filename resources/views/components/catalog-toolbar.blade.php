@props([
    'action',
    'search' => '',
    'status' => 'activos',
    'placeholder' => 'Buscar...',
])

<form method="GET" action="{{ $action }}" {{ $attributes->class(['reveal-up reveal-up-delay-1 mt-6 grid gap-3 border border-line bg-paper p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_190px_auto]']) }}>
    <div class="relative">
        <label for="catalog-search" class="sr-only">Buscar en el catálogo</label>
        <svg class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
        <input id="catalog-search" name="buscar" type="search" value="{{ $search }}" placeholder="{{ $placeholder }}" class="min-h-12 w-full border border-line bg-white pr-4 pl-11 text-sm text-ink-950 outline-none transition placeholder:text-steel-300 focus:border-ink-950 focus:ring-2 focus:ring-hazard/40">
    </div>

    <div class="relative grid">
        <label for="catalog-status" class="sr-only">Filtrar por estado</label>
        <select id="catalog-status" name="estado" class="col-start-1 row-start-1 min-h-12 appearance-none border border-line bg-white px-4 pr-10 text-sm font-semibold text-ink-700 outline-none transition focus:border-ink-950 focus:ring-2 focus:ring-hazard/40">
            <option value="activos" @selected($status === 'activos')>Solo activos</option>
            <option value="inactivos" @selected($status === 'inactivos')>Solo inactivos</option>
            <option value="todos" @selected($status === 'todos')>Todos los estados</option>
        </select>
        <svg class="pointer-events-none col-start-1 row-start-1 mr-4 size-4 self-center justify-self-end text-steel-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="inline-flex min-h-12 flex-1 items-center justify-center bg-ink-950 px-5 font-display text-sm font-bold tracking-wider text-white uppercase transition hover:bg-ink-800 lg:flex-none">Filtrar</button>
        @if ($search !== '' || $status !== 'activos')
            <a href="{{ $action }}" class="inline-flex min-h-12 items-center justify-center border border-line px-4 text-xs font-semibold text-steel-500 uppercase transition hover:border-ink-950 hover:text-ink-950" aria-label="Limpiar filtros">×</a>
        @endif
    </div>
</form>
