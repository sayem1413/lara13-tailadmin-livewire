<x-app-layout title="Dashboard">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Welcome back, {{ auth()->user()->name }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Here's what's happening in your application.</p>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.card>
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Users</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $userCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500 dark:text-gray-400">Roles</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $roleCount }}</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm text-gray-500 dark:text-gray-400">Your Roles</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                {{ auth()->user()->getRoleNames()->implode(', ') ?: '—' }}
            </p>
        </x-ui.card>
    </div>
</x-app-layout>
