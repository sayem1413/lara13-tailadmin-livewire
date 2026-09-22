@props(['label' => null])

<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input
        type="radio"
        {{ $attributes->merge(['class' => 'size-4 border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-white/20 dark:bg-white/5']) }}
    />

    @if ($label)
        {{ $label }}
    @endif
</label>
