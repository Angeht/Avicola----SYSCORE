@props(['paginator'])

@if ($paginator->hasPages())
    <nav {{ $attributes->class(['flex flex-col gap-4 border-t border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between']) }} aria-label="Paginación">
        <p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} registros
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="grid size-9 place-items-center border border-line text-steel-300" aria-disabled="true">←</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="grid size-9 place-items-center border border-line text-ink-700 transition hover:border-ink-950 hover:bg-ink-950 hover:text-white" rel="prev" aria-label="Página anterior">←</a>
            @endif

            @foreach ($paginator->elements() as $element)
                @if (is_string($element))
                    <span class="grid size-9 place-items-center text-steel-500">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="grid size-9 place-items-center bg-hazard font-mono text-xs font-bold text-ink-950" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="grid size-9 place-items-center border border-line font-mono text-xs text-ink-700 transition hover:border-ink-950 hover:bg-ink-950 hover:text-white" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="grid size-9 place-items-center border border-line text-ink-700 transition hover:border-ink-950 hover:bg-ink-950 hover:text-white" rel="next" aria-label="Página siguiente">→</a>
            @else
                <span class="grid size-9 place-items-center border border-line text-steel-300" aria-disabled="true">→</span>
            @endif
        </div>
    </nav>
@endif
