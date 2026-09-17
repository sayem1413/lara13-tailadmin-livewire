@props(['title' => null, 'padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-white/10 dark:bg-white/[0.03] '.($padded ? 'p-5 sm:p-6' : '')]) }}>
    @if ($title)
        <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
    @endif

    {{ $slot }}
</div>
