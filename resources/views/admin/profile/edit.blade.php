<x-app-layout title="Profile">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Profile</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Update your account's profile information.</p>
    </div>

    <x-ui.card class="max-w-2xl">
        @if (session('status') === 'profile-information-updated')
            <x-ui.alert type="success" class="mb-6">Your profile has been updated.</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('user-profile-information.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-4">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="size-16 rounded-full object-cover" />
                @else
                    <span class="flex size-16 items-center justify-center rounded-full bg-brand-50 text-lg font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        {{ $user->initials() }}
                    </span>
                @endif

                <div>
                    <x-forms.label for="avatar">Avatar</x-forms.label>
                    <input type="file" name="avatar" id="avatar" accept="image/*" class="block text-sm text-gray-600 dark:text-gray-300" />
                    <x-forms.error for="avatar" bag="updateProfileInformation" />
                </div>
            </div>

            <div>
                <x-forms.label for="name">Name</x-forms.label>
                <x-forms.input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required />
                <x-forms.error for="name" bag="updateProfileInformation" />
            </div>

            <div>
                <x-forms.label for="email">Email</x-forms.label>
                <x-forms.input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required />
                <x-forms.error for="email" bag="updateProfileInformation" />
            </div>

            <x-ui.button type="submit">Save Changes</x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
