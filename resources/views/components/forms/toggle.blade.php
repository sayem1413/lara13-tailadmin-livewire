@props(['label' => null])

@php
    $disabled = $attributes->get('disabled');
@endphp

<label class="flex items-center gap-2 {{ $disabled ? 'cursor-not-allowed' : 'cursor-pointer' }}">
    <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
        <span class="pointer-events-none absolute inset-0 rounded-full bg-gray-300 transition-colors peer-checked:bg-brand-500 peer-disabled:opacity-50 dark:bg-white/10"></span>
        <span class="pointer-events-none absolute left-0.5 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
    </span>

    @if ($label)
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
    @endif
</label>
