@props(['searchable' => false, 'bag' => 'default'])

@php
    $errorKey = wireModelName($attributes) ?? $attributes->get('name');
    $hasError = $errorKey && $errors->getBag($bag)->has($errorKey);

    $borderClasses = $hasError
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/50 dark:text-red-400'
        : 'border-gray-300 bg-white text-gray-800 focus:border-brand-500 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white/90 dark:focus:border-brand-500';
@endphp

@if ($searchable)
    <div x-data="enhancedSelect()" wire:ignore>
        <select
            x-ref="select"
            {{ $attributes->merge(['class' => "block w-full rounded-lg border px-4 py-2.5 text-sm shadow-xs focus:outline-none focus:ring-3 {$borderClasses}"]) }}
        >{{ $slot }}</select>
    </div>
@else
    <select
        {{ $attributes->merge(['class' => "form-select block w-full rounded-lg border px-4 py-2.5 text-sm shadow-xs focus:outline-none focus:ring-3 {$borderClasses}"]) }}
    >{{ $slot }}</select>
@endif
