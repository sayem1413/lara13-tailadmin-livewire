<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Users</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage user accounts, roles, and access.</p>
        </div>

        @can('create', \App\Models\User::class)
            <a href="{{ route('admin.users.create') }}">
                <x-ui.button>Add User</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="min-w-[220px] flex-1">
                <x-forms.input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email..." />
            </div>

            <select wire:model.live="role" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-white/90">
                <option value="">All roles</option>
                @foreach ($this->roleOptions as $roleName)
                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-white/90">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            @if (! empty($selected))
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($selected) }} selected</span>
                    <x-ui.button variant="secondary" wire:click="bulkActivate">Activate</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="bulkDeactivate">Deactivate</x-ui.button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <x-forms.checkbox
                                wire:click="toggleSelectAllOnPage"
                                @checked($this->allOnPageSelected($users->pluck('id')->all()))
                            />
                        </th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Roles</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-4 py-3">
                                <x-forms.checkbox wire:model.live="selected" value="{{ $user->id }}" />
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $userRole)
                                        <x-ui.badge color="brand">{{ $userRole->name }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-gray-400">&mdash;</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->is_active)
                                    <x-ui.badge color="green">Active</x-ui.badge>
                                @else
                                    <x-ui.badge color="red">Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3 text-sm">
                                    @can('update', $user)
                                        <button type="button" wire:click="toggleActive({{ $user->id }})" class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    @endcan
                                    @can('delete', $user)
                                        <button type="button" wire:click="delete({{ $user->id }})" data-confirm="delete" class="font-medium text-red-600 hover:underline dark:text-red-400">
                                            Delete
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 p-4 dark:border-white/10">
            {{ $users->links() }}
        </div>
    </x-ui.card>
</div>
