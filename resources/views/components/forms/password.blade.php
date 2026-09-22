@props(['meter' => false, 'bag' => 'default'])

@php
    $errorKey = wireModelName($attributes) ?? $attributes->get('name');
    $hasError = $errorKey && $errors->getBag($bag)->has($errorKey);

    $borderClasses = $hasError
        ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/50 dark:text-red-400'
        : 'border-gray-300 bg-white text-gray-800 focus:border-brand-500 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white/90 dark:focus:border-brand-500';
@endphp

<div x-data="passwordField()">
    <div class="relative">
        <input
            :type="visible ? 'text' : 'password'"
            x-on:input="update($event.target.value)"
            {{ $attributes->merge([
                'class' => "block w-full rounded-lg border px-4 py-2.5 pr-11 text-sm shadow-xs placeholder:text-gray-400 focus:outline-none focus:ring-3 {$borderClasses}",
            ]) }}
        />

        <button
            type="button"
            x-on:click="visible = !visible"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
            <x-ui.icon x-show="!visible" name="eye" class="size-4" />
            <x-ui.icon x-show="visible" x-cloak name="eye-off" class="size-4" />
            <span class="sr-only" x-text="visible ? 'Hide password' : 'Show password'"></span>
        </button>
    </div>

    @if ($meter)
        <div class="mt-2" x-show="value.length > 0" x-cloak>
            <div class="flex gap-1">
                <template x-for="i in 4" :key="i">
                    <div class="h-1 flex-1 rounded-full transition-colors" :class="i <= score ? strengthColor : 'bg-gray-200 dark:bg-white/10'"></div>
                </template>
            </div>
            <p class="mt-1 text-xs" :class="strengthTextColor" x-text="strengthLabel"></p>
        </div>
    @endif
</div>
