<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Roles &amp; Permissions</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage roles and the permissions they grant.</p>
        </div>

        @can('create', \App\Models\Permission\Role::class)
            <a href="{{ route('admin.roles.create') }}">
                <x-ui.button>Add Role</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card :padded="false">
        <div class="border-b border-gray-100 p-4 dark:border-white/10">
            <div class="max-w-xs">
                <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search roles..." />
            </div>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Permissions</th>
                        <th class="px-4 py-3 font-medium">Users</th>
                        <th class="px-4 py-3 font-medium">&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $role->permissions_count }}</td>
                            <td class="px-4 py-3">
                                <a
                                    href="{{ route('admin.users.index', ['role' => $role->name]) }}"
                                    class="text-brand-600 hover:underline dark:text-brand-400"
                                >
                                    {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3 text-sm">
                                    @can('update', $role)
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    @endcan
                                    @can('delete', $role)
                                        <button
                                            type="button"
                                            wire:click="delete({{ $role->id }})"
                                            data-confirm="delete"
                                            data-confirm-entity="Role"
                                            data-confirm-name="{{ $role->name }}"
                                            class="font-medium text-red-600 hover:underline dark:text-red-400"
                                        >
                                            Delete
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No roles found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 md:hidden dark:divide-white/10">
            @forelse ($roles as $role)
                <div wire:key="role-mobile-{{ $role->id }}" class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</p>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $role->permissions_count }} {{ Str::plural('permission', $role->permissions_count) }}</span>
                    </div>

                    <a
                        href="{{ route('admin.users.index', ['role' => $role->name]) }}"
                        class="mt-1 inline-block text-sm text-brand-600 hover:underline dark:text-brand-400"
                    >
                        {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                    </a>

                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                        @can('update', $role)
                            <a href="{{ route('admin.roles.edit', $role) }}" class="flex min-h-11 items-center font-medium text-brand-600 dark:text-brand-400">Edit</a>
                        @endcan
                        @can('delete', $role)
                            <button
                                type="button"
                                wire:click="delete({{ $role->id }})"
                                data-confirm="delete"
                                data-confirm-entity="Role"
                                data-confirm-name="{{ $role->name }}"
                                class="min-h-11 font-medium text-red-600 dark:text-red-400"
                            >
                                Delete
                            </button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No roles found.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$roles" />
    </x-ui.card>
</div>
