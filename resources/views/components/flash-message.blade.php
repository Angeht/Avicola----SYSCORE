@if (session('status'))
    <div role="status" aria-live="polite" {{ $attributes->class(['mb-6 flex items-start gap-3 border border-signal/30 bg-signal-soft px-4 py-3 text-sm text-ink-900']) }}>
        <svg class="mt-0.5 size-5 shrink-0 text-signal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="m5 12 4 4L19 6" />
        </svg>
        <p class="font-medium">{{ session('status') }}</p>
        <button type="button" class="ml-auto grid size-6 place-items-center text-steel-500 hover:text-ink-950" data-dismiss aria-label="Cerrar mensaje">×</button>
    </div>
@endif
