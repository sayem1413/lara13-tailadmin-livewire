<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $roleId ? 'Edit Role' : 'Add Role' }}
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $roleId ? 'Update this role\'s name and permissions.' : 'Create a new role and choose its permissions.' }}
        </p>
    </div>

    <form wire:submit="save" class="max-w-3xl space-y-5">
        <x-forms.error for="role" />

        <x-ui.card>
            <x-forms.label for="name">Role Name</x-forms.label>
            <x-forms.input type="text" icon="shield" id="name" wire:model="name" required />
            <x-forms.error for="name" />
        </x-ui.card>

        @php $allPermissionNames = $permissionGroups->flatten()->pluck('name')->all(); @endphp

        <x-ui.card>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Permissions</h3>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <x-forms.checkbox
                        wire:click="toggleAllPermissions"
                        :checked="! empty($allPermissionNames) && empty(array_diff($allPermissionNames, $selectedPermissions))"
                    />
                    Select all
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-gray-500 uppercase dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Module</th>
                            <th class="py-2 pr-4 font-medium">Permissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($permissionGroups as $group => $permissions)
                            @php $namesInGroup = $permissions->pluck('name')->all(); @endphp
                            <tr>
                                <td class="py-3 pr-4 align-top whitespace-nowrap">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-800 capitalize dark:text-white/90">
                                        <x-forms.checkbox
                                            wire:click="toggleGroup({{ json_encode($namesInGroup) }})"
                                            :checked="empty(array_diff($namesInGroup, $selectedPermissions))"
                                        />
                                        {{ str($group)->replace('-', ' ') }}
                                    </label>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex flex-wrap gap-4">
                                        @foreach ($permissions as $permission)
                                            <label class="flex min-h-11 items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <x-forms.checkbox wire:model.live="selectedPermissions" value="{{ $permission->name }}" />
                                                {{ Str::headline($permission->section ?? $permission->name) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-forms.error for="selectedPermissions.*" />
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit" loading-text="Saving...">Save</x-ui.button>
            <a href="{{ route('admin.roles.index') }}">
                <x-ui.button type="button" variant="secondary">Cancel</x-ui.button>
            </a>
        </div>
    </form>
</div>
