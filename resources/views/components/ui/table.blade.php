@props([])

<div {{ $attributes->merge(['class' => 'hidden overflow-x-auto md:block']) }}>
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase dark:border-white/10 dark:text-gray-400">
            {{ $head }}
        </thead>
        {{ $slot }}
    </table>
</div>
