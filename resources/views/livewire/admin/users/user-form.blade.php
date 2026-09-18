<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $userId ? 'Edit User' : 'Add User' }}
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $userId ? "Update this user's details, status, and roles." : 'Create a new user account.' }}
        </p>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-5">
        <x-ui.card title="Details" class="space-y-5">
            <div>
                <x-forms.label for="name">Name</x-forms.label>
                <x-forms.input type="text" icon="user-circle" id="name" wire:model="name" required />
                <x-forms.error for="name" />
            </div>

            <div>
                <x-forms.label for="email">Email</x-forms.label>
                <x-forms.input type="email" icon="mail" id="email" wire:model="email" required />
                <x-forms.error for="email" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-forms.label for="password">
                        {{ $userId ? 'New Password' : 'Password' }}
                    </x-forms.label>
                    <x-forms.password meter id="password" wire:model="password" autocomplete="new-password" />
                    @if ($userId)
                        <p class="mt-1 text-xs text-gray-400">Leave blank to keep the current password.</p>
                    @endif
                    <x-forms.error for="password" />
                </div>

                <div>
                    <x-forms.label for="password_confirmation">Confirm Password</x-forms.label>
                    <x-forms.password id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <x-forms.checkbox wire:model="is_active" />
                Active
            </label>
        </x-ui.card>

        <x-ui.card title="Roles">
            @if (empty($availableRoles))
                <p class="text-sm text-gray-500 dark:text-gray-400">No roles available yet.</p>
            @else
                <x-forms.select searchable multiple wire:model="selectedRoles" id="selectedRoles" placeholder="Assign roles...">
                    @foreach ($availableRoles as $roleName)
                        <option value="{{ $roleName }}" @selected(in_array($roleName, $selectedRoles, true))>{{ $roleName }}</option>
                    @endforeach
                </x-forms.select>
            @endif
            <x-forms.error for="selectedRoles" />
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit" loading-text="Saving...">Save</x-ui.button>
            <a href="{{ route('admin.users.index') }}">
                <x-ui.button type="button" variant="secondary">Cancel</x-ui.button>
            </a>
        </div>
    </form>
</div>
