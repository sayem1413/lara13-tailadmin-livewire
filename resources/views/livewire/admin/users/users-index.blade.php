<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Users</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage user accounts, roles, and access.</p>
        </div>

        <div class="flex items-center gap-2">
            @can('admin.users.export')
                <x-ui.button type="button" variant="secondary" wire:click="export">Export Excel</x-ui.button>
                <x-button.export-pdf action="exportPdf" />
            @endcan

            @can('admin.users.import')
                <x-import.button :model="\App\Models\User::class" permission="admin.users.import" title="Import Users" wire:key="import-users" />
            @endcan

            @can('create', \App\Models\User::class)
                <a href="{{ route('admin.users.create') }}">
                    <x-ui.button>Add User</x-ui.button>
                </a>
            @endcan
        </div>
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="min-w-[220px] flex-1">
                <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email..." />
            </div>

            <div class="w-44">
                <x-forms.select searchable wire:model.live="role">
                    <option value="">All roles</option>
                    @foreach ($this->roleOptions as $roleName)
                        <option value="{{ $roleName }}">{{ $roleName }}</option>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="w-40">
                <x-forms.select wire:model.live="status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </x-forms.select>
            </div>

            @if (! empty($selected))
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($selected) }} selected</span>
                    <x-ui.button
                        variant="secondary"
                        wire:click="bulkActivate"
                        data-confirm="bulk-activate"
                        data-confirm-entity-plural="users"
                        data-confirm-count="{{ count($selected) }}"
                    >Activate</x-ui.button>
                    <x-ui.button
                        variant="secondary"
                        wire:click="bulkDeactivate"
                        data-confirm="bulk-deactivate"
                        data-confirm-entity-plural="users"
                        data-confirm-count="{{ count($selected) }}"
                    >Deactivate</x-ui.button>
                </div>
            @endif
        </div>

        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th class="w-10 px-4 py-3">
                        <x-forms.checkbox
                            wire:click="toggleSelectAllOnPage"
                            :checked="$this->allOnPageSelected($users->pluck('id')->all())"
                        />
                    </th>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Roles</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">&nbsp;</th>
                </tr>
            </x-slot:head>

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
                                        @if ($user->id !== auth()->id())
                                            <button type="button" wire:click="toggleActive({{ $user->id }})" class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    @endcan
                                    @can('delete', $user)
                                        <button
                                            type="button"
                                            wire:click="delete({{ $user->id }})"
                                            data-confirm="delete"
                                            data-confirm-entity="User"
                                            data-confirm-name="{{ $user->name }}"
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
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
        </x-ui.table>

        <div class="divide-y divide-gray-100 md:hidden dark:divide-white/10">
            @forelse ($users as $user)
                <div wire:key="user-mobile-{{ $user->id }}" class="flex gap-3 p-4">
                    <x-forms.checkbox class="mt-1" wire:model.live="selected" value="{{ $user->id }}" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                            </div>
                            @if ($user->is_active)
                                <x-ui.badge color="green">Active</x-ui.badge>
                            @else
                                <x-ui.badge color="red">Inactive</x-ui.badge>
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap gap-1">
                            @forelse ($user->roles as $userRole)
                                <x-ui.badge color="brand">{{ $userRole->name }}</x-ui.badge>
                            @empty
                                <span class="text-xs text-gray-400">No roles</span>
                            @endforelse
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                            @can('update', $user)
                                @if ($user->id !== auth()->id())
                                    <button type="button" wire:click="toggleActive({{ $user->id }})" class="min-h-11 font-medium text-gray-600 dark:text-gray-300">
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                @endif
                                <a href="{{ route('admin.users.edit', $user) }}" class="flex min-h-11 items-center font-medium text-brand-600 dark:text-brand-400">Edit</a>
                            @endcan
                            @can('delete', $user)
                                <button
                                    type="button"
                                    wire:click="delete({{ $user->id }})"
                                    data-confirm="delete"
                                    data-confirm-entity="User"
                                    data-confirm-name="{{ $user->name }}"
                                    class="min-h-11 font-medium text-red-600 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No users found.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$users" />
    </x-ui.card>
</div>
