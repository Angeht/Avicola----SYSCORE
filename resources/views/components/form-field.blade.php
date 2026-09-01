@props([
    'name',
    'label',
    'hint' => null,
    'required' => false,
])

<div {{ $attributes }}>
    <div class="flex items-end justify-between gap-4">
        <label for="{{ $name }}" class="font-mono text-[10px] font-semibold tracking-[0.16em] text-ink-700 uppercase">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
        @if ($hint)
            <span class="text-right text-[11px] text-steel-500">{{ $hint }}</span>
        @endif
    </div>

    <div class="mt-2">{{ $slot }}</div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
