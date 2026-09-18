<x-app-layout title="View Role">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $role->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $role->users()->count() }} user(s) assigned</p>
        </div>

        @can('update', $role)
            <a href="{{ route('admin.roles.edit', $role) }}">
                <x-ui.button>Edit Role</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card title="Permissions">
        <div class="flex flex-wrap gap-1">
            @forelse ($role->permissions as $permission)
                <x-ui.badge color="brand">{{ $permission->name }}</x-ui.badge>
            @empty
                <span class="text-sm text-gray-500 dark:text-gray-400">No permissions assigned.</span>
            @endforelse
        </div>
    </x-ui.card>

    <div class="mt-4">
        <a href="{{ route('admin.roles.index') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Back to Roles</a>
    </div>
</x-app-layout>
