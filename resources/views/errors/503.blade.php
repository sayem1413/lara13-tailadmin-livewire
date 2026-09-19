<x-guest-layout title="Maintenance">
    <div class="text-center">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">We&rsquo;ll be back soon</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $exception->getMessage() ?: 'The application is currently undergoing maintenance. Please check back shortly.' }}
        </p>
    </div>
</x-guest-layout>
