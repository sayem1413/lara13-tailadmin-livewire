@props(['type' => 'text', 'icon' => null, 'iconPosition' => 'left', 'bag' => 'default'])

@php
    $errorKey = wireModelName($attributes) ?? $attributes->get('name');
    $hasError = $errorKey && $errors->getBag($bag)->has($errorKey);

    $borderClasses = $hasError
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/50 dark:text-red-400'
        : 'border-gray-300 bg-white text-gray-800 focus:border-brand-500 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white/90 dark:focus:border-brand-500';
@endphp

<div class="relative">
    @if ($icon && $iconPosition === 'left')
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
            <x-ui.icon :name="$icon" class="size-4" />
        </span>
    @endif

    <input
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border px-4 py-2.5 text-sm shadow-xs placeholder:text-gray-400 focus:outline-none focus:ring-3 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400 dark:disabled:bg-white/[0.02] dark:disabled:text-gray-500 '
                .($icon && $iconPosition === 'left' ? 'pl-10 ' : '')
                .($icon && $iconPosition === 'right' ? 'pr-10 ' : '')
                .$borderClasses,
        ]) }}
    />

    @if ($icon && $iconPosition === 'right')
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400">
            <x-ui.icon :name="$icon" class="size-4" />
        </span>
    @endif
</div>
