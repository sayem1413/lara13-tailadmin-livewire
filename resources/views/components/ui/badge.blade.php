@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
        'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
    ][$color] ?? '';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$colors}"]) }}>
    {{ $slot }}
</span>
