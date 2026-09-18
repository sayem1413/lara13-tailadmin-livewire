<x-app-layout title="View User">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
        </div>

        @can('update', $user)
            <a href="{{ route('admin.users.edit', $user) }}">
                <x-ui.button>Edit User</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card title="Details">
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    @if ($user->is_active)
                        <x-ui.badge color="green">Active</x-ui.badge>
                    @else
                        <x-ui.badge color="red">Inactive</x-ui.badge>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Roles</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @forelse ($user->roles as $userRole)
                        <x-ui.badge color="brand">{{ $userRole->name }}</x-ui.badge>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">No roles assigned.</span>
                    @endforelse
                </dd>
            </div>
        </dl>
    </x-ui.card>

    <div class="mt-4">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Back to Users</a>
    </div>
</x-app-layout>
