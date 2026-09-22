@props(['wireModel', 'title' => null, 'maxWidth' => 'md'])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-md',
    };
@endphp

<div
    x-show="$wire.{{ $wireModel }}"
    x-cloak
    @keydown.escape.window="$wire.{{ $wireModel }} = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    <div class="fixed inset-0 bg-gray-900/50" @click="$wire.{{ $wireModel }} = false"></div>

    <div
        @click.outside="$wire.{{ $wireModel }} = false"
        {{ $attributes->merge(['class' => "relative w-full $maxWidthClass rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"]) }}
    >
        @if ($title)
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
                <button type="button" @click="$wire.{{ $wireModel }} = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-ui.icon name="x-mark" class="size-5" />
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
