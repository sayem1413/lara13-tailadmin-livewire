@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-400',
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-500/20 dark:bg-yellow-500/10 dark:text-yellow-400',
        'info' => 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300',
    ][$type] ?? '';
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border px-4 py-3 text-sm {$styles}"]) }}>
    {{ $slot }}
</div>
