@props(['variant' => 'primary', 'type' => 'button', 'loadingText' => null])

@php
    $variants = [
        'primary' => 'bg-brand-500 text-white hover:bg-brand-600 focus-visible:outline-brand-500',
        'secondary' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
    ][$variant] ?? '';
@endphp

<button
    type="{{ $type }}"
    @if ($loadingText)
        x-data="submitGuard()"
        x-bind:disabled="submitting"
    @endif
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-xs transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {$variants}"]) }}
>
    @if ($loadingText)
        <span x-show="!submitting" class="inline-flex items-center gap-2">{{ $slot }}</span>
        <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
            </svg>
            {{ $loadingText }}
        </span>
    @else
        {{ $slot }}
    @endif
</button>
